<?php

namespace App\Support\FileManager;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operasi file manager terjail ke root satu user (PRD 6.10, chroot ke home).
 *
 * Driver 'local' (dev): operasi langsung pada sandbox storage/app/filemanager/<user>.
 * Di produksi driver 'agent' meneruskan operasi ke tkjpanel-agentd (root, chroot home).
 * Semua path divalidasi PathJail lebih dulu - tidak ada jalan keluar dari root.
 */
class FileManager implements FileStore
{
    private string $base;

    public function __construct(private User $owner)
    {
        $root = $owner->linux_username ?: 'user'.$owner->id;
        $this->base = rtrim(config('filemanager.base_path'), '/\\').'/'.$root;

        if (! is_dir($this->base)) {
            @mkdir($this->base, 0755, true);
        }
    }

    public function baseDir(): string
    {
        return $this->base;
    }

    /**
     * Daftar isi direktori.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(string $relDir = ''): array
    {
        $abs = PathJail::resolve($this->base, $relDir);
        if (! is_dir($abs)) {
            throw new RuntimeException('Direktori tidak ditemukan.');
        }

        $rel = PathJail::normalizeRelative($relDir);
        $entries = [];

        foreach (scandir($abs) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $abs.'/'.$name;
            $isDir = is_dir($path);
            $childRel = ltrim($rel.'/'.$name, '/');

            $entries[] = [
                'name' => $name,
                'path' => $childRel,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? null : (filesize($path) ?: 0),
                'modified' => date('Y-m-d H:i', filemtime($path) ?: time()),
                'perms' => substr(sprintf('%o', fileperms($path)), -4),
                'editable' => ! $isDir && (filesize($path) ?: 0) <= config('filemanager.max_edit_bytes'),
            ];
        }

        // Direktori dulu, lalu berkas; masing-masing alfabetis.
        usort($entries, fn ($a, $b) => [$b['type'] === 'dir', $a['name']] <=> [$a['type'] === 'dir', $b['name']]);

        return $entries;
    }

    public function read(string $relFile): string
    {
        $abs = PathJail::resolve($this->base, $relFile);
        if (! is_file($abs)) {
            throw new RuntimeException('Berkas tidak ditemukan.');
        }
        if (filesize($abs) > config('filemanager.max_edit_bytes')) {
            throw new RuntimeException('Berkas terlalu besar untuk diedit.');
        }

        return (string) file_get_contents($abs);
    }

    public function write(string $relFile, string $content): void
    {
        $abs = PathJail::resolve($this->base, $relFile);
        if (is_dir($abs)) {
            throw new RuntimeException('Target adalah direktori.');
        }
        file_put_contents($abs, $content);
    }

    public function makeDirectory(string $relDir, string $name): string
    {
        $this->assertSafeName($name);
        $target = PathJail::resolve($this->base, trim($relDir.'/'.$name, '/'));
        if (file_exists($target)) {
            throw new RuntimeException('Sudah ada berkas/direktori dengan nama itu.');
        }
        @mkdir($target, 0755);

        return PathJail::normalizeRelative(trim($relDir.'/'.$name, '/'));
    }

    public function delete(string $relPath): void
    {
        $abs = PathJail::resolve($this->base, $relPath);
        if ($abs === rtrim($this->base, '/')) {
            throw new RuntimeException('Tidak boleh menghapus root.');
        }
        if (is_dir($abs)) {
            $this->deleteRecursive($abs);
        } elseif (is_file($abs)) {
            @unlink($abs);
        }
    }

    public function rename(string $from, string $to): void
    {
        $absFrom = PathJail::resolve($this->base, $from);
        $absTo = PathJail::resolve($this->base, $to);
        if (! file_exists($absFrom)) {
            throw new RuntimeException('Sumber tidak ditemukan.');
        }
        if (file_exists($absTo)) {
            throw new RuntimeException('Target sudah ada.');
        }
        @rename($absFrom, $absTo);
    }

    public function storeUpload(string $relDir, UploadedFile $file): void
    {
        $this->assertSafeName($file->getClientOriginalName());
        $absDir = PathJail::resolve($this->base, $relDir);
        if (! is_dir($absDir)) {
            throw new RuntimeException('Direktori tujuan tidak ada.');
        }
        $file->move($absDir, $file->getClientOriginalName());
    }

    public function download(string $relFile): Response
    {
        return response()->download($this->absoluteFile($relFile));
    }

    /** Sandbox lokal: chmod langsung (di driver agent, dijalankan sebagai user situs). */
    public function chmod(string $relPath, string $mode): void
    {
        if (! preg_match('/^[0-7]{3,4}$/', $mode) || (strlen($mode) === 4 && ! in_array($mode[0], ['0', '1'], true))) {
            throw new RuntimeException('Mode tidak valid (setuid/setgid tidak diizinkan).');
        }
        $abs = PathJail::resolve($this->base, $relPath);
        if (! file_exists($abs) || $abs === rtrim($this->base, '/')) {
            throw new RuntimeException('Berkas tidak ditemukan.');
        }
        @chmod($abs, octdec($mode));
    }

    /** Path absolut untuk unduhan (setelah validasi jail). */
    public function absoluteFile(string $relFile): string
    {
        $abs = PathJail::resolve($this->base, $relFile);
        if (! is_file($abs)) {
            throw new RuntimeException('Berkas tidak ditemukan.');
        }

        return $abs;
    }

    private function assertSafeName(string $name): void
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0") || $name === '..') {
            throw new RuntimeException('Nama tidak valid.');
        }
    }

    private function deleteRecursive(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
