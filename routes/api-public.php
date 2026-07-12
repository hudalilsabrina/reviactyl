<?php

use App\Http\Controllers\Api\Public\ServerStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/servers/{server}', ServerStatusController::class);
