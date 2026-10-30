@extends('layout.app')

@section('content')
@php
    $methodId = $form['payment_method_id'] ?? '';
@endphp
<script>
    $(function () {
        var $invoice_id = $('#invoice_id');
        $invoice_id.focus();

        amounts = json_parse('{!! $amounts !!}', {{ (int) config('app.debug') }});
        invoice_payment_methods = json_parse('{!! $invoicePaymentMethods !!}', {{ (int) config('app.debug') }});
        $invoice_id.change(function () {
            var invoice_identifier = "invoice" + $('#invoice_id').val();
            $('#payment_amount').val(amounts[invoice_identifier].replace("&nbsp;", " "));
            $('#payment_method_id').val(invoice_payment_methods[invoice_identifier]).trigger('change');

            if (invoice_payment_methods[invoice_identifier] != 0) {
                $('.payment-method-wrapper').append("<input type='hidden' name='payment_method_id' id='payment-method-id-hidden' class='hidden' value='" + invoice_payment_methods[invoice_identifier] + "'>");
                $('#payment_method_id').prop('disabled', true);
            } else {
                $('#payment-method-id-hidden').remove();
                $('#payment_method_id').prop('disabled', false);
            }
        });
    });
</script>

<form method="post" class="form-horizontal">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

@if ($paymentId)
    <input type="hidden" name="payment_id" value="{{ $paymentId }}">
@endif

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.payment_form') !!}</h1>
        @include('layout.header_buttons', ['attributeCancel' => 'onclick="window.location.href = `'.url('payments').'`;"'])
    </div>

    <div id="content">

        @include('layout.alerts')

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="invoice_id" class="control-label">{!! __('ip.invoice') !!}</label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <select name="invoice_id" id="invoice_id" class="form-control simple-select" required>
@if (! $paymentId)
@foreach ($openInvoices as $invoice)
                    <option value="{{ $invoice->invoice_id }}"
                            {!! ($form['invoice_id'] ?? '') == $invoice->invoice_id ? 'selected="selected"' : '' !!}>
                        {{ $invoice->invoice_number.' - '.format_client($invoice).' - ' }}{!! format_currency($invoice->invoice_balance) !!}
                    </option>
@endforeach
@else
                    <option value="{{ $payment?->invoice_id }}">
                        {{ $payment?->invoice_number.' - '.format_client($payment).' - ' }}{!! format_currency($payment?->invoice_balance) !!}
                    </option>
@endif
                </select>
            </div>
        </div>

        <div class="form-group has-feedback">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="payment_date" class="control-label">{!! __('ip.date') !!}</label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <div class="input-group">
                    <input name="payment_date" id="payment_date"
                           class="form-control datepicker"
                           value="{{ date_from_mysql($form['payment_date'] ?? '') }}" required>
                    <span class="input-group-addon">
                        <i class="fa fa-calendar fa-fw"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="payment_amount" class="control-label">{!! __('ip.amount') !!}</label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <input type="text" name="payment_amount" id="payment_amount" class="form-control"
                       value="{{ format_amount(app(\App\Support\NumberFormat::class)->standardize($form['payment_amount'] ?? '')) }}" required>
            </div>
        </div>

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="payment_method_id" class="control-label">
                    {!! __('ip.payment_method') !!}
                </label>
            </div>
            <div class="col-xs-12 col-sm-6 payment-method-wrapper">

{{-- Add a hidden input field if a payment method was set to pass the disabled attribute --}}
@if ($methodId)
                <input type="hidden" name="payment_method_id" class="hidden"
                       value="{{ $methodId }}">
@endif

                <select id="payment_method_id" name="payment_method_id"
                        class="form-control simple-select" data-minimum-results-for-search="Infinity"
                        {!! $methodId ? 'disabled="disabled"' : '' !!}>
@foreach ($paymentMethods as $paymentMethod)
                    <option value="{{ $paymentMethod->payment_method_id }}"
                        {!! $methodId == $paymentMethod->payment_method_id ? 'selected="selected"' : '' !!}>
                        {{ $paymentMethod->payment_method_name }}
                    </option>
@endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="payment_note" class="control-label">{!! __('ip.note') !!}</label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <textarea name="payment_note"
                          class="form-control">{{ $form['payment_note'] ?? '' }}</textarea>
            </div>

        </div>

    </div>

</form>
@endsection
