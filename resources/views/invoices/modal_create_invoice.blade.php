@php
    $permissive = get_setting('enable_permissive_search_clients');
@endphp
<script>
    $(function () {
        // Display the create invoice modal
        $('#create-invoice').modal('show');

        // Enable select2 for all selects
        $('.simple-select').select2();

        @include('clients.script_select2_client_id')

        // Creates the invoice
        $('#invoice_create_confirm').click(function () {
            // Posts the data to validate and create the invoice;
            // will create the new client if necessary
            $.post("{{ url('invoices/ajax/create') }}", {
                    client_id: $('#create_invoice_client_id').val(),
                    invoice_date_created: $('#invoice_date_created').val(),
                    invoice_group_id: $('#invoice_group_id').val(),
                    invoice_time_created: '{{ date('H:i:s') }}',
                    invoice_password: $('#invoice_password').val(),
                    user_id: '{{ auth()->id() }}',
                    payment_method: $('#payment_method_id').val()
                },
                function (data) {
                    var response = json_parse(data, {{ (int) config('app.debug') }});
                    if (response.success === 1) {
                        // The validation was successful and invoice was created
                        window.location = "{{ url('invoices/view') }}/" + response.invoice_id;
                    }
                    else {
                        // The validation was not successful
                        $('.control-group').removeClass('has-error');
                        for (var key in response.validation_errors) {
                            $('#' + key).parent().parent().addClass('has-error');
                        }
                    }
                });
        });
    });

</script>

<div id="create-invoice" class="modal modal-lg"
     role="dialog" aria-labelledby="modal_create_invoice" aria-hidden="true">
    <form class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><i class="fa fa-close"></i></button>
            <h4 class="panel-title">{!! __('ip.create_invoice') !!}</h4>
        </div>
        <div class="modal-body">

            <input class="hidden" id="payment_method_id"
                   value="{{ get_setting('invoice_default_payment_method') }}">
            <input class="hidden" id="input_permissive_search_clients"
                   value="{{ $permissive }}">

            <div class="form-group has-feedback">
                <label for="create_invoice_client_id">{!! __('ip.client') !!}</label>
                <div class="input-group">
                    <span id="toggle_permissive_search_clients" class="input-group-addon" title="{!! __('ip.enable_permissive_search_clients') !!}" style="cursor:pointer;">
                        <i class="fa fa-toggle-{{ $permissive ? 'on' : 'off' }} fa-fw"></i>
                    </span>
                    <select name="client_id" id="create_invoice_client_id" class="client-id-select form-control"
                            autofocus="autofocus" required>
@if (! empty($client))
                        <option value="{{ $client->client_id }}">{{ format_client($client, false) }}</option>
@endif
                    </select>
                </div>
            </div>

            <div class="form-group has-feedback">
                <label for="invoice_date_created">{!! __('ip.invoice_date') !!}</label>

                <div class="input-group">
                    <input name="invoice_date_created" id="invoice_date_created"
                           class="form-control datepicker"
                           value="{{ date((string) get_setting('date_format')) }}" required>
                    <span class="input-group-addon">
                    <i class="fa fa-calendar fa-fw"></i>
                </span>
                </div>
            </div>

            <div class="form-group">
                <label for="invoice_password">{!! __('ip.invoice_password') !!}</label>
                <input type="text" name="invoice_password" id="invoice_password" class="form-control"
                       value="{{ get_setting('invoice_pre_password') }}"
                       style="margin: 0 auto;" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="invoice_group_id">{!! __('ip.invoice_group') !!}</label>
                <select name="invoice_group_id" id="invoice_group_id"
                    class="form-control simple-select" data-minimum-results-for-search="Infinity" required>
@foreach ($invoiceGroups as $invoiceGroup)
                    <option value="{{ $invoiceGroup->invoice_group_id }}"{!! get_setting('default_invoice_group') == $invoiceGroup->invoice_group_id ? ' selected="selected"' : '' !!}>
                        {{ $invoiceGroup->invoice_group_name }}
                    </option>
@endforeach
                </select>
            </div>

        </div>

        <div class="modal-footer">
            <div class="btn-group">
                <button class="btn btn-success ajax-loader" id="invoice_create_confirm" type="button">
                    <i class="fa fa-check"></i> {!! __('ip.submit') !!}
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i> {!! __('ip.cancel') !!}
                </button>
            </div>
        </div>

    </form>

</div>
