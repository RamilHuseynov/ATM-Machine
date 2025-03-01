<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');

Route::prefix('accounts')->group(function () {
    Route::apiResource('/', AccountController::class);
    Route::post('{account}/withdraw', [TransactionController::class, 'withdraw']);
    Route::get('{account}/history', [TransactionController::class, 'history']);
});

Route::prefix('transactions')->group(function () {
    Route::delete('{transaction}', [TransactionController::class, 'deleteTransaction']);
});
