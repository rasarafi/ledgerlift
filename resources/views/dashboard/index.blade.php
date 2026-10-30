@extends('layout.app')

@use('App\Support\Strangler')

@section('content')
{{-- modules/dashboard/views/index.php --}}
<div id="content">
    @include('layout.alerts')

    <div class="row{{ get_setting('disable_quickactions') == 1 ? ' hidden' : '' }}">
        <div class="col-xs-12">

            <div id="panel-quick-actions" class="panel panel-default quick-actions">

                <div class="panel-heading">
                    <b>{!! __('ip.quick_actions') !!}</b>
                </div>

                <div class="btn-group btn-group-justified no-margin">
                    <a href="{{ Strangler::url('clients/form') }}" class="btn btn-default">
                        <i class="fa fa-user fa-margin"></i>
                        <span class="hidden-xs">{!! __('ip.add_client') !!}</span>
                    </a>
                    {{-- Quotes are not ported: legacy opens a modal here, this is a link to its quote list. --}}
                    <a href="{{ Strangler::url('quotes/index') }}" class="btn btn-default">
                        <i class="fa fa-file fa-margin"></i>
                        <span class="hidden-xs">{!! __('ip.create_quote') !!}</span>
                    </a>
                    <a href="javascript:void(0)" class="create-invoice btn btn-default">
                        <i class="fa fa-file-text fa-margin"></i>
                        <span class="hidden-xs">{!! __('ip.create_invoice') !!}</span>
                    </a>
                    <a href="{{ Strangler::url('payments/form') }}" class="btn btn-default">
                        <i class="fa fa-credit-card fa-margin"></i>
                        <span class="hidden-xs">{!! __('ip.enter_payment') !!}</span>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 col-md-6">

            {{-- The quote overview, recent quotes, projects and tasks panels of legacy. --}}
            <div id="panel-legacy-modules" class="panel panel-default panel-heading">
                <span class="text-muted">Quotes, projects and tasks are in the legacy app:</span>
                <a href="{{ Strangler::url('quotes/index') }}"><i class="fa fa-external-link"></i> {!! __('ip.quotes') !!}</a>
            </div>

        </div>
        <div class="col-xs-12 col-md-6">

            <div id="panel-invoice-overview" class="panel panel-default overview">

                <div class="panel-heading">
                    <b><i class="fa fa-bar-chart fa-margin"></i> {!! __('ip.invoice_overview') !!}</b>
                    <span class="pull-right text-muted">{!! $invoiceStatusPeriod !!}</span>
                </div>

                <table class="table table-hover table-bordered table-condensed no-margin">
@foreach ($invoiceStatusTotals as $total)
                    <tr>
                        <td>
                            <a href="{{ Strangler::url($total['href']) }}">
                                {!! $total['label'] !!}
                            </a>
                        </td>
                        <td class="amount">
                            <span class="{{ $total['class'] }}">
                                {!! format_currency($total['sum_total']) !!}
                            </span>
                        </td>
                    </tr>
@endforeach
                </table>
            </div>
@if (! $hasOverdue)
            <div class="panel panel-default panel-heading">
                <span class="text-muted">{!! __('ip.no_overdue_invoices') !!}</span>
            </div>
@else
            <div class="panel panel-danger panel-heading">
                <a href="{{ Strangler::url('invoices/status/overdue') }}" class="text-danger"><i class="fa fa-external-link"></i> {!! __('ip.overdue_invoices') !!}</a>
                <span class="pull-right text-danger">
                    {!! format_currency($overdueTotal) !!}
                </span>
            </div>
@endif

        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 col-md-6 col-md-offset-6">

            <div id="panel-recent-invoices" class="panel panel-default">

                <div class="panel-heading">
                    <b><i class="fa fa-history fa-margin"></i> {!! __('ip.recent_invoices') !!}</b>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped table-condensed no-margin">
                        <thead>
                        <tr>
                            <th>{!! __('ip.status') !!}</th>
                            <th style="min-width: 15%;">{!! __('ip.due_date') !!}</th>
                            <th style="min-width: 15%;">{!! __('ip.invoice') !!}</th>
                            <th style="min-width: 35%;">{!! __('ip.client') !!}</th>
                            <th class="amount">{!! __('ip.balance') !!}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>

@foreach ($invoices as $invoice)
                            @php($isReadOnly = config('ledgerlift.disable_read_only') ? 0 : $invoice->is_read_only)
                            <tr>
                                <td>
                                    <span class="label {{ $invoiceStatuses[$invoice->invoice_status_id]['class'] ?? '' }}">
                                        {!! $invoiceStatuses[$invoice->invoice_status_id]['label'] ?? '' !!}
                                        @if ($invoice->invoice_sign == '-1')&nbsp;<i class="fa fa-credit-invoice" title="{!! __('ip.credit_invoice') !!}"></i>@endif
                                        @if ($isReadOnly)&nbsp;<i class="fa fa-read-only" title="{!! __('ip.read_only') !!}"></i>@endif
                                        @if ($invoice->invoice_is_recurring)&nbsp;<i class="fa fa-refresh" title="{!! __('ip.recurring') !!}"></i>@endif
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ $invoice->is_overdue ? 'font-overdue' : '' }}">
                                        {{ date_from_mysql($invoice->invoice_date_due) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ Strangler::url('invoices/view/'.$invoice->invoice_id) }}">{{ $invoice->invoice_number ?: $invoice->invoice_id }}</a>
                                </td>
                                <td>
                                    <a href="{{ Strangler::url('clients/view/'.$invoice->client_id) }}">{{ format_client($invoice) }}</a>
                                </td>
                                <td class="amount">
                                    {!! format_currency((float) $invoice->invoice_balance * (int) $invoice->invoice_sign) !!}
                                </td>
                                <td style="text-align: center;">
                                    {{-- PDFs are not ported: the legacy app generates them. --}}
@if ($invoice->sumex_id != null)
                                    <a href="{{ Strangler::legacy('invoices/generate_sumex_pdf/'.$invoice->invoice_id) }}"
                                       target="_blank" title="{!! __('ip.generate_sumex') !!}">
                                        <i class="fa fa-file-pdf-o"></i>
                                    </a>
@else
                                    <a href="{{ Strangler::legacy('invoices/generate_pdf/'.$invoice->invoice_id) }}"
                                       target="_blank" title="{!! __('ip.download_pdf') !!}">
                                        <i class="fa fa-file-pdf-o"></i>
                                    </a>
@endif
                                </td>
                            </tr>
@endforeach
                        <tr>
                            <td colspan="6" class="text-right small">
                                <a href="{{ Strangler::url('invoices/status/all') }}">{!! __('ip.view_all') !!}</a>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection
