<?php

use App\Http\Controllers\Api\CompartimentoController;
use App\Http\Controllers\Api\HistoricoController;
use App\Http\Controllers\Api\IngredienteController;
use Illuminate\Support\Facades\Route;

Route::prefix('ingredientes')->controller(IngredienteController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    // Antes de {ingrediente}: senão "volume" seria tratado como id.
    Route::get('volume', 'volume');
    Route::get('{ingrediente}', 'show')->whereNumber('ingrediente');
    Route::post('{ingrediente}/saida', 'saida')->whereNumber('ingrediente');
});

Route::prefix('compartimentos')->controller(CompartimentoController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('disponiveis', 'disponiveis');
    Route::get('disponiveis-para-venda', 'disponiveisParaVenda');
});

Route::get('historico', [HistoricoController::class, 'index']);
