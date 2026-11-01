<?php

use Modules\TaxModule\Http\Controllers\Api\V1\Common\Tax\TaxController;
use Illuminate\Support\Facades\Route;

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
Route::prefix('taxvat')->name('taxvat.')->group(function () {
    Route::controller(TaxController::class)->group(function () {
        Route::get('get-taxVat-list', 'index');
        Route::put('get-calculated-tax', 'calculate');
    });
});
