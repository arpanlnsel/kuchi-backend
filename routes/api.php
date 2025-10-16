<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MataDataController;
use App\Http\Controllers\Api\Admin\HomeBannerController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Public Home Banner routes
Route::ApiResource('home-banner', HomeBannerController::class)->only(['index', 'show']);

// Public Booking routes
Route::ApiResource('bookings', BookingController::class);

// ✅ Public Events routes (No authentication required)
Route::get('events', [EventController::class, 'index']);
Route::get('events/{event_id}', [EventController::class, 'show']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // ✅ Admin-only Events routes (Create, Update, Delete)
    Route::middleware('role:admin')->group(function () {
        Route::post('events', [EventController::class, 'store']);
        Route::post('events/{event_id}', [EventController::class, 'update']);
        Route::delete('events/{event_id}', [EventController::class, 'destroy']);
    });

    // MataData routes - accessible by both admin and sales
    Route::middleware('role:sales,admin')->group(function () {
        Route::get('mata-data', [MataDataController::class, 'index']);
        Route::get('mata-data/active-sessions', [MataDataController::class, 'getActiveSessions']);
        Route::get('mata-data/{mata_id}', [MataDataController::class, 'show']);
        Route::get('mata-data/user/{user_id}', [MataDataController::class, 'getByUser']);
    });

    // Admin routes - accessible by both admin and sales
    Route::middleware('role:admin,sales')->prefix('admin')->group(function () {
        // User Management
        Route::prefix('sales')->group(function () {
            Route::get('/', [AuthController::class, 'getAllUsers']);
            Route::get('/{id}', [AuthController::class, 'getUserById']);
            Route::put('/{id}/status', [AuthController::class, 'updateUserStatus']);
            Route::put('/{id}/password', [AuthController::class, 'updatePassword']);
        });
    });

    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });
        Route::ApiResource('home-banner', HomeBannerController::class)->only(['store', 'update', 'destroy']);
        Route::delete('mata-data/{mata_id}', [MataDataController::class, 'destroy']);
    });

    // Sales dashboard
    Route::middleware('role:sales,admin')->group(function () {
        Route::get('sales/dashboard', function () {
            return response()->json(['message' => 'Sales Dashboard']);
        });
    });
});