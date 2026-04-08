<?php

use App\Http\Controllers\OxaamReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OxaamReportController::class, 'index'])->name('report.index');
Route::post('/scrape', [OxaamReportController::class, 'scrape'])->name('report.scrape');
