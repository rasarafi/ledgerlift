<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FilterAjaxController;
use App\Http\Controllers\InvoiceGroupController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductAjaxController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// Legacy URLs are HMVC (module/controller/method) and CodeIgniter answers any method;
// each action checks the method itself where it matters.
Route::get('/sessions', [SessionController::class, 'index']);
Route::match(['get', 'post'], '/sessions/login', [SessionController::class, 'login']);
Route::get('/sessions/logout', [SessionController::class, 'logout']);

Route::middleware('admin')->group(function () {
    // default_controller = dashboard
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Reference data and products: x, x/index/{offset?}, x/form/{id?} and POST x/delete/{id}.
    foreach ([
        'tax_rates' => TaxRateController::class,
        'families' => FamilyController::class,
        'units' => UnitController::class,
        'payment_methods' => PaymentMethodController::class,
        'invoice_groups' => InvoiceGroupController::class,
        'products' => ProductController::class,
    ] as $module => $controller) {
        Route::get($module, [$controller, 'index']);
        Route::get($module.'/index/{offset?}', [$controller, 'index'])->where('offset', '[0-9]+');
        Route::match(['get', 'post'], $module.'/form/{id?}', [$controller, 'form']);
        Route::post($module.'/delete/{id}', [$controller, 'delete']);
    }
});

// Base_Controller: an AJAX controller answers nothing to a plain request, before the login check.
Route::middleware(['ajax', 'admin'])->group(function () {
    Route::post('/filter/ajax/filter_families', [FilterAjaxController::class, 'filterFamilies']);
    Route::post('/filter/ajax/filter_products', [FilterAjaxController::class, 'filterProducts']);

    Route::get('/products/ajax/modal_product_lookups/{rand?}', [ProductAjaxController::class, 'modalProductLookups']);
    Route::post('/products/ajax/process_product_selections', [ProductAjaxController::class, 'processProductSelections']);
});
