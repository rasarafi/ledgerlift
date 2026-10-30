@use('App\Support\Strangler')
{{-- einvoicing is not ported: the e-invoicing columns of legacy's table never show. --}}
<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead>
        <tr>
            <th>{!! __('ip.active') !!}</th>
            <th>{!! __('ip.client_name') !!}</th>
            <th>{!! __('ip.email_address') !!}</th>
            <th>{!! __('ip.phone_number') !!}</th>
            <th class="amount last">{!! __('ip.balance') !!}</th>
            <th>{!! __('ip.options') !!}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $client)
            <tr>
                <td>
                    @if ($client->client_active)<span class="label active">{!! __('ip.yes') !!}</span>@else<span class="label inactive">{!! __('ip.no') !!}</span>@endif
                </td>
                <td><a href="{{ url('clients/view/'.$client->client_id) }}">{{ format_client($client) }}</a></td>
                <td>{{ $client->client_email }}</td>
                <td>{{ $client->client_phone ?: ($client->client_mobile ?: '') }}</td>
                <td class="amount last">{!! format_currency($client->client_invoice_balance) !!}</td>
                <td>
                    <div class="options btn-group">
                        <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-cog"></i> {!! __('ip.options') !!}
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a href="{{ url('clients/view/'.$client->client_id) }}">
                                    <i class="fa fa-eye fa-margin"></i> {!! __('ip.view') !!}
                                </a>
                            </li>
                            <li>
                                <a href="{{ url('clients/form/'.$client->client_id) }}">
                                    <i class="fa fa-edit fa-margin"></i> {!! __('ip.edit') !!}
                                </a>
                            </li>
                            <li>
                                {{-- Quotes are not ported: the client opens in the legacy app, which creates them. --}}
                                <a href="{{ Strangler::legacy('clients/view/'.$client->client_id) }}">
                                    <i class="fa fa-file fa-margin"></i> {!! __('ip.create_quote') !!}
                                </a>
                            </li>
                            <li>
                                <a href="#" class="client-create-invoice"
                                   data-client-id="{{ $client->client_id }}">
                                    <i class="fa fa-file-text fa-margin"></i> {!! __('ip.create_invoice') !!}
                                </a>
                            </li>
                            <li>
                                <form action="{{ url('clients/delete/'.$client->client_id) }}"
                                      method="POST">
                                    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                                    <button type="submit" class="dropdown-button"
                                            onclick="return confirm('{!! __('ip.delete_client_warning') !!}');">
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
