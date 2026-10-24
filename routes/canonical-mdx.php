<?php

use App\Http\Controllers\CanonicalMdxReviewController;
use Illuminate\Support\Facades\Route;

if (config('canonical-mdx.enabled', false)) {
    Route::get('account/canonical-mdx/{id}/review', CanonicalMdxReviewController::class)
        ->middleware(['auth', 'verified', 'can:ux.author'])
        ->whereUuid('id')->name('canonical-mdx.review');
}
