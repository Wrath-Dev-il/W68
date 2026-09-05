<?php

use App\Http\Controllers\Special\UnservedReportController;
use Illuminate\Support\Facades\Route;

Route::get('/sales/unserved-report', [UnservedReportController::class, 'index'])->name('unserved-report');
Route::get('/sales/unserved-report/customers', [UnservedReportController::class, 'customers'])->name('unserved-report.customers');
Route::get('/sales/unserved-report/salesmen', [UnservedReportController::class, 'salesmen'])->name('unserved-report.salesmen');
Route::get('/sales/unserved-report/data', [UnservedReportController::class, 'data'])->name('unserved-report.data');
Route::get('/sales/unserved-report/print', [UnservedReportController::class, 'print'])->name('unserved-report.print');
