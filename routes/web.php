<?php

use App\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::redirect("/", "/admin/emails");

Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
