<?php

use Illuminate\Support\Facades\Route;
use Xfa\Pdf\Http\Controllers\XfaPdfController;

Route::group([
    'prefix' => config('xfa-pdf.route_prefix', 'xfa-pdf'),
    'middleware' => config('xfa-pdf.middleware', ['web']),
], function () {
    Route::get('/', [XfaPdfController::class, 'index'])->name('xfa-pdf.index');
    Route::get('/upload', [XfaPdfController::class, 'create'])->name('xfa-pdf.create');
    Route::post('/upload', [XfaPdfController::class, 'store'])->name('xfa-pdf.store');
    Route::get('/{document}', [XfaPdfController::class, 'show'])->name('xfa-pdf.show');
    Route::get('/{document}/edit', [XfaPdfController::class, 'edit'])->name('xfa-pdf.edit');
    Route::put('/{document}', [XfaPdfController::class, 'update'])->name('xfa-pdf.update');
    Route::delete('/{document}', [XfaPdfController::class, 'destroy'])->name('xfa-pdf.destroy');

    // AJAX endpoints for repeatable items
    Route::post('/{document}/add-item', [XfaPdfController::class, 'addItem'])->name('xfa-pdf.add-item');
    Route::post('/{document}/remove-item', [XfaPdfController::class, 'removeItem'])->name('xfa-pdf.remove-item');
});
