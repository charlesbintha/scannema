<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\ImportController;

/*
|--------------------------------------------------------------------------
| SCANNEMA API Routes
|--------------------------------------------------------------------------
*/

// Mobile authentication
Route::post('/mobile/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(\App\Http\Middleware\ScannerSession::class)->group(function () {
Route::get('/agents', [\App\Http\Controllers\AgentController::class, 'index']);
Route::match(['post', 'patch'], '/agents', [\App\Http\Controllers\AgentController::class, 'save']);
Route::post('/mobile/auth/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\DB::table('auth_sessions')->where('token_hash', hash('sha256', $request->bearerToken()))->delete();
    return response()->json(['ok' => true]);
});
Route::patch('/events/{eventId}/tickets', [\App\Http\Controllers\PaymentController::class, 'update']);

// Events
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::get('/events/{eventId}', [EventController::class, 'show']);

// Scan verification
Route::post('/events/{eventId}/scan/verify', [ScanController::class, 'verify']);

// Stats & scan logs
Route::get('/events/{eventId}/stats', [StatsController::class, 'stats']);
Route::get('/events/{eventId}/scan-logs', [StatsController::class, 'scanLogs']);

// CSV import
Route::post('/events/{eventId}/invitations/import', [ImportController::class, 'import']);

});
