<?php

use Illuminate\Support\Facades\Route;

// Catch-all route — Vue Router handles everything on the frontend
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
