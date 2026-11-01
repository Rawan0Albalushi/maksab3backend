<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\app\Http\Controllers\Api\V1\Customer\Chat\ConversationController;
use Modules\AI\app\Http\Controllers\Api\V1\Customer\Chat\MessageController;
use Modules\AI\app\Http\Controllers\Api\V1\Vendor\Product\ProductAutoFillController;
use Modules\AI\app\Http\Middleware\AiChatEnabled;

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
Route::middleware(['vendor.api','actch:vendor_app'])->prefix('ai')->name('ai.')->group(function () {
    Route::controller(ProductAutoFillController::class)->group(function () {
        Route::get('generate-title-and-description', 'generateTitleAndDescription');
        Route::get('generate-other-data', 'generateGeneralAndPriceData');
        Route::get('generate-variation-data', 'generateVariationData');
        Route::get('generate-title-suggestions', 'generateTitleSuggestions');
        Route::post('generate-form-image', 'generateTitleFromImage');
    });
});
Route::prefix('customer')->group(function () {
    Route::middleware([AiChatEnabled::class, 'throttle:ai-chat-group'])->prefix('ai-chat')->group(function () {
        Route::controller(MessageController::class)->group(function () {
            Route::post('send', 'store')->middleware('throttle:ai-chat-send');
            Route::get('messages', 'index');
        });
        Route::controller(ConversationController::class)->group(function () {
            Route::get('conversations', 'index');
            Route::delete('conversations/{id}', 'destroy');
        });
    });
});
