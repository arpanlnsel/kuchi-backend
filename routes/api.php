<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MataDataController;
use App\Http\Controllers\Api\Admin\HomeBannerController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Public Home Banner routes (no authentication required)
Route::prefix('admin/home-banner')->group(function () {
    Route::get('/', [HomeBannerController::class, 'index']);
    Route::get('/{id}', [HomeBannerController::class, 'show']);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // MataData routes - accessible by both admin and sales
    Route::middleware('role:sales,admin')->group(function () {
        Route::get('mata-data', [MataDataController::class, 'index']);
        Route::get('mata-data/{mata_id}', [MataDataController::class, 'show']);
        Route::get('mata-data/user/{user_id}', [MataDataController::class, 'getByUser']);
    });

    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });
        
        // Home Banner CUD operations - Admin only (Create, Update, Delete)
        Route::prefix('home-banner')->group(function () {
            Route::post('/', [HomeBannerController::class, 'store']);
            Route::post('/{id}', [HomeBannerController::class, 'update']); // POST for multipart/form-data
            Route::delete('/{id}', [HomeBannerController::class, 'destroy']);
        });
        
        // Delete mata data (admin only)
        Route::delete('mata-data/{mata_id}', [MataDataController::class, 'destroy']);
    });

    // Sales dashboard
    Route::middleware('role:sales,admin')->group(function () {
        Route::get('sales/dashboard', function () {
            return response()->json(['message' => 'Sales Dashboard']);
        });
    });
});