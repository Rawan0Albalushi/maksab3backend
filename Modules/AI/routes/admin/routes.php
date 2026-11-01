<?php

use Modules\AI\app\Http\Controllers\Admin\Web\Product\ProductAutoFillController;
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
Route::middleware(['module:item','current-module'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('product')->name('product.')->group(function () {
        Route::controller(ProductAutoFillController::class)->group(function () {
            Route::get('title-auto-fill', 'titleAutoFill')->name('title-auto-fill');
            Route::get('description-auto-fill', 'descriptionAutoFill')->name('description-auto-fill');
            Route::get('general-setup-auto-fill', 'GeneralSetupAutoFill')->name('general-setup-auto-fill');
            Route::get('price-others-auto-fill', 'PriceOthersAutoFill')->name('price-others-auto-fill');
            Route::get('seo-section-auto-fill', 'seoSectionAutoFill')->name('seo-section-auto-fill');
            Route::get('generate-other-variation-data', 'getOtherVariationData')->name('generate-other-variation-data');
            Route::get('variation-setup-auto-fill', 'variationSetupAutoFill')->name('variation-setup-auto-fill');
            Route::post('analyze-image-auto-fill', 'analyzeImageAutoFill')->name('analyze-image-auto-fill');
            Route::post('generate-title-suggestions', 'generateTitleSuggestions')->name('generate-title-suggestions');
        });
    });
});
