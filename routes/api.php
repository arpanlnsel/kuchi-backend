<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MataDataController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
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
        // Get all mata data (with optional user_id filter)
        Route::get('mata-data', [MataDataController::class, 'index']);
        
        // Get mata data by ID
        Route::get('mata-data/{mata_id}', [MataDataController::class, 'show']);
        
        // Get mata data by user ID
        Route::get('mata-data/user/{user_id}', [MataDataController::class, 'getByUser']);
    });

    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('admin/dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
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