<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceAmount;
use App\Models\InvoiceGroup;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemAmount;
use App\Models\InvoiceTaxRate;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;

function makeInvoice(): Invoice
{
    $client = Client::create(['client_name' => 'Acme', 'client_date_created' => now(), 'client_date_modified' => now()]);

    return Invoice::create([
        'user_id' => 1,
        'client_id' => $client->client_id,
        'invoice_group_id' => 3,
        'invoice_date_created' => '2026-10-30',
        'invoice_date_modified' => now(),
        'invoice_date_due' => '2026-11-29',
        'invoice_terms' => '',
        'invoice_url_key' => str_repeat('a', 32),
    ]);
}

it('maps the ip_users row to an authenticatable User', function () {
    $admin = User::find(1);

    expect($admin)->not->toBeNull()
        ->and($admin->getAuthIdentifierName())->toBe('user_id')
        ->and(password_verify('ledgerlift-demo-1', $admin->getAuthPassword()))->toBeTrue()
        ->and($admin->getRememberTokenName())->toBe('')
        ->and($admin->getRememberToken())->toBeNull()
        ->and($admin->toArray())->not->toHaveKey('user_password');
});

it('walks the invoice relations', function () {
    $invoice = makeInvoice();
    $item = $invoice->items()->create(['item_date_added' => '2026-10-30', 'item_name' => 'Work', 'item_quantity' => '2', 'item_price' => '10.00']);
    $item->amount()->create(['item_subtotal' => '20.00']);
    $invoice->amount()->create(['invoice_total' => '20.00']);
    $taxRate = TaxRate::create(['tax_rate_name' => 'VAT', 'tax_rate_percent' => '10.00']);
    $invoice->taxRates()->create(['tax_rate_id' => $taxRate->tax_rate_id]);
    $method = PaymentMethod::first();
    $payment = $invoice->payments()->create(['payment_method_id' => $method->payment_method_id, 'payment_date' => '2026-10-30', 'payment_amount' => '5.00', 'payment_note' => '']);

    $invoice = Invoice::find($invoice->invoice_id);

    expect($invoice->client->client_name)->toBe('Acme')
        ->and($invoice->user->user_id)->toBe(1)
        ->and($invoice->group)->toBeInstanceOf(InvoiceGroup::class)
        ->and($invoice->items->first())->toBeInstanceOf(InvoiceItem::class)
        ->and($invoice->items->first()->amount)->toBeInstanceOf(InvoiceItemAmount::class)
        ->and($invoice->amount)->toBeInstanceOf(InvoiceAmount::class)
        ->and($invoice->taxRates->first())->toBeInstanceOf(InvoiceTaxRate::class)
        ->and($invoice->taxRates->first()->taxRate->tax_rate_name)->toBe('VAT')
        ->and($invoice->payments->first())->toBeInstanceOf(Payment::class)
        ->and($payment->fresh()->method->payment_method_id)->toBe($method->payment_method_id)
        ->and($payment->invoice->invoice_id)->toBe($invoice->invoice_id)
        ->and($invoice->client->invoices->count())->toBe(1);
});

it('leaves money and date columns as the strings MySQL returns', function () {
    $invoice = makeInvoice();
    $invoice->items()->create(['item_date_added' => '2026-10-30', 'item_quantity' => '1.50000000', 'item_price' => '9.90']);
    $invoice->payments()->create(['payment_date' => '2026-10-30', 'payment_amount' => '5.00', 'payment_note' => '']);

    $item = InvoiceItem::first();
    $payment = Payment::first();

    expect($item->item_quantity)->toBe('1.50000000')
        ->and($item->item_price)->toBe('9.90')
        ->and($payment->payment_amount)->toBe('5.00')
        ->and($payment->payment_date)->toBe('2026-10-30')
        ->and(Invoice::first()->invoice_date_due)->toBe('2026-11-29');
});

it('relates products to family, unit and tax rate', function () {
    $unit = Unit::create(['unit_name' => 'hour', 'unit_name_plrl' => 'hours']);
    $taxRate = TaxRate::create(['tax_rate_name' => 'VAT', 'tax_rate_percent' => '10.00']);
    $product = Product::create(['product_name' => 'Widget', 'product_description' => '', 'product_price' => '3.00', 'unit_id' => $unit->unit_id, 'tax_rate_id' => $taxRate->tax_rate_id]);

    expect($product->unit->unit_name)->toBe('hour')
        ->and($product->taxRate->tax_rate_name)->toBe('VAT')
        ->and($product->family)->toBeNull()
        ->and($product->fresh()->product_price)->toBe('3.00')
        ->and($unit->products->count())->toBe(1);
});
