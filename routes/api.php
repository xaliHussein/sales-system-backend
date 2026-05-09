<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\DozensController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\DebtorUsersController;

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
        Route::get('check_barcode', 'checkBarcode');
        Route::post('add_product_dozen', 'addProductDozen');
        Route::post('delete_product', 'deleteDozenProduct');
        Route::post('edit_product', 'editDozenProduct');
    });

    Route::controller(SalesController::class)->group(function () {
        Route::get('get_sales', 'getSales');
        Route::get('get_sales_delivery', 'getSalesDelivery');
        Route::post('add_sales', 'addSales');
        Route::post('change_type_delivery', 'changeTypeDelivery');
        Route::post('retrieve_all_items', 'retrieveAllItems');
        Route::post('retrieve_item', 'retrieveItem');
        Route::post('change_sale_type', 'changeSaleType');
        Route::post('add_info_customer', 'addInfoCustomer');
    });

    Route::controller(ProductController::class)->group(function () {
        Route::get('get_products', 'getProducts');
    });

    Route::controller(StatisticsController::class)->group(function () {
        Route::get('get_statistics', 'getStatistics');
        Route::get('get_range_sales_statistics', 'getRangeSalesStatistics');
    });

    Route::controller(DebtorUsersController::class)->group(function () {
        Route::get('get_debtor_users', 'getDebtorUsers');
        Route::get('get_sales_to_debtor', 'getSalesToDebtor');
        Route::get('get_statistics_debtor_user', 'getStatisticsDebtorUser');
        Route::get('get_sales_to_debtor_with_payments_items', 'getSalesToDebtorWithPaymentsItems');
        Route::post('add_debtor_users', 'addDebtorUsers');
        Route::post('edit_debtor_users', 'editDebtorUsers');
        Route::post('debt_repayment', 'debtRepayment');
        Route::post('add_new_payment', 'addNewPayment');
        Route::post('retrieve_item_debtor', 'retrieveItemDebtor');
        Route::post('get_invoice_to_print', 'getInvoiceToPrint');
        Route::post('add_payment', 'addGeneralPayment');
        Route::post('change_debit_type', 'changeDebitType');
    });

});
