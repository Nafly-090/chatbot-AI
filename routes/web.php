<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', [ChatbotController::class, 'index'])->name('home');
Route::post('/', [ChatbotController::class, 'process'])->name('home.process');

// History routes
Route::post('/new-chat', [ChatbotController::class, 'newChat'])->name('new-chat');
Route::post('/load-chat', [ChatbotController::class, 'loadChat'])->name('load-chat');
Route::post('/delete-chat', [ChatbotController::class, 'deleteChat'])->name('delete-chat');