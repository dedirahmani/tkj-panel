<?php

namespace App\Http\Controllers;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\Database;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DatabaseController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $databases = Database::query()
            ->with('owner')
            ->whereIn('user_id', $actor->manageableUserIds())
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('databases.index', [
            'databases' => $databases,
            'adminerInstalled' => is_file((string) config('services.adminer.file')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('databases.create', [
            'owners' => $this->ownerOptions($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]{1,32}$/'],
            'db_user' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_]{1,32}$/'],
            'user_id' => ['required', 'integer', Rule::in($actor->manageableUserIds())],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
            'remote_access' => ['nullable', 'boolean'],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
        ]);

        $owner = User::findOrFail($data['user_id']);
        if (! $owner->linux_username) {
            throw ValidationException::withMessages(['user_id' => __('databases.owner_no_linux')]);
        }

        $this->assertWithinQuota($owner);

        $name = Database::prefixed($owner->linux_username, Str::lower($data['name']));
        $dbUser = Database::prefixed($owner->linux_username, Str::lower($data['db_user'] ?? $data['name']));

        if (Database::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => __('databases.name_taken')]);
        }
        if (Database::where('db_user', $dbUser)->exists()) {
            throw ValidationException::withMessages(['db_user' => __('databases.user_taken')]);
        }

        $remote = $request->boolean('remote_access');
        $allowedIps = $this->parseIps($data['allowed_ips'] ?? null);

        $database = Database::create([
            'user_id' => $owner->id,
            'name' => $name,
            'db_user' => $dbUser,
            'host' => $remote ? '%' : 'localhost',
            'remote_access' => $remote,
            'allowed_ips' => $allowedIps ?: null,
        ]);

        $password = $data['password'] ?? Database::generatePassword();

        AgentJob::dispatch(AgentOperation::CreateDatabase, $database->toAgentPayload($password), $database->name);

        AuditLogger::log('database.created', "Membuat database {$database->name}", Database::class, $database->id, [
            'owner' => $owner->email,
            'remote' => $remote,
        ]);

        return redirect()->route('databases.index')
            ->with('success', __('databases.created', ['name' => $database->name]))
            ->with('db_credentials', [
                'name' => $database->name,
                'user' => $database->db_user,
                'password' => $password,
                'host' => $database->host,
            ]);
    }

    public function resetPassword(Request $request, Database $database): RedirectResponse
    {
        $this->authorizeDb($request->user(), $database);

        $password = Database::generatePassword();

        AgentJob::dispatch(AgentOperation::ResetDbPassword, [
            'db_user' => $database->db_user,
            'password' => $password,
            'host' => $database->host,
        ], $database->name);

        AuditLogger::log('database.password_reset', "Reset password {$database->db_user}", Database::class, $database->id);

        return redirect()->route('databases.index')
            ->with('success', __('databases.password_reset'))
            ->with('db_credentials', [
                'name' => $database->name,
                'user' => $database->db_user,
                'password' => $password,
                'host' => $database->host,
            ]);
    }

    public function updateAccess(Request $request, Database $database): RedirectResponse
    {
        $this->authorizeDb($request->user(), $database);

        $data = $request->validate([
            'remote_access' => ['nullable', 'boolean'],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
        ]);

        $remote = $request->boolean('remote_access');
        $allowedIps = $this->parseIps($data['allowed_ips'] ?? null);

        $database->update([
            'remote_access' => $remote,
            'host' => $remote ? '%' : 'localhost',
            'allowed_ips' => $allowedIps ?: null,
        ]);

        // Re-grant hak akses user db sesuai host/IP baru (whitelist create_db_user).
        AgentJob::dispatch(AgentOperation::CreateDbUser, [
            'db_name' => $database->name,
            'db_user' => $database->db_user,
            'host' => $database->host,
            'remote_access' => $remote,
            'allowed_ips' => $allowedIps,
            'privileges' => 'ALL',
            'regrant_only' => true,
        ], $database->name);

        AuditLogger::log('database.access', "Ubah akses remote {$database->name} -> ".($remote ? 'on' : 'off'), Database::class, $database->id);

        return back()->with('success', __('common.saved'));
    }

    public function destroy(Request $request, Database $database): RedirectResponse
    {
        $this->authorizeDb($request->user(), $database);

        AgentJob::dispatch(AgentOperation::DropDatabase, [
            'db_name' => $database->name,
            'db_user' => $database->db_user,
            'host' => $database->host,
        ], $database->name);

        AuditLogger::log('database.deleted', "Menghapus database {$database->name}", Database::class, $database->id);
        $database->delete();

        return back()->with('success', __('databases.deleted'));
    }

    // --- Helpers -----------------------------------------------------------

    private function authorizeDb(User $actor, Database $database): void
    {
        if (! in_array($database->user_id, $actor->manageableUserIds(), true)) {
            abort(403, __('errors.forbidden'));
        }
    }

    private function assertWithinQuota(User $owner): void
    {
        $package = $owner->package;
        if (! $package) {
            return;
        }

        if ($owner->databases()->count() >= $package->max_databases) {
            throw ValidationException::withMessages([
                'name' => __('databases.quota_exceeded', ['max' => $package->max_databases]),
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

    /**
     * Parse & validasi daftar IP/CIDR (dipisah koma/spasi).
     *
     * @return array<int, string>
     */
    private function parseIps(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn ($ip) => trim($ip))
            ->filter(fn ($ip) => $ip !== '' && $this->isValidIpOrCidr($ip))
            ->unique()
            ->values()
            ->all();
    }

    private function isValidIpOrCidr(string $value): bool
    {
        [$ip, $mask] = array_pad(explode('/', $value, 2), 2, null);

        if ($mask !== null && (! ctype_digit($mask) || (int) $mask < 0 || (int) $mask > 32)) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
