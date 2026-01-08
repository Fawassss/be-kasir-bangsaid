<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\WelcomeController;

// Public Routes
Route::get('/', [WelcomeController::class, 'index']);

