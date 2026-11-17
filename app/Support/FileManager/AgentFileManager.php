<?php

namespace App\Support\FileManager;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * File manager di /home/<user> sungguhan, dilayani tkjpanel-agentd (POST /fs).
 * Agent menjalankan setiap operasi lewat helper yang berjalan SEBAGAI user situs, jadi file
 * yang dibuat/diunggah langsung milik user itu dan terbaca PHP-FPM situsnya.
 *
 * Permintaan ditandatangani: X-TKJ-Sign = hex(HMAC-SHA256(kunci, waktu."\n".body)), dengan
 * kunci = HMAC-SHA256("tkjpanel-fs-v1", APP_KEY mentah) — sama dengan agent/internal/fileman.
 */
class AgentFileManager implements FileStore
{
    public function __construct(private User $owner)
    {
        if (! $owner->linux_username) {
            throw new RuntimeException('Pengguna ini belum punya user Linux.');
        }
    }

    public function list(string $relDir = ''): array
    {
        $maxEdit = (int) config('filemanager.max_edit_bytes');

        return array_map(fn (array $e) => $e + [
            'editable' => $e['type'] === 'file' && ($e['size'] ?? 0) <= $maxEdit,
        ], $this->call(['op' => 'list', 'path' => $relDir])['entries'] ?? []);
    }

    public function read(string $relFile): string
    {
        $res = $this->call(['op' => 'read', 'path' => $relFile, 'max' => (int) config('filemanager.max_edit_bytes')]);

        return (string) base64_decode($res['content'] ?? '', true);
    }

    public function write(string $relFile, string $content): void
    {
        $this->call(['op' => 'write', 'path' => $relFile, 'content' => base64_encode($content)]);
    }

    public function makeDirectory(string $relDir, string $name): string
    {
        $this->assertSafeName($name);
        $path = PathJail::normalizeRelative(trim($relDir.'/'.$name, '/'));
        $this->call(['op' => 'mkdir', 'path' => $path]);

        return $path;
    }

    public function delete(string $relPath): void
    {
        if (PathJail::normalizeRelative($relPath) === '') {
            throw new RuntimeException('Tidak boleh menghapus root.');
        }
        $this->call(['op' => 'delete', 'path' => $relPath]);
    }

    public function rename(string $from, string $to): void
    {
        $this->call(['op' => 'rename', 'path' => $from, 'to' => $to]);
    }

    public function storeUpload(string $relDir, UploadedFile $file): void
    {
        $name = $file->getClientOriginalName();
        $this->assertSafeName($name);
        $this->call([
            'op' => 'write', 'path' => trim($relDir.'/'.$name, '/'),
            'content' => base64_encode((string) file_get_contents($file->getRealPath())), 'no_clobber' => true,
        ]);
    }

    public function download(string $relFile): Response
    {
        $res = $this->call(['op' => 'read', 'path' => $relFile, 'max' => (int) config('filemanager.max_download_bytes', 50 * 1024 * 1024)]);
        $bytes = (string) base64_decode($res['content'] ?? '', true);

        return response()->streamDownload(fn () => print ($bytes), basename($relFile), [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    public function chmod(string $relPath, string $mode): void
    {
        $this->call(['op' => 'chmod', 'path' => $relPath, 'mode' => $mode]);
    }

    /** @return array<string, mixed> */
    private function call(array $request): array
    {
        $body = json_encode($request + ['user' => $this->owner->linux_username], JSON_UNESCAPED_SLASHES);
        $ts = (string) time();

        try {
            $res = Http::withBody($body, 'application/json')
                ->withHeaders(['X-TKJ-Time' => $ts, 'X-TKJ-Sign' => hash_hmac('sha256', $ts."\n".$body, self::key())])
                ->timeout(90)
                ->post(rtrim((string) config('services.agent.url'), '/').'/fs');
        } catch (\Throwable) {
            throw new RuntimeException(__('files.agent_unreachable'));
        }

        $json = $res->json();
        if (! $res->successful() || ! ($json['ok'] ?? false)) {
            throw new RuntimeException($json['error'] ?? __('files.agent_unreachable'));
        }

        return $json;
    }

    /** Kunci HMAC = HMAC-SHA256("tkjpanel-fs-v1", APP_KEY mentah). */
    public static function key(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;

        return hash_hmac('sha256', 'tkjpanel-fs-v1', (string) $raw, true);
    }

    private function assertSafeName(string $name): void
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0") || $name === '..' || $name === '.') {
            throw new RuntimeException('Nama tidak valid.');
        }
    }
}
