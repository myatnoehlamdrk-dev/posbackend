<?php

use App\Http\Controllers\UploadsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Local uploads go through the framework (not straight off disk) so the
// response can carry the CORS header the Flutter web build needs — see
// UploadsController::show.
Route::get('/uploads/{filename}', [UploadsController::class, 'show'])
    ->where('filename', '[A-Za-z0-9._-]+');
