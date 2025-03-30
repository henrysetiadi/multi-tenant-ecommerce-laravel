<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;

// -------------------------
// AUTHENTICATION ROUTES
// -------------------------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Route yang membutuhkan autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    //login mode
    Route::get('/list-products', [ProductController::class, 'getAllProductCrossDbExceptMe']);
    Route::get('/list-cart', [CartController::class, 'getCart']);
});

Route::middleware(['auth:sanctum','auth'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/detail-product/{id}', [ProductController::class, 'getProductById']);
    Route::post('/create-products', [ProductController::class, 'store']);
    Route::post('/update-products/{id}', [ProductController::class, 'update']);
    Route::delete('/delete-products/{id}',[ProductController::class, 'destroy']);
});

Route::middleware(['auth:sanctum','auth'])->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}',[CartController::class, 'destroy']);
});

//guest mode
Route::get('/list-product', [ProductController::class, 'getAllProductCrossDb']);



