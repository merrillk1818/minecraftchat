<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NoteController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'chatMaxLength' => (int) Setting::get('chat_max_length', 1000),
        'noteMaxLength' => (int) Setting::get('note_max_length', 10000),
    ]);
});

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register'])->middleware('throttle:register');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Chat API (guests + registered)
Route::prefix('api')->group(function () {
    Route::get('/status',  [ChatController::class, 'status']);
    Route::post('/chat',   [ChatController::class, 'chat'])->middleware('throttle:chat');
});

// Admin panel
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/',                              [DashboardController::class, 'index']);
    Route::post('/scrape',                       [DashboardController::class, 'scrape']);
    Route::get('/documents',                     [DocumentController::class,  'index']);
    Route::post('/documents/upload',             [DocumentController::class,  'upload']);
    Route::post('/documents/reindex-failed',     [DocumentController::class,  'reindexFailed']);
    Route::post('/documents/{id}/reindex',       [DocumentController::class,  'reindex']);
    Route::delete('/documents/{id}',             [DocumentController::class,  'destroy']);
    Route::get('/users',                         [UserController::class,      'index']);
    Route::get('/users/{id}/edit',               [UserController::class,      'edit']);
    Route::post('/users/{id}',                   [UserController::class,      'update']);
    Route::post('/users/{id}/role',              [UserController::class,      'setRole']);
    Route::delete('/users/{id}',                 [UserController::class,      'destroy']);
    Route::get('/settings',                      [SettingsController::class,  'index']);
    Route::post('/settings',                     [SettingsController::class,  'update']);
});

// Authenticated-only API
Route::prefix('api')->middleware('auth')->group(function () {
    Route::get('/sessions',               [ChatController::class, 'sessions']);
    Route::get('/sessions/{id}/messages', [ChatController::class, 'sessionMessages']);
    Route::delete('/sessions/{id}',       [ChatController::class, 'deleteSession']);
    Route::post('/notes',                 [NoteController::class, 'store'])->middleware('throttle:notes');
});

// My Notes (authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/my-notes',              [NoteController::class, 'index']);
    Route::get('/my-notes/{id}/edit',    [NoteController::class, 'edit']);
    Route::post('/my-notes/{id}',        [NoteController::class, 'update']);
    Route::delete('/my-notes/{id}',      [NoteController::class, 'destroy']);
});
