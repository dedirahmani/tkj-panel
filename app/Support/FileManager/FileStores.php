<?php

namespace App\Support\FileManager;

use App\Models\User;

/** Pilih driver file manager sesuai config filemanager.driver. */
final class FileStores
{
    public static function for(User $owner): FileStore
    {
        return config('filemanager.driver') === 'agent'
            ? new AgentFileManager($owner)
            : new FileManager($owner);
    }
}
