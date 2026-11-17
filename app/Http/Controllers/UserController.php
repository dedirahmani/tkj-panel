<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AgentJob;
use App\Models\Package;
use App\Models\ResellerAllocation;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\UserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $users = User::query()
            ->with(['package', 'parent'])
            ->whereIn('id', $actor->manageableUserIds())
            ->where('id', '!=', $actor->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('users.index', compact('users', 'actor'));
    }

    public function create(Request $request): View
    {
        $actor = $request->user();

        return view('users.create', [
            'roles' => $actor->role->creatableRoles(),
            'packages' => Package::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $creatableValues = array_map(fn (UserRole $r) => $r->value, $actor->role->creatableRoles());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in($creatableValues)],
            'package_id' => ['nullable', 'exists:packages,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Alokasi (hanya relevan saat Super Admin membuat Reseller)
            'alloc_max_users' => ['nullable', 'integer', 'min:0'],
            'alloc_disk_quota_mb' => ['nullable', 'integer', 'min:0'],
            'alloc_max_domains' => ['nullable', 'integer', 'min:0'],
            'alloc_max_databases' => ['nullable', 'integer', 'min:0'],
        ]);

        $package = ! empty($data['package_id']) ? Package::find($data['package_id']) : null;

        // Reseller menarik dari alokasinya saat membuat user.
        if ($actor->isReseller()) {
            $this->assertResellerAllocation($actor, $package);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'parent_id' => $actor->isReseller() ? $actor->id : null,
            'linux_username' => $this->generateLinuxUsername($data['email']),
            'locale' => app()->getLocale(),
            'is_active' => true,
        ]);

        // Alokasi untuk Reseller baru (dibuat Super Admin).
        if ($user->isReseller()) {
            ResellerAllocation::create([
                'user_id' => $user->id,
                'max_users' => $data['alloc_max_users'] ?? 5,
                'disk_quota_mb' => $data['alloc_disk_quota_mb'] ?? 10240,
                'max_domains' => $data['alloc_max_domains'] ?? 10,
                'max_databases' => $data['alloc_max_databases'] ?? 10,
            ]);
        }

        // Provisioning isolasi Linux (user + home + open_basedir + kuota disk).
        UserProvisioner::provision($user);

        AuditLogger::log(
            action: 'user.created',
            description: "Membuat pengguna {$user->email} ({$user->role->value})",
            targetType: User::class,
            targetId: $user->id,
            properties: ['role' => $user->role->value, 'linux_username' => $user->linux_username],
        );

        return redirect()->route('users.show', $user)
            ->with('success', __('users.created', ['name' => $user->name]));
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeManage($request->user(), $user);

        $user->load(['package', 'parent', 'sftpAccounts', 'allocation']);

        return view('users.show', [
            'user' => $user,
            'recentJobs' => $user->linux_username
                ? AgentJob::where('target_domain', UserProvisioner::target($user))->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }

    /** Provision ulang isolasi Linux (resync ke agent). */
    public function provision(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);

        UserProvisioner::provision($user);
        AuditLogger::log('user.provisioned', "Provision ulang {$user->email}", User::class, $user->id);

        return back()->with('success', __('users.provisioned'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);

        $user->update(['is_active' => false, 'suspended_at' => now()]);
        AuditLogger::log('user.suspended', "Suspend {$user->email}", User::class, $user->id);

        return back()->with('success', __('users.suspended'));
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);

        $user->update(['is_active' => true, 'suspended_at' => null]);
        AuditLogger::log('user.activated', "Aktifkan {$user->email}", User::class, $user->id);

        return back()->with('success', __('users.activated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeManage($actor, $user);

        if ($user->id === $actor->id) {
            abort(403, __('errors.forbidden'));
        }

        UserProvisioner::deprovision($user);

        AuditLogger::log('user.deleted', "Hapus {$user->email}", User::class, $user->id);
        $user->delete();

        return redirect()->route('users.index')->with('success', __('users.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    private function authorizeManage(User $actor, User $target): void
    {
        if (! $actor->canManageUsers() || ! in_array($target->id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    /** Pastikan pembuatan user tidak melebihi alokasi reseller. */
    private function assertResellerAllocation(User $reseller, ?Package $package): void
    {
        $alloc = $reseller->allocation;
        if (! $alloc) {
            return; // tanpa alokasi = tak dibatasi (fallback)
        }

        $usage = $reseller->allocationUsage();

        if ($usage['users'] + 1 > $alloc->max_users) {
            throw ValidationException::withMessages(['role' => __('users.alloc_users_exceeded', ['max' => $alloc->max_users])]);
        }
        if ($usage['disk_mb'] + ($package?->disk_quota_mb ?? 0) > $alloc->disk_quota_mb) {
            throw ValidationException::withMessages(['package_id' => __('users.alloc_disk_exceeded')]);
        }
        if ($usage['domains'] + ($package?->max_domains ?? 0) > $alloc->max_domains) {
            throw ValidationException::withMessages(['package_id' => __('users.alloc_domains_exceeded')]);
        }
        if ($usage['databases'] + ($package?->max_databases ?? 0) > $alloc->max_databases) {
            throw ValidationException::withMessages(['package_id' => __('users.alloc_db_exceeded')]);
        }
    }

    private function generateLinuxUsername(string $email): string
    {
        $base = Str::of($email)->before('@')->lower()->replaceMatches('/[^a-z0-9]/', '')->limit(24, '')->value();
        $base = $base !== '' ? $base : 'user';

        if (ctype_digit($base[0])) {
            $base = 'u'.$base;
        }

        $candidate = $base;
        $i = 1;
        while (User::where('linux_username', $candidate)->exists()) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }
}
