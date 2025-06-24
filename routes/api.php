<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DozensController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ProductController;

Route::post("login", [UserController::class, "login"]);

Route::middleware(['auth:api'])->group(function () {

    Route::middleware('Admin')->group(function () {

        Route::controller(UserController::class)->group(function () {
            Route::get('get_users', 'getUsers');
            // Route::put('block_users', 'blockUser');
            // Route::put('open_user', 'openUser');
            Route::post('add_users', 'addUsers');
        });
    });

    Route::controller(DozensController::class)->group(function () {
        Route::get('get_goods', 'getDozen');
        Route::get('get_products_by_barcode', 'getProductsByBarcode');
        Route::post('add_product_dozen', 'addProductDozen');
        Route::post('delete_product', 'deleteDozenProduct');
        Route::post('edit_product', 'editDozenProduct');
    });

    Route::controller(SalesController::class)->group(function () {
        Route::get('get_sales', 'getSales');
        Route::post('add_sales', 'addSales');
    });

    Route::controller(ProductController::class)->group(function () {
        Route::get('get_products', 'getProducts');
    });

});
