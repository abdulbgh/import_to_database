<?php

use App\Http\Controllers\Api\Tenant\Import\ClientImportController;
use App\Http\Controllers\ExcelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('client/import/file',[ClientImportController::class,'uploadDocument']);
Route::post('client/import/transfer',[ClientImportController::class,'transferToModule']);
Route::get('client/import/error',[ClientImportController::class,'getErrorDataAsExcel']);