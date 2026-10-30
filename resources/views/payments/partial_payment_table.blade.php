{{-- partial_payments_table.php: the rows of $payments, for the payment list, its filter and the client view's tab. --}}
<div class="table-responsive">
    <table class="table table-hover table-striped">

        <thead>
        <tr>
            <th>{!! __('ip.payment_date') !!}</th>
            <th>{!! __('ip.invoice_date') !!}</th>
            <th>{!! __('ip.invoice') !!}</th>
            <th>{!! __('ip.client') !!}</th>
            <th class="amount last">{!! __('ip.amount') !!}</th>
            <th>{!! __('ip.payment_method') !!}</th>
            <th>{!! __('ip.note') !!}</th>
            <th>{!! __('ip.options') !!}</th>
        </tr>
        </thead>

        <tbody>
        @foreach ($payments as $payment)
            <tr>
                <td>{{ date_from_mysql($payment->payment_date) }}</td>
                <td>{{ date_from_mysql($payment->invoice_date_created) }}</td>
                <td><a href="{{ url('invoices/view/'.$payment->invoice_id) }}">{{ $payment->invoice_number }}</a></td>
                <td>
                    <a href="{{ url('clients/view/'.$payment->client_id) }}"
                       title="{!! __('ip.view_client') !!}">
                        {{ format_client($payment) }}
                    </a>
                </td>
                <td class="amount last">{!! format_currency($payment->payment_amount) !!}</td>
                <td>{{ $payment->payment_method_name }}</td>
                <td>{{ $payment->payment_note }}</td>
                <td>
                    <div class="options btn-group">
                        <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-cog"></i> {!! __('ip.options') !!}
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="{{ url('payments/form/'.$payment->payment_id) }}">
                                    <i class="fa fa-edit fa-margin"></i>
                                    {!! __('ip.edit') !!}
                                </a>
                            </li>
                            <li>
                                <form action="{{ url('payments/delete/'.$payment->payment_id) }}"
                                      method="POST">
                                    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                                    <button type="submit" class="dropdown-button"
                                            onclick="return confirm('{!! __('ip.delete_record_warning') !!}');">
                                        <i class="fa fa-trash-o fa-margin"></i> {!! __('ip.delete') !!}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>

    </table>
</div>
