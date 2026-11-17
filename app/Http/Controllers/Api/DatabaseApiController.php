<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Database;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $databases = Database::whereIn('user_id', $request->user()->manageableUserIds())
            ->orderBy('name')
            ->get()
            ->map(fn (Database $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'db_user' => $d->db_user,
                'host' => $d->host,
                'remote_access' => $d->remote_access,
            ]);

        return response()->json(['data' => $databases]);
    }
}
