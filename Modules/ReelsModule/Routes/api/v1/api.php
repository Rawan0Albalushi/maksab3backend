<?php

use Illuminate\Support\Facades\Route;
use Modules\ReelsModule\Http\Controllers\Api\V1\Customer\Reel\ReelController;
use Modules\ReelsModule\Http\Controllers\Api\V1\Vendor\Reel\ReelController as VendorReelController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware(['localization', 'module-check'])->group(function () {
    Route::prefix('customer')->name('customer.')->group(function () {
        Route::middleware('apiGuestCheck')->prefix('reels')->name('reels.')->group(function () {
            Route::controller(ReelController::class)->group(function () {
                Route::get('list', 'index')->name('list');
                Route::get('details', 'show')->name('show');
                Route::get('stats', 'stats')->name('stats');
                Route::post('visit', 'visit')->name('visit');
            });
        });
        Route::middleware('auth:api')->prefix('reels')->name('reels.')->group(function () {
            Route::controller(ReelController::class)->group(function () {
                Route::post('like', 'like')->name('like');
            });
        });
    });
});
Route::middleware(['vendor.api', 'actch:vendor_app'])->prefix('vendor')->group(function () {
    Route::prefix('reel')->group(function () {
        Route::controller(VendorReelController::class)->group(function () {
            Route::get('list', 'index');
            Route::post('store', 'store');
            Route::get('details', 'show');
            Route::put('update', 'update');
            Route::delete('delete', 'destroy');
            Route::put('status', 'updateStatus');
        });
    });
});
