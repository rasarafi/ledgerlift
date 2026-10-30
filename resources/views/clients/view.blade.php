@extends('layout.app')

@section('content')
<script>
    $(function () {
        const client_id = @json((int) $client->client_id);
        function add_delete_client_notes_click_event(){
            $('.delete_client_note').click(delete_client_note);
        }
        function reload_client_notes(data){
            var response = json_parse(data, {{ (int) config('app.debug') }});
            if (response.success === 1) {
                // The validation was successful
                $('.has-error').removeClass('has-error');
                $('#client_note').val('');

                // Reload all notes
                $('#notes_list').load("{{ url('clients/ajax/load_client_notes') }}",
                    {
                        client_id: client_id
                    }, function (response) {
                        {!! config('app.debug') ? 'console.log(response);' : '' !!}

                        setTimeout(add_delete_client_notes_click_event, 161);
                    });
            } else {
                // The validation was not successful
                $('.has-error').removeClass('has-error');
                for (var key in response.validation_errors) {
                    $('#' + key).parent().addClass('has-error');
                }
            }
            close_loader();
        }
        function delete_client_note(event) {
            show_loader();
            $.post('{{ url('clients/ajax/delete_client_note') }}',
                {
                    client_note_id: $(this).attr('data-id')
                }, function (data) {
                    reload_client_notes(data);
                }
            );
        }
        $('#save_client_note').click(function () {
            show_loader();
            $.post('{{ url('clients/ajax/save_client_note') }}',
                {
                    client_id: client_id,
                    client_note: $('#client_note').val()
                }, function (data) {
                    reload_client_notes(data);
                }
            );
        });
        add_delete_client_notes_click_event();
    });
</script>

<div id="headerbar">
    <h1 class="headerbar-title">{{ format_client($client) }}</h1>

    <div class="headerbar-item pull-right">
        <div class="btn-group btn-group-sm">
            <a href="#" class="btn btn-default client-create-quote"
               data-client-id="{{ $client->client_id }}">
                <i class="fa fa-file"></i> {!! __('ip.create_quote') !!}
            </a>
            <a href="#" class="btn btn-default client-create-invoice"
               data-client-id="{{ $client->client_id }}">
                <i class="fa fa-file-text"></i> {!! __('ip.create_invoice') !!}</a>
            <a href="{{ url('clients/form/'.$client->client_id) }}"
               class="btn btn-default">
                <i class="fa fa-edit"></i> {!! __('ip.edit') !!}
            </a>
            <form action="{{ url('clients/delete/'.$client->client_id) }}"
                  method="POST" class="btn-group btn-group-sm">
                <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('{!! __('ip.delete_client_warning') !!}');">
                    <i class="fa fa-trash-o"></i> {!! __('ip.delete') !!}
                </button>
            </form>
        </div>
    </div>

</div>

