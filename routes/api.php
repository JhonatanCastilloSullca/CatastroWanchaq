<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('rentas/lotes/{lote}')
    ->name('api.rentas.')
    ->group(function () {
        Route::get('ficha-individual', [\App\Http\Controllers\Api\RentasPdfController::class, 'individual'])->name('individual');
        Route::get('archivo-rentas', [\App\Http\Controllers\Api\RentasPdfController::class, 'archivoRentas'])->name('archivo');
        Route::get('predio-urbano', [\App\Http\Controllers\Api\RentasPdfController::class, 'predioUrbano'])->name('predio-urbano');
    });
