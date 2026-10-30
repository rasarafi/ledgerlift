<?php

use App\Http\Controllers\ClientAjaxController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FilterAjaxController;
use App\Http\Controllers\InvoiceAjaxController;
use App\Http\Controllers\InvoiceController;
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

    // Invoices: the list by status, the view, and the deletes (a draft, unless ENABLE_INVOICE_DELETION).
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/index', [InvoiceController::class, 'index']);
    Route::get('invoices/status/{status?}/{offset?}', [InvoiceController::class, 'status'])->where('offset', '[0-9]+');
    Route::get('invoices/view/{id}', [InvoiceController::class, 'view']);
    Route::post('invoices/delete/{id}', [InvoiceController::class, 'delete']);
    Route::post('invoices/delete_invoice_tax/{invoiceId}/{invoiceTaxRateId}', [InvoiceController::class, 'deleteInvoiceTax']);
});

// Base_Controller: an AJAX controller answers nothing to a plain request, before the login check.
Route::middleware(['ajax', 'admin'])->group(function () {
    Route::post('/filter/ajax/filter_families', [FilterAjaxController::class, 'filterFamilies']);
    Route::post('/filter/ajax/filter_products', [FilterAjaxController::class, 'filterProducts']);
    Route::post('/filter/ajax/filter_clients', [FilterAjaxController::class, 'filterClients']);
    Route::post('/filter/ajax/filter_invoices', [FilterAjaxController::class, 'filterInvoices']);

    Route::get('/products/ajax/modal_product_lookups/{rand?}', [ProductAjaxController::class, 'modalProductLookups']);
    Route::post('/products/ajax/process_product_selections', [ProductAjaxController::class, 'processProductSelections']);

    Route::get('/clients/ajax/name_query', [ClientAjaxController::class, 'nameQuery']);
    Route::get('/clients/ajax/save_preference_permissive_search_clients', [ClientAjaxController::class, 'savePreferencePermissiveSearchClients']);
    Route::post('/clients/ajax/save_client_note', [ClientAjaxController::class, 'saveClientNote']);
    Route::post('/clients/ajax/delete_client_note', [ClientAjaxController::class, 'deleteClientNote']);
    Route::post('/clients/ajax/load_client_notes', [ClientAjaxController::class, 'loadClientNotes']);

    // jQuery's load() is a GET without data, a POST with it (the client's "create invoice" button).
    Route::match(['get', 'post'], '/invoices/ajax/modal_create_invoice', [InvoiceAjaxController::class, 'modalCreateInvoice']);
    Route::post('/invoices/ajax/create', [InvoiceAjaxController::class, 'create']);
    Route::post('/invoices/ajax/save', [InvoiceAjaxController::class, 'save']);
    Route::post('/invoices/ajax/save_invoice_tax_rate', [InvoiceAjaxController::class, 'saveInvoiceTaxRate']);
    Route::post('/invoices/ajax/delete_item/{invoiceId}', [InvoiceAjaxController::class, 'deleteItem']);
});
