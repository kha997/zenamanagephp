<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth'])->group(function () {
    // Admin routes will be added here when needed
    // Currently empty as per spec
});
