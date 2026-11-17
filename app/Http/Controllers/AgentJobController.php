<?php

namespace App\Http\Controllers;

use App\Models\AgentJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentJobController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $jobs = AgentJob::query()
            ->with('requester')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('requested_by', $user->id))
            ->latest()
            ->paginate(25);

        return view('jobs.index', compact('jobs'));
    }
}
