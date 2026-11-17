<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\AgentJob;
use App\Models\AuditLog;
use App\Models\Database;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $manageableIds = $user->manageableUserIds();

        $stats = [
            'users' => $user->canManageUsers()
                ? max(count($manageableIds) - 1, 0) // kecualikan diri sendiri
                : null,
            'sites' => Site::whereIn('user_id', $manageableIds)->count(),
            'databases' => Database::whereIn('user_id', $manageableIds)->count(),
            'jobs_pending' => $user->isSuperAdmin()
                ? AgentJob::where('status', JobStatus::Pending)->count()
                : AgentJob::where('status', JobStatus::Pending)
                    ->where('requested_by', $user->id)->count(),
        ];

        $recentActivity = AuditLog::query()
            ->with('user')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('user_id', $manageableIds))
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('dashboard', compact('user', 'stats', 'recentActivity'));
    }
}
