<?php

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Queuebert\Http\Controllers\ActivityController;

Route::get('queuebert/activity', ActivityController::class)->name('queuebert.activity');
