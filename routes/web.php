<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', [ChatbotController::class, 'index'])->name('home');
Route::post('/', [ChatbotController::class, 'process']); // No name needed for AJAX