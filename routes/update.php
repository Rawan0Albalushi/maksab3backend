<?php

use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::controller(UpdateController::class)->group(function () {
    Route::get('/', 'update_software_index')->name('index');

    // A second door to the same page. An add-on can claim '/' before the updater does --
    // module service providers are package-discovered, so they register their routes before
    // the application's own providers run, and the Builder storefront registers a root route
    // on every hostname. No add-on defines this path, so the update page stays reachable even
    // when a client's add-on copy is older than the core being installed.
    Route::get('update-software', 'update_software_index')->name('update-software');

    Route::post('update-system', 'update_software')->name('update-system');
});

// To the named path rather than '/', which an add-on may be answering with its own 404.
Route::fallback(function () {
    return redirect()->route('update-software');
});
