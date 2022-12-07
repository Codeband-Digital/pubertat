<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/fail', [\App\Http\Controllers\PaymentController::class, 'fail']);
Route::get('/success', [\App\Http\Controllers\PaymentController::class, 'success']);
Route::get('/stripeSuccess', [\App\Http\Controllers\PaymentController::class, 'stripeSuccess']);
Route::get('/result', [\App\Http\Controllers\PaymentController::class, 'result']);


Route::post('/api/login/', [\App\Http\Controllers\API\AuthController::class, 'login']);
//Route::get('/api/logout/', [\App\Http\Controllers\API\AuthController::class, 'logout']);
Route::get('/api/getUserInfo/', [\App\Http\Controllers\API\AuthController::class, 'getAuth']);
Route::get('/api/getPaymentLinks/', [\App\Http\Controllers\API\AuthController::class, 'getPaymentLinks']);
Route::get('/api/getCases/', [\App\Http\Controllers\API\CasesController::class, 'getAll']);


Route::get('auth/email-authenticate/{token}', [
    'as' => 'email-authenticate',
    'uses' => '\App\Http\Controllers\UserController@authenticateEmail'
]);
