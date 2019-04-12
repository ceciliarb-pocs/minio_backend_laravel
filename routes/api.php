<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/arquivos/{nome_arquivo}/{modo?}', 'ArquivoController@recuperaArquivo');
Route::delete('/arquivos/{nome_arquivo}', 'ArquivoController@removeArquivo');
Route::post('/arquivos', 'ArquivoController@gravaArquivo');
