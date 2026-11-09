<?php

use App\Http\Controllers\CalculatorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Calcucrypt: the calculators
|--------------------------------------------------------------------------
|
| The page routes. Each page renders declared page Data built from the resource's projection;
| the JSON surface is the declared API in routes/api.php.
*/

// Calculators (tool pages).
Route::get('calculators', [CalculatorController::class, 'index'])->name('calculators.index');
Route::get('calculators/{calculator:slug}', [CalculatorController::class, 'show'])->name('calculators.show');
