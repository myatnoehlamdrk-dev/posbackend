<?php
use App\Models\Customer;
use App\Models\User;
use App\Models\inventory_logs;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerController;

use App\Http\Controllers\InventoryLogController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/inventorylog', [InventoryLogController::class, 'store']);
Route::get('/customerprofile', function () {
    $user = Customer::first();

    return response()->json($user);
});
Route::post('/productadd', [ProductController::class, 'store']);
Route::get('/product', [ProductController::class, 'index']);
Route::get('/producthome', [ProductController::class, 'show']);
Route::get('/productgift', [ProductController::class, 'showgift']);
Route::get('/productfashion', [ProductController::class, 'showfashion']);
Route::get('/productbook', [ProductController::class, 'showbook']);
Route::post('/categoryadd', [CategoryController::class, 'store']);
Route::post('/roleadd', [RoleController::class, 'store']);
Route::post('/order', [OrderController::class, 'store']);
Route::get('/order', [OrderController::class, 'index']);
Route::put('/order/{id}', [OrderController::class, 'update']);
Route::delete('/order/{id}', [OrderController::class, 'destroy']);
Route::get('/customer', [CustomerController::class, 'index']);
Route::get('/customerupdate', [CustomerController::class, 'update']);
Route::get('/adminprofile', function () {
    $user = User::first();

    return response()->json($user);
});
Route::delete('/customer/{id}', [CustomerController::class, 'destroy']);
Route::put('/customer/{id}', [CustomerController::class, 'update']);
Route::post('/addcustomer/', [AuthController::class, 'register']);



