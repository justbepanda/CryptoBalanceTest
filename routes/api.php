<?php

use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawController;

// Контроллер для создания депозита не стал делать, он очевиден
Route::post('/webhook/deposit', [DepositController::class, 'webhook']);
Route::post('/withdraw', [WithdrawController::class, 'withdraw']);
