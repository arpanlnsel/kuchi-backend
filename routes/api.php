<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MataDataController;
use App\Http\Controllers\Api\Admin\HomeBannerController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\StoneGroupController;
use App\Http\Controllers\Api\TermsAndConditionsController;
use App\Http\Controllers\Api\PrivacyPolicyController;   
use App\Http\Controllers\Api\AboutUsController;   
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

// Public Events routes
Route::get('events', [EventController::class, 'index']);
Route::get('events/{event_id}', [EventController::class, 'show']);

// Public Terms & Conditions routes (Read-only)
Route::get('terms-and-conditions', [TermsAndConditionsController::class, 'index']);
Route::get('terms-and-conditions/{id}', [TermsAndConditionsController::class, 'show']);

// Public Privacy Policy routes (Read-only)
Route::get('privacy-policy', [PrivacyPolicyController::class, 'index']);
Route::get('privacy-policy/{id}', [PrivacyPolicyController::class, 'show']);

// Public about us routes (Read-only)
Route::get('about-us', [AboutUsController::class, 'index']);
Route::get('about-us/{id}', [AboutUsController::class, 'show']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Admin-only Events routes
    Route::middleware('role:admin')->group(function () {
        Route::post('events', [EventController::class, 'store']);
        Route::post('events/{event_id}', [EventController::class, 'update']);
        Route::delete('events/{event_id}', [EventController::class, 'destroy']);
        
        // Stone Groups CRUD routes (Admin only)
        Route::ApiResource('stone-groups', StoneGroupController::class);
        Route::get('stone-groups/search/{keyword}', [StoneGroupController::class, 'search']);
        Route::post('stone-groups/bulk-upload', [StoneGroupController::class, 'bulkUpload']);
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
            Route::get('/search', [AuthController::class, 'searchUsers']);
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
        
        // Terms & Conditions CRUD routes (Admin only)
        Route::prefix('terms-and-conditions')->group(function () {
            Route::post('/', [TermsAndConditionsController::class, 'store']);
            Route::put('/{id}', [TermsAndConditionsController::class, 'update']);
            Route::delete('/{id}', [TermsAndConditionsController::class, 'destroy']);
        });

        // Privacy Policy CRUD routes (Admin only)
        Route::prefix('privacy-policy')->group(function () {
            Route::post('/', [PrivacyPolicyController::class, 'store']);
            Route::put('/{id}', [PrivacyPolicyController::class, 'update']);
            Route::delete('/{id}', [PrivacyPolicyController::class, 'destroy']);
        });

        // about us CRUD routes (Admin only)
        Route::prefix('about-us')->group(function () {
            Route::post('/', [AboutUsController::class, 'store']);
            Route::put('/{id}', [AboutUsController::class, 'update']);
            Route::delete('/{id}', [AboutUsController::class, 'destroy']);
        });
    });

    // Sales dashboard
    Route::middleware('role:sales,admin')->group(function () {
        Route::get('sales/dashboard', function () {
            return response()->json(['message' => 'Sales Dashboard']);
        });
    });
});