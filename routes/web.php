<?php

use App\Http\Controllers\ClientAjaxController;
use App\Http\Controllers\ClientController;
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

    // Clients: the list is split by status, the view by tab, and a deleted client takes its invoices with it.
    Route::get('clients', [ClientController::class, 'index']);
    Route::get('clients/status/{status?}/{offset?}', [ClientController::class, 'status'])->where('offset', '[0-9]+');
    Route::match(['get', 'post'], 'clients/form/{id?}', [ClientController::class, 'form']);
    Route::get('clients/view/{id}/{tab?}/{page?}', [ClientController::class, 'view'])->where('page', '[0-9]+');
    Route::post('clients/delete/{id}', [ClientController::class, 'delete']);
});

// Base_Controller: an AJAX controller answers nothing to a plain request, before the login check.
Route::middleware(['ajax', 'admin'])->group(function () {
    Route::post('/filter/ajax/filter_families', [FilterAjaxController::class, 'filterFamilies']);
    Route::post('/filter/ajax/filter_products', [FilterAjaxController::class, 'filterProducts']);
    Route::post('/filter/ajax/filter_clients', [FilterAjaxController::class, 'filterClients']);

    Route::get('/products/ajax/modal_product_lookups/{rand?}', [ProductAjaxController::class, 'modalProductLookups']);
    Route::post('/products/ajax/process_product_selections', [ProductAjaxController::class, 'processProductSelections']);

    Route::get('/clients/ajax/name_query', [ClientAjaxController::class, 'nameQuery']);
    Route::get('/clients/ajax/save_preference_permissive_search_clients', [ClientAjaxController::class, 'savePreferencePermissiveSearchClients']);
    Route::post('/clients/ajax/save_client_note', [ClientAjaxController::class, 'saveClientNote']);
    Route::post('/clients/ajax/delete_client_note', [ClientAjaxController::class, 'deleteClientNote']);
    Route::post('/clients/ajax/load_client_notes', [ClientAjaxController::class, 'loadClientNotes']);
});
