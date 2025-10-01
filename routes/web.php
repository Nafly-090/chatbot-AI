<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', [ChatbotController::class, 'index'])->name('home');
Route::post('/', [ChatbotController::class, 'process']); 



Route::get('/clear-chat', function () {
    Session::forget('conversation');
    return redirect()->route('home');
})->name('chat.clear');