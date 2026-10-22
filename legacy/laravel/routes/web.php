<?php

use App\Http\Controllers\Admin\CKEditorUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'throttle:admin-upload'])
    ->prefix('admin/media')
    ->group(function () {
        Route::get('/library', [CKEditorUploadController::class, 'index'])
            ->name('admin.media.library');

        Route::post('/ckeditor-upload', [CKEditorUploadController::class, 'store'])
            ->name('admin.media.ckeditor-upload');
    });
