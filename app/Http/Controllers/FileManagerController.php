<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileManager\FileStore;
use App\Support\FileManager\FileStores;
use App\Support\FileManager\PathJail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class FileManagerController extends Controller
{
    public function index(Request $request): View
    {
        $owner = $this->resolveOwner($request);
        $path = $this->safePath($request->query('path', ''));

        $fm = FileStores::for($owner);
        $entries = [];
        $error = null;
        try {
            $entries = $fm->list($path);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $path = '';
        }

        return view('files.index', [
            'owner' => $owner,
            'owners' => $this->ownerOptions($request->user()),
            'path' => $path,
            'crumbs' => $this->breadcrumbs($path),
            'entries' => $entries,
            'listError' => $error,
        ]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $file = $this->safePath($request->query('path', ''));

        try {
            $content = FileStores::for($owner)->read($file);
        } catch (RuntimeException $e) {
            return redirect()->route('files.index', ['owner' => $owner->id])->with('error', $e->getMessage());
        }

        return view('files.edit', [
            'owner' => $owner,
            'file' => $file,
            'dir' => trim(dirname($file), '.'),
            'content' => $content,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        return $this->run($owner, fn (FileStore $fm) => $fm->write($this->safePath($data['path']), $data['content'] ?? ''),
            'file.saved', "Simpan berkas {$data['path']}",
            redirectTo: route('files.edit', ['owner' => $owner->id, 'path' => $this->safePath($data['path'])]),
            flash: __('files.saved'),
        );
    }

    public function mkdir(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return $this->run($owner, fn (FileStore $fm) => $fm->makeDirectory($this->safePath($data['path'] ?? ''), $data['name']),
            'file.mkdir', "Buat folder {$data['name']}", $this->backToDir($owner, $data['path'] ?? ''), __('files.folder_created'));
    }

    public function rename(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'dir' => ['nullable', 'string'],
        ]);

        return $this->run($owner, fn (FileStore $fm) => $fm->rename($this->safePath($data['from']), $this->safePath($data['to'])),
            'file.renamed', "Rename {$data['from']} -> {$data['to']}", $this->backToDir($owner, $data['dir'] ?? ''), __('files.renamed'));
    }

    public function delete(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'dir' => ['nullable', 'string'],
        ]);

        return $this->run($owner, fn (FileStore $fm) => $fm->delete($this->safePath($data['path'])),
            'file.deleted', "Hapus {$data['path']}", $this->backToDir($owner, $data['dir'] ?? ''), __('files.deleted'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'path' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:'.config('filemanager.max_upload_kb')],
        ]);

        return $this->run($owner, fn (FileStore $fm) => $fm->storeUpload($this->safePath($data['path'] ?? ''), $request->file('file')),
            'file.uploaded', 'Unggah '.$request->file('file')->getClientOriginalName(), $this->backToDir($owner, $data['path'] ?? ''), __('files.uploaded'));
    }

    public function download(Request $request): Response
    {
        $owner = $this->resolveOwner($request);
        $file = $this->safePath($request->query('path', ''));

        try {
            return FileStores::for($owner)->download($file);
        } catch (RuntimeException $e) {
            return redirect()->route('files.index', ['owner' => $owner->id])->with('error', $e->getMessage());
        }
    }

    /** chmod / chown adalah operasi root -> diteruskan ke agent (PRD 6.10). */
    public function chmod(Request $request): RedirectResponse
    {
        $owner = $this->resolveOwner($request);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'mode' => ['required', 'regex:/^[0-7]{3,4}$/'],
            'dir' => ['nullable', 'string'],
        ]);

        // Langsung (sinkron): driver agent menjalankannya sebagai user situs di /home/<user>.
        return $this->run($owner, fn (FileStore $fm) => $fm->chmod($this->safePath($data['path']), $data['mode']),
            'file.chmod', "chmod {$data['mode']} {$data['path']}", $this->backToDir($owner, $data['dir'] ?? ''), __('files.chmod_done'));
    }

    // --- Helpers -----------------------------------------------------------

    /**
     * Jalankan operasi FileManager dengan penanganan error jail yang seragam.
     */
    private function run(User $owner, callable $op, string $action, string $desc, string $redirectTo, string $flash): RedirectResponse
    {
        try {
            $op(FileStores::for($owner));
        } catch (RuntimeException $e) {
            return redirect($redirectTo)->with('error', $e->getMessage());
        }

        AuditLogger::log($action, $desc, User::class, $owner->id);

        return redirect($redirectTo)->with('success', $flash);
    }

    private function resolveOwner(Request $request): User
    {
        $actor = $request->user();
        $ownerId = (int) $request->input('owner', $request->query('owner', 0));

        if ($ownerId && in_array($ownerId, $actor->manageableUserIds(), true)) {
            return User::findOrFail($ownerId);
        }

        return $actor;
    }

    private function safePath(?string $path): string
    {
        try {
            return PathJail::normalizeRelative((string) $path);
        } catch (RuntimeException) {
            return '';
        }
    }

    private function backToDir(User $owner, string $path): string
    {
        return route('files.index', ['owner' => $owner->id, 'path' => $this->safePath($path)]);
    }

    /** @return array<int, array{name: string, path: string}> */
    private function breadcrumbs(string $path): array
    {
        $crumbs = [];
        $acc = '';
        foreach (array_filter(explode('/', $path)) as $seg) {
            $acc = ltrim($acc.'/'.$seg, '/');
            $crumbs[] = ['name' => $seg, 'path' => $acc];
        }

        return $crumbs;
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
