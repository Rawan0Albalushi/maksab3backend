<?php

use Modules\TaxModule\Http\Controllers\{
    SystemTaxVatSetupController,
    TaxVatController,
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware(['admin','current-module','module:system_tax'])->prefix('taxvat')->name('taxvat.')->group(function () {
    Route::controller(TaxVatController::class)->group(function () {
        Route::get('get-taxvat-data', 'index')->name('index');
        Route::post('add-taxvat-data', 'store')->name('store');
        Route::put('update-taxvat-data/{taxVat}', 'update')->name('update');
        Route::get('update-taxvat-status/{taxVat}', 'status')->name('status');
        Route::get('export-taxvat', 'export')->name('export');
    });
    Route::controller(SystemTaxVatSetupController::class)->group(function () {
        Route::get('system-taxvat', 'index')->name('systemTaxvat');
        Route::put('system-taxvat', 'systemTaxVatStore')->name('systemTaxVatStore');
        Route::get('system-taxvat-vendor-status', 'vendorStatus')->name('systemTaxVatVendorStatus');
    });
});
