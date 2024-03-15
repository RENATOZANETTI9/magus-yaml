<?php

use Magus\Yaml\Controllers\DatabaseController;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// Database 
Route::get('/get_table_data/{table}/{limit}/{identify?}/{value?}', [DatabaseController::class,'getTableData']);
Route::post('/update_table', [DatabaseController::class, 'updateTable']);
Route::post('/insert_table', [DatabaseController::class, 'insertTable']);
Route::post('/create_or_update_table', [DatabaseController::class, 'createOrUpdateTable']);
Route::get('/parameters', [DatabaseController::class, 'getParameters']);




