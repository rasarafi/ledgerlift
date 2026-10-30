@use('App\Support\Strangler')
{{-- Read-only list of invoices ($invoices, $invoiceStatuses): the client view's tab; the invoice list reuses it. --}}
@php
    $invoiceCount = count($invoices);
    $invoiceListSplit = $invoiceCount > 3 ? $invoiceCount / 2 : 9999;
@endphp
<div class="table-responsive">
    <table class="table table-hover table-striped">

        <thead>
        <tr>
            <th>{!! __('ip.status') !!}</th>
            <th>{!! __('ip.invoice') !!}</th>
            <th>{!! __('ip.created') !!}</th>
            <th>{!! __('ip.due_date') !!}</th>
            <th>{!! __('ip.client_name') !!}</th>
            <th class="amount">{!! __('ip.amount') !!}</th>
            <th class="amount last">{!! __('ip.balance') !!}</th>
            <th>{!! __('ip.options') !!}</th>
        </tr>
        </thead>

        <tbody>
        @foreach ($invoices as $invoice)
            @php
                // Disable read-only if not applicable
                $isReadOnly = config('ledgerlift.disable_read_only') ? 0 : $invoice->is_read_only;
                // Convert the dropdown menu to a dropup if invoice is after the invoice split
                $dropup = $loop->iteration > $invoiceListSplit;
            @endphp
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
                    <a href="{{ Strangler::url('invoices/view/'.$invoice->invoice_id) }}"
                       title="{!! __('ip.edit') !!}">
                        {{ $invoice->invoice_number ?: $invoice->invoice_id }}
                    </a>
                </td>

                <td>
                    {{ date_from_mysql($invoice->invoice_date_created) }}
                </td>

                <td>
                    <span class="{{ $invoice->is_overdue ? 'font-overdue' : '' }}">
                        {{ date_from_mysql($invoice->invoice_date_due) }}
                    </span>
                </td>

                <td>
                    <a href="{{ url('clients/view/'.$invoice->client_id) }}"
                       title="{!! __('ip.view_client') !!}">
                        {{ format_client($invoice) }}
                    </a>
                </td>

                <td class="amount {{ $invoice->invoice_sign == '-1' ? 'text-danger' : '' }}">
                    {!! format_currency($invoice->invoice_total) !!}
                </td>

                <td class="amount last">
                    {!! format_currency($invoice->invoice_balance) !!}
                </td>

                <td>
                    <div class="options btn-group{{ $dropup ? ' dropup' : '' }}">
                        <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-cog"></i> {!! __('ip.options') !!}
                        </a>
                        <ul class="dropdown-menu">
                            @if ($isReadOnly != 1)
                                <li>
                                    <a href="{{ Strangler::url('invoices/view/'.$invoice->invoice_id) }}">
                                        <i class="fa fa-edit fa-margin"></i> {!! __('ip.edit') !!}
                                    </a>
                                </li>
                            @endif
                            <li>
                                <a href="{{ Strangler::url('invoices/generate_pdf/'.$invoice->invoice_id) }}?_ip_csrf={{ rawurlencode(csrf_token()) }}"
                                   target="_blank">
                                    <i class="fa fa-print fa-margin"></i> {!! __('ip.download_pdf') !!}
                                </a>
                            </li>
                            <li>
                                <a href="{{ Strangler::url('mailer/invoice/'.$invoice->invoice_id) }}">
                                    <i class="fa fa-send fa-margin"></i> {!! __('ip.send_email') !!}
                                </a>
                            </li>
                            <li>
                                <a href="#" class="invoice-add-payment"
                                   data-invoice-id="{{ $invoice->invoice_id }}"
                                   data-invoice-balance="{{ $invoice->invoice_balance }}"
                                   data-invoice-payment-method="{{ $invoice->payment_method }}">
                                    <i class="fa fa-money fa-margin"></i>
                                    {!! __('ip.enter_payment') !!}
                                </a>
                            </li>
                            @if ($invoice->invoice_status_id == 1 || (config('ledgerlift.enable_invoice_deletion') === true && $isReadOnly != 1))
                                <li>
                                    <form action="{{ url('invoices/delete/'.$invoice->invoice_id) }}"
                                          method="POST">
                                        <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                                        <button type="submit" class="dropdown-button"
                                                onclick="return confirm('{!! __('ip.delete_invoice_warning') !!}');">
                                            <i class="fa fa-trash-o fa-margin"></i> {!! __('ip.delete') !!}
                                        </button>
                                    </form>
                                </li>
                            @endif
                        </ul>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>

    </table>
</div>