<ul id="submenu" class="nav nav-tabs nav-tabs-noborder">
    <li{!! $activeTab == 'detail' ? ' class="active"' : '' !!}><a data-toggle="tab" href="#client-details">{!! __('ip.details') !!}</a></li>
    {{-- Quotes are not ported: the tab is a link to the client's quotes on the legacy app. --}}
    <li><a href="{{ \App\Support\Strangler::legacy('clients/view/'.$client->client_id.'/quotes') }}">{!! __('ip.quotes') !!}</a></li>
    <li{!! $activeTab == 'invoices' ? ' class="active"' : '' !!}><a data-toggle="tab" href="#client-invoices">{!! __('ip.invoices') !!}</a></li>
    <li{!! $activeTab == 'payments' ? ' class="active"' : '' !!}><a data-toggle="tab" href="#client-payments">{!! __('ip.payments') !!}</a></li>
</ul>

<div id="content" class="tabbable tabs-below no-padding">
    <div class="tab-content no-padding">

        <div id="client-details" class="tab-pane tab-rich-content{{ $activeTab == 'detail' ? ' active' : '' }}">

            @include('layout.alerts')

            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-8">

                    <h3>{{ format_client($client) }}</h3>
                    <p>@include('clients.partial_client_address')</p>

                </div>
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">

                    <table class="table table-bordered no-margin">
                        <tr>
                            <th>{!! __('ip.language') !!}</th>
                            <td class="td-amount">{{ ucfirst((string) $client->client_language) }}</td>
                        </tr>
                        <tr>
                            <th>{!! __('ip.total_billed') !!}</th>
                            <td class="td-amount">{!! format_currency($client->client_invoice_total) !!}</td>
                        </tr>
                        <tr>
                            <th>{!! __('ip.total_paid') !!}</th>
                            <th class="td-amount">{!! format_currency($client->client_invoice_paid) !!}</th>
                        </tr>
                        <tr>
                            <th>{!! __('ip.total_balance') !!}</th>
                            <td class="td-amount">{!! format_currency($client->client_invoice_balance) !!}</td>
                        </tr>
                    </table>

                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-xs-12 col-sm-6">

                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">{!! __('ip.contact_information') !!}</div>
                        <div class="panel-body table-content">
                            <table class="table no-margin">
@if ($client->client_invoicing_contact)
                                <tr>
                                    <th>{!! __('ip.contact') !!} ({!! __('ip.invoicing') !!})</th>
                                    <td>{{ $client->client_invoicing_contact }}</td>
                                </tr>
@endif
@if ($client->client_email)
                                <tr>
                                    <th>{!! __('ip.email') !!}</th>
                                    <td>{!! \App\Support\LegacyHtml::autoLink(e($client->client_email), 'email') !!}</td>
                                </tr>
@endif
@if ($client->client_phone)
                                <tr>
                                    <th>{!! __('ip.phone') !!}</th>
                                    <td>{{ $client->client_phone }}</td>
                                </tr>
@endif
@if ($client->client_mobile)
                                <tr>
                                    <th>{!! __('ip.mobile') !!}</th>
                                    <td>{{ $client->client_mobile }}</td>
                                </tr>
@endif
@if ($client->client_fax)
                                <tr>
                                    <th>{!! __('ip.fax') !!}</th>
                                    <td>{{ $client->client_fax }}</td>
                                </tr>
@endif
@if ($client->client_web)
                                <tr>
                                    <th>{!! __('ip.web') !!}</th>
                                    <td>{!! \App\Support\LegacyHtml::autoLink(e($client->client_web), 'url', true) !!}</td>
                                </tr>
@endif
                            </table>
                        </div>
                    </div>

                </div>
                <div class="col-xs-12 col-sm-6">
                    <div class="panel panel-default no-margin">

                        <div class="panel-heading">{!! __('ip.tax_information') !!}</div>
                        <div class="panel-body table-content">
                            <table class="table no-margin">
                                <tr>
                                    <th>{!! __('ip.company') !!}</th>
                                    <td>{{ $client->client_company ? $client->client_company : '' }}</td>
                                </tr>
@if ($client->client_vat_id)
                                <tr>
                                    <th>{!! __('ip.vat_id') !!}</th>
                                    <td>{{ $client->client_vat_id }}</td>
                                </tr>
@endif
@if ($client->client_tax_code)
                                <tr>
                                    <th>{!! __('ip.tax_code') !!}</th>
                                    <td>{{ $client->client_tax_code }}</td>
                                </tr>
@endif
                            </table>
                        </div>

                    </div>
                </div>
            </div>

@if ($client->client_surname != '')
            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">

                    <div class="panel panel-default">
                        <div class="panel-heading">{!! __('ip.personal_information') !!}</div>

                        <div class="panel-body table-content">
                            <table class="table no-margin">
                                <tr>
                                    <th>{!! __('ip.birthdate') !!}</th>
                                    <td>
                                        {{ (! empty($client->client_birthdate) && $client->client_birthdate !== '0000-00-00') ? format_date($client->client_birthdate) : '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>{!! __('ip.gender') !!}</th>
                                    <td>{!! format_gender($client->client_gender) !!}</td>
                                </tr>
@if (get_setting('sumex') == '1')
                                <tr>
                                    <th>{!! __('ip.sumex_ssn') !!}</th>
                                    <td>{{ format_avs($client->client_avs) }}</td>
                                </tr>

                                <tr>
                                    <th>{!! __('ip.sumex_insurednumber') !!}</th>
                                    <td>{{ $client->client_insurednumber }}</td>
                                </tr>

                                <tr>
                                    <th>{!! __('ip.sumex_veka') !!}</th>
                                    <td>{{ $client->client_veka }}</td>
                                </tr>
@endif
                            </table>
                        </div>
                    </div>

                </div>
            </div>
@endif

            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">

                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">
                            {!! __('ip.notes') !!}
                        </div>
                        <div class="panel-body">
                            <div id="notes_list">
                                @include('clients.partial_notes')
                            </div>
                            <div class="input-group">
                                <textarea id="client_note" class="form-control" rows="2" style="resize:none"></textarea>
                                <span id="save_client_note" class="input-group-addon btn btn-default">
                                    {!! __('ip.add_note') !!}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div id="client-invoices" class="tab-pane table-content{{ $activeTab == 'invoices' ? ' active' : '' }}">
            <div class="container-fluid">
                <div class="pull-right" style="margin:.5rem 0 -1.5rem 0">
                    @include('layout.pager', ['pager' => $invoicePager, 'baseUrl' => url('clients/view/'.$client->client_id.'/invoices')])
                </div>
            </div>
            @include('invoices.partial_invoice_table')
        </div>

        <div id="client-payments" class="tab-pane table-content{{ $activeTab == 'payments' ? ' active' : '' }}">
            <div class="container-fluid">
                <div class="pull-right" style="margin:.5rem 0 -1.5rem 0">
                    @include('layout.pager', ['pager' => $paymentPager, 'baseUrl' => url('clients/view/'.$client->client_id.'/payments')])
                </div>
            </div>
            @include('payments.partial_payment_table')
        </div>
    </div>
</div>
@endsection
