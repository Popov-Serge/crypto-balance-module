<?php

use App\Http\Controllers\CryptoBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/crypto-balances', [CryptoBalanceController::class, 'index']);
    Route::post('/crypto-balances/credit', [CryptoBalanceController::class, 'credit']);
    Route::post('/crypto-balances/debit', [CryptoBalanceController::class, 'debit']);
});
