<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\GuideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Calcucrypt: the calculators and the Guides that explain them
|--------------------------------------------------------------------------
|
| These are Calcucrypt's routes as the tutorial finds them. Every page passes an inline prop
| array, and `resolve` is a hand-rolled JSON route that reads `inputs` unvalidated and returns an
| undeclared shape. The chapters replace that, one declaration at a time.
*/

// Calculators (tool pages) and their JSON re-resolution seam.
Route::get('calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('calculators/{calculator:slug}', [CalculatorController::class, 'show'])->name('calculators.show');
Route::match(['get', 'post'], 'calculators/{calculator:slug}/resolve', [CalculatorController::class, 'resolve'])
    ->name('calculators.resolve');

// Guides: editorial Posts that embed or link Calculators.
Route::get('guides', [GuideController::class, 'index'])->name('guides.index');
Route::get('guides/{post:slug}', [GuideController::class, 'show'])->name('guides.show');
