<?php

namespace App\Support\FileManager;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operasi file manager untuk satu user. Semua path relatif terhadap root user dan
 * divalidasi; kegagalan dilempar sebagai RuntimeException berpesan ramah.
 *
 * - FileManager       : sandbox lokal (pengembangan / Windows), FILEMANAGER_DRIVER=local
 * - AgentFileManager  : /home/<user> sungguhan lewat tkjpanel-agentd, FILEMANAGER_DRIVER=agent
 */
interface FileStore
{
    /** @return array<int, array<string, mixed>> */
    public function list(string $relDir = ''): array;

    public function read(string $relFile): string;

    public function write(string $relFile, string $content): void;

    public function makeDirectory(string $relDir, string $name): string;

    public function delete(string $relPath): void;

    public function rename(string $from, string $to): void;

    public function storeUpload(string $relDir, UploadedFile $file): void;

    public function download(string $relFile): Response;

    public function chmod(string $relPath, string $mode): void;
}
