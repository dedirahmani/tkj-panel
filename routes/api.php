<?php

use App\Http\Controllers\Api\DatabaseApiController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SiteApiController;
use Illuminate\Support\Facades\Route;

/*
 | REST API TKJPanel (PRD 9) - token-based (Sanctum). Untuk otomasi & bahan ajar API.
 | Token dibuat di panel (menu API) dengan ability terbatas: sites:read, sites:write,
 | databases:read. Semua data dilingkupi ke pemilik token.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::middleware('abilities:sites:read')->get('/sites', [SiteApiController::class, 'index']);
    Route::middleware('abilities:sites:read')->get('/sites/{site}', [SiteApiController::class, 'show']);
    Route::middleware('abilities:sites:write')->post('/sites', [SiteApiController::class, 'store']);

    Route::middleware('abilities:databases:read')->get('/databases', [DatabaseApiController::class, 'index']);
});
