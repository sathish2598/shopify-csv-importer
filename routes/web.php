<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/upload');

Route::get('/upload', [UploadController::class, 'create'])->name('uploads.create');
Route::post('/upload', [UploadController::class, 'store'])->name('uploads.store');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/dashboard/{upload}', [DashboardController::class, 'show'])->name('dashboard.show');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
