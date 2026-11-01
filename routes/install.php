<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::controller(InstallController::class)->group(function () {
    Route::get('/', 'step0')->name('step0');
    Route::get('/step1', 'step1')->name('step1');
    Route::get('/step2', 'step2')->name('step2');
    Route::get('/step3/{error?}', 'step3')->name('step3')->middleware('installation-check');
    Route::get('/step4', 'step4')->name('step4')->middleware('installation-check');
    Route::get('/step5', 'step5')->name('step5')->middleware('installation-check');
    Route::post('/database_installation', 'database_installation')->name('install.db')->middleware('installation-check');
    Route::get('import_sql', 'import_sql')->name('import_sql')->middleware('installation-check');
    Route::get('force-import-sql', 'force_import_sql')->name('force-import-sql')->middleware('installation-check');
    Route::post('system_settings', 'system_settings')->name('system_settings');
    Route::post('purchase_code', 'purchase_code')->name('purchase.code');
});
Route::fallback(function () {
return redirect('/');
});
