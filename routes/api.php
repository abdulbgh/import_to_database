<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tenant\Client\Import\ClientImportController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('client/import/file',[ClientImportController::class,'uploadDocument']);
Route::post('client/import/transfer',[ClientImportController::class,'transferToModule']);
Route::get('client/import/error',[ClientImportController::class,'getErrorDataAsExcel']);