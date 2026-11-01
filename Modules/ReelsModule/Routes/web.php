<?php

use Modules\ReelsModule\Http\Controllers\Admin\ReelController;
use Modules\ReelsModule\Http\Controllers\Vendor\ReelController as VendorReelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['admin', 'module:reels', 'current-module', 'actch:admin_panel'])->prefix('admin/reels')->name('admin.reels.')->group(function () {
    Route::controller(ReelController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/items', 'items')->name('items');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::put('/update/{id}', 'update')->name('update');
        Route::delete('/delete/{id}', 'destroy')->name('destroy');
        Route::get('/status/{id}/{status}', 'status')->name('status');
    });
});
Route::middleware(['vendor', 'module:reels', 'actch:admin_panel'])->prefix('vendor-panel/reels')->name('vendor.reels.')->group(function () {
    Route::controller(VendorReelController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::put('/update/{id}', 'update')->name('update');
        Route::delete('/delete/{id}', 'destroy')->name('destroy');
        Route::get('/status/{id}/{status}', 'status')->name('status');
    });
});
