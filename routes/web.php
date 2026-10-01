<?php

use App\Http\Controllers\WebAdminController;
use App\Http\Middleware\WebSession;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'admin.login')->name('login');
Route::post('/login', [WebAdminController::class, 'login'])->middleware('throttle:10,1')->name('web.login');
Route::middleware(WebSession::class)->group(function () {
    Route::get('/', [WebAdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [WebAdminController::class, 'logout'])->name('web.logout');
    Route::get('/events/new', [WebAdminController::class, 'eventForm'])->name('web.events.new');
    Route::post('/events', [WebAdminController::class, 'saveEvent'])->name('web.events.create');
    Route::get('/events/{eventId}/edit', [WebAdminController::class, 'eventForm'])->whereNumber('eventId')->name('web.events.edit');
    Route::patch('/events/{eventId}', [WebAdminController::class, 'saveEvent'])->whereNumber('eventId')->name('web.events.update');
    Route::post('/events/{eventId}/payments', [WebAdminController::class, 'pay'])->whereNumber('eventId')->name('web.pay');
    Route::post('/events/{eventId}/import', [WebAdminController::class, 'import'])->whereNumber('eventId')->name('web.import');
    Route::get('/agents', [WebAdminController::class, 'agents'])->name('web.agents');
    Route::post('/agents', [WebAdminController::class, 'saveAgent'])->name('web.agents.create');
    Route::patch('/agents', [WebAdminController::class, 'saveAgent'])->name('web.agents.update');
});
