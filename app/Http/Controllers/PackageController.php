<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $packages = Package::withCount('users')->orderByDesc('is_default')->orderBy('name')->get();

        return view('packages.index', compact('packages'));
    }

    public function create(): View
    {
        return view('packages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'disk_quota_mb' => ['required', 'integer', 'min:100'],
            'max_domains' => ['required', 'integer', 'min:0'],
            'max_subdomains' => ['required', 'integer', 'min:0'],
            'max_databases' => ['required', 'integer', 'min:0'],
            'max_cronjobs' => ['required', 'integer', 'min:0'],
            'max_sftp_accounts' => ['required', 'integer', 'min:0'],
            'bandwidth_quota_mb' => ['required', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            Package::where('is_default', true)->update(['is_default' => false]);
        }

        $package = Package::create($data);

        AuditLogger::log('package.created', "Membuat paket {$package->name}", Package::class, $package->id);

        return redirect()->route('packages.index')
            ->with('success', __('packages.created', ['name' => $package->name]));
    }
}
