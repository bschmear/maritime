<?php

use App\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentationController::class, 'index'])->name('home');
Route::get('/c/{category:slug}', [DocumentationController::class, 'category'])->name('category');
Route::get('/a/{article:slug}', [DocumentationController::class, 'show'])->name('article');
