<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\GuideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Calcucrypt: the calculators and the Guides that explain them
|--------------------------------------------------------------------------
|
| The page routes. Each page renders declared page Data (chapter 2); the JSON surface is the
| declared API in routes/api.php.
*/

// Calculators (tool pages). The widget re-resolves through the `resolve` operation in
// routes/api.php.
Route::get('calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('calculators/{calculator:slug}', [CalculatorController::class, 'show'])->name('calculators.show');

// Guides: editorial Posts that embed or link Calculators.
Route::get('guides', [GuideController::class, 'index'])->name('guides.index');
Route::get('guides/{post:slug}', [GuideController::class, 'show'])->name('guides.show');
