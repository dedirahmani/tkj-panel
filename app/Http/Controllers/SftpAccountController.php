<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\SftpAccount;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SftpAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = SftpAccount::query()
            ->with('owner')
            ->whereIn('user_id', $request->user()->manageableUserIds())
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('sftp.index', compact('accounts'));
    }

    public function create(Request $request): View
    {
        return view('sftp.create', ['owners' => $this->ownerOptions($request->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{1,20}$/'],
            'subdir' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-\/]{1,60}$/'],
            'user_id' => ['required', 'integer', Rule::in($actor->manageableUserIds())],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
        ]);

        $owner = User::findOrFail($data['user_id']);
        if (! $owner->linux_username) {
            throw ValidationException::withMessages(['user_id' => __('sftp.owner_no_linux')]);
        }

        $this->assertWithinQuota($owner);

        $username = SftpAccount::prefixed($owner->linux_username, Str::lower($data['name']));
        // Akun SFTP adalah user Linux; useradd menolak nama di atas 32 karakter.
        if (strlen($username) > 32) {
            throw ValidationException::withMessages(['name' => __('sftp.name_too_long')]);
        }
        if (SftpAccount::where('username', $username)->exists()) {
            throw ValidationException::withMessages(['name' => __('sftp.name_taken')]);
        }

        $subdir = trim($data['subdir'] ?? $data['name'], '/');
        $homePath = $owner->homePath().'/'.$subdir;

        $account = SftpAccount::create([
            'user_id' => $owner->id,
            'username' => $username,
            'home_path' => $homePath,
            'is_active' => true,
        ]);

        $password = $data['password'] ?? SftpAccount::generatePassword();

        AgentJob::dispatch(AgentOperation::SetSftpAccess, $account->toAgentPayload('create', $password), $owner->linux_username);

        AuditLogger::log('sftp.created', "Buat akun SFTP {$account->username}", SftpAccount::class, $account->id, [
            'owner' => $owner->email,
            'home' => $homePath,
        ]);

        return redirect()->route('sftp.index')
            ->with('success', __('sftp.created', ['name' => $account->username]))
            ->with('sftp_credentials', ['username' => $account->username, 'home' => $homePath, 'password' => $password]);
    }

    public function resetPassword(Request $request, SftpAccount $sftp): RedirectResponse
    {
        $this->authorize($request->user(), $sftp);

        $password = SftpAccount::generatePassword();
        AgentJob::dispatch(AgentOperation::SetSftpAccess, $sftp->toAgentPayload('reset_password', $password), $sftp->owner->linux_username);

        AuditLogger::log('sftp.password_reset', "Reset password SFTP {$sftp->username}", SftpAccount::class, $sftp->id);

        return redirect()->route('sftp.index')
            ->with('success', __('sftp.password_reset'))
            ->with('sftp_credentials', ['username' => $sftp->username, 'home' => $sftp->home_path, 'password' => $password]);
    }

    public function destroy(Request $request, SftpAccount $sftp): RedirectResponse
    {
        $this->authorize($request->user(), $sftp);

        AgentJob::dispatch(AgentOperation::SetSftpAccess, $sftp->toAgentPayload('remove'), $sftp->owner->linux_username);

        AuditLogger::log('sftp.deleted', "Hapus akun SFTP {$sftp->username}", SftpAccount::class, $sftp->id);
        $sftp->delete();

        return back()->with('success', __('sftp.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    private function authorize(User $actor, SftpAccount $account): void
    {
        if (! in_array($account->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    private function assertWithinQuota(User $owner): void
    {
        $package = $owner->package;
        if (! $package) {
            return;
        }

        if ($owner->sftpAccounts()->count() >= $package->max_sftp_accounts) {
            throw ValidationException::withMessages([
                'name' => __('sftp.quota_exceeded', ['max' => $package->max_sftp_accounts]),
            ]);
        }
    }

    private function ownerOptions(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->canManageUsers()) {
            return collect([$actor]);
        }

        return User::whereIn('id', $actor->manageableUserIds())
            ->whereNotNull('linux_username')
            ->orderBy('name')
            ->get();
    }
}
