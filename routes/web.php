<?php

use Bramato\FilamentAiAssistent\Http\Controllers\CategoryController;
use Bramato\FilamentAiAssistent\Http\Controllers\CommentController;
use Bramato\FilamentAiAssistent\Http\Controllers\PostController;
use Bramato\FilamentAiAssistent\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::post('/FilamentAiAssistent/webhook',[\Bramato\FilamentAiAssistent\Http\Controllers\WebhookController::class,'save'])->name('FilamentAiAssistent.webhook');
   
