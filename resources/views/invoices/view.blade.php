@extends('layout.app')

@use('App\Support\LegacyHtml')
@use('App\Support\Strangler')
@section('content')
@php
    // views/invoices/view.php, LEGACY_CALCULATION=true. Not ported: custom fields, e-invoicing (its
    // `einvoicing` setting stays unset), the sumex view, the responsive item list and attachments.
    if (config('ledgerlift.disable_read_only') == true) {
        $invoice->is_read_only = 0;
    }
    $myClass = $itsMine ? 'success' : 'warning';
    $editUserTitle = __('ip.edit').' '.__('ip.user').' ('.__('ip.invoicing').'): '.PHP_EOL.e(format_user($invoiceUser));
    $isDraft = $invoice->invoice_status_id == 1 && ! $invoice->creditinvoice_parent_id;
    $canSave = $invoice->is_read_only != 1 || $invoice->invoice_status_id != 4;
    $readOnlyPaid = $invoice->is_read_only == 1 && $invoice->invoice_status_id == 4;
    $disabled = $invoice->is_read_only ? 'disabled="disabled"' : '';
@endphp

<script>
    $(function () {
        $('.item-task-id').each(function () {
            // Disable client change if at least one item already has a task id assigned
            if ($(this).val().length > 0) {
                $('#invoice_change_client').hide();
                return false;
             }
        });

        $('.btn_add_product').click(function () {
            $('#modal-placeholder').load("{{ url('products/ajax/modal_product_lookups') }}/" + Math.floor(Math.random() * 1000));
        });

        $('.btn_add_task').click(function () {
            $('#modal-placeholder').load("{{ Strangler::url('tasks/ajax/modal_task_lookups/'.$invoiceId) }}/" + Math.floor(Math.random() * 1000));
        });

        $('.btn_add_row').click(function () {
            $('#new_row').clone().appendTo('#item_table').removeAttr('id').addClass('item').show();
            // Legacy:no: check items tax usage is correct (ReLoad on change)
            check_items_tax_usages();
        });

@if ($items->isEmpty())
        $('#new_row').clone().appendTo('#item_table').removeAttr('id').addClass('item').show();
@endif

        // Legacy:no: check items tax usage is correct (Load on change)
        $(document).on('loaded', check_items_tax_usages());

        $('#btn_create_recurring').click(function () {
            $('#modal-placeholder').load("{{ url('invoices/ajax/modal_create_recurring') }}", {
                invoice_id: {{ $invoiceId }}
            });
        });
@if ($isDraft)

        $('#invoice_change_client').click(function () {
            $('#modal-placeholder').load("{{ url('invoices/ajax/modal_change_client') }}", {
                invoice_id: {{ $invoiceId }},
                client_id: "{{ $invoice->client_id }}",
            });
        });

        $('#invoice_change_user').click(function () {
            $('#modal-placeholder').load("{{ url('invoices/ajax/modal_change_user') }}", {
                invoice_id: {{ $invoiceId }},
                user_id: "{{ $invoice->user_id }}",
            });
        });
@endif

        $('#btn_save_invoice').click(function () {
            var items = [];
            var item_order = 1;
            $('#item_table .item').each(function () {
                var row = {};
                $(this).find('input,select,textarea').each(function () {
                    if ($(this).is(':checkbox')) {
                        row[$(this).attr('name')] = $(this).is(':checked');
                    } else {
                        row[$(this).attr('name')] = $(this).val();
                    }
                });
                row['item_order'] = item_order;
                item_order++;
                items.push(row);
            });
            $.post("{{ url('invoices/ajax/save') }}", {
                    legacy_calculation: 1,
                    invoice_id: {{ $invoiceId }},
                    invoice_number: $('#invoice_number').val(),
                    invoice_date_created: $('#invoice_date_created').val(),
                    invoice_date_due: $('#invoice_date_due').val(),
                    invoice_status_id: $('#invoice_status_id').val(),
                    invoice_password: $('#invoice_password').val(),
                    items: JSON.stringify(items),
                    invoice_discount_amount: $('#invoice_discount_amount').val(),
                    invoice_discount_percent: $('#invoice_discount_percent').val(),
                    invoice_terms: $('#invoice_terms').val(),
                    custom: $('input[name^=custom],select[name^=custom]').serializeArray(),
                    payment_method: $('#payment_method').val(),
                },
                function (data) {
                    var response = json_parse(data, {{ (int) config('app.debug') }});
                    if (response.success === 1) {
                        window.location = "{{ url('invoices/view') }}/" + {{ $invoiceId }};
                    } else {
                        $('#fullpage-loader').hide();
                        $('.control-group').removeClass('has-error');
                        $('div.alert[class*="alert-"]').remove();
                        var resp_errors = response.validation_errors,
                            all_resp_errors = '';
                        for (var key in resp_errors) {
                            $('#' + key).parent().addClass('has-error');
                            all_resp_errors += resp_errors[key];
                        }
                        $('#invoice_form').prepend('<div class="alert alert-danger">' + all_resp_errors + '</div>');
                    }
                });
        });

        $('#btn_generate_pdf').click(function () {
            window.open('{{ Strangler::legacy('invoices/generate_pdf/'.$invoiceId) }}?_ip_csrf={{ rawurlencode(csrf_token()) }}', '_blank');
        });

        $(document).on('click', '.btn_delete_item', function () {
            var btn = $(this);
            var item_id = btn.data('item-id');

            // Just remove the row if no item ID is set (new row)
            if (typeof item_id === 'undefined') {
                $(this).parents('.item').remove();
                check_items_tax_usages();
            } else {
                $.post("{{ url('invoices/ajax/delete_item/'.$invoice->invoice_id) }}", {
                        'item_id': item_id,
                    },
                    function (data) {
                        var response = json_parse(data, {{ (int) config('app.debug') }});
                        if (response.success === 1) {
                            btn.parents('.item').remove();
                        } else {
                            btn.removeClass('btn-link').addClass('btn-danger').prop('disabled', true);
                        }

                        check_items_tax_usages();
                    }
                );
            }
        });

@if ($invoice->is_read_only != 1)
            var fixHelper = function (e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            };

            $('#item_table').sortable({
                items: 'tbody',
                helper: fixHelper,
            });

        if ($('#invoice_discount_percent').val().length > 0) {
            $('#invoice_discount_amount').prop('disabled', true);
        }

        if ($('#invoice_discount_amount').val().length > 0) {
            $('#invoice_discount_percent').prop('disabled', true);
        }

        $('#invoice_discount_amount').keyup(function () {
            if (this.value.length > 0) {
                $('#invoice_discount_percent').prop('disabled', true);
            } else {
                $('#invoice_discount_percent').prop('disabled', false);
            }
        });
        $('#invoice_discount_percent').keyup(function () {
            if (this.value.length > 0) {
                $('#invoice_discount_amount').prop('disabled', true);
            } else {
                $('#invoice_discount_amount').prop('disabled', false);
            }
        });
@endif

@if ($invoice->invoice_is_recurring)
        $(document).on('click', '.js-item-recurrence-toggler', function () {
            var itemRecurrenceState = $(this).next('input').val();
            if (itemRecurrenceState === ('1')) {
                $(this).next('input').val('0');
                $(this).removeClass('fa-calendar-check-o text-success');
                $(this).addClass('fa-calendar-o text-muted');
            } else {
                $(this).next('input').val('1');
                $(this).removeClass('fa-calendar-o text-muted');
                $(this).addClass('fa-calendar-check-o text-success');
            }
        });
@endif

    });
</script>

@include('invoices.modal_delete_invoice')
@include('invoices.modal_add_invoice_tax')
<div id="headerbar">
    <h1 class="headerbar-title">
        <span data-toggle="tooltip" data-placement="bottom" title="{!! __('ip.invoicing') !!}: {{ PHP_EOL.format_user($invoiceUser) }}">
            {!! __('ip.invoice').' '.($invoice->invoice_number ? '#'.e($invoice->invoice_number) : __('ip.id').': '.$invoice->invoice_id) !!}
        </span>
@if ($changeUser)
        <a data-toggle="tooltip" data-placement="bottom"
           title="{!! $editUserTitle !!}"
           href="{{ Strangler::url('users/form/'.$invoice->user_id) }}">
            <i class="fa fa-xs fa-user text-{{ $myClass }}"></i>
                <span class="hidden-xs">{{ $invoice->user_name }}</span>
        </a>
@if ($isDraft)

        <span id="invoice_change_user" class="fa fa-fw fa-edit text-{{ $itsMine ? 'muted' : 'danger' }} cursor-pointer"
              data-toggle="tooltip" data-placement="bottom"
              title="{!! __('ip.change_user') !!}"></span>
@endif
@endif
    </h1>

    <div class="headerbar-item pull-right{{ $canSave ? ' btn-group' : '' }}">

        <div class="options btn-group btn-group-sm">
            <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">
                <i class="fa fa-caret-down no-margin"></i> {!! __('ip.options') !!}
            </a>
            <ul class="dropdown-menu">
@if ($invoice->is_read_only != 1)
                <li>
                    <a href="#add-invoice-tax" data-toggle="modal">
                        <i class="fa fa-plus fa-margin"></i> {!! __('ip.add_invoice_tax') !!}
                    </a>
                </li>
@endif
                <li>
                    <a href="#" id="btn_create_credit"
                       data-invoice-id="{{ $invoiceId }}">
                        <i class="fa fa-minus fa-margin"></i> {!! __('ip.create_credit_invoice') !!}
                    </a>
                </li>
@if ($invoice->invoice_balance != 0)
                <li>
                    <a href="#" class="invoice-add-payment"
                       data-invoice-id="{{ $invoiceId }}"
                       data-invoice-balance="{{ $invoice->invoice_balance }}"
                       data-invoice-payment-method="{{ $invoice->payment_method }}"
                       data-payment-cf-exist="{{ $paymentCfExist }}">
                        <i class="fa fa-credit-card fa-margin"></i>
                        {!! __('ip.enter_payment') !!}
                    </a>
                </li>
@endif
                <li>
                    <a href="#" id="btn_generate_pdf"
                       data-invoice-id="{{ $invoiceId }}">
                        <i class="fa fa-print fa-margin"></i>
                        {!! __('ip.download_pdf') !!}
                    </a>
                </li>
                <li>
                    <a href="{{ Strangler::url('mailer/invoice/'.$invoice->invoice_id) }}">
                        <i class="fa fa-send fa-margin"></i>
                        {!! __('ip.send_email') !!}
                    </a>
                </li>
                <li class="divider"></li>
                <li>
                    <a href="#" id="btn_create_recurring"
                       data-invoice-id="{{ $invoiceId }}">
                        <i class="fa fa-refresh fa-margin"></i>
                        {!! __('ip.create_recurring') !!}
                    </a>
                </li>
                <li>
                    <a href="#" id="btn_copy_invoice"
                       data-invoice-id="{{ $invoiceId }}"
                       data-client-id="{{ $invoice->client_id }}">
                        <i class="fa fa-copy fa-margin"></i>
                        {!! __('ip.copy_invoice') !!}
                    </a>
                </li>
@if ($invoice->invoice_status_id == 1 || (config('ledgerlift.enable_invoice_deletion') === true && $invoice->is_read_only != 1))
                <li>
                    <a href="#delete-invoice" data-toggle="modal">
                        <i class="fa fa-trash-o fa-margin"></i>
                        {!! __('ip.delete') !!}
                    </a>
                </li>
@endif
            </ul>
        </div>

@if ($canSave)
        <a href="#" class="btn btn-sm btn-success ajax-loader" id="btn_save_invoice">
            <i class="fa fa-check"></i> {!! __('ip.save') !!}
        </a>
@endif
    </div>

    <div class="headerbar-item invoice-labels pull-right">
@if ($invoice->invoice_is_recurring)
        <span class="label label-info">
            <i class="fa fa-refresh"></i> {!! __('ip.recurring') !!}
        </span>
@endif
@if ($invoice->is_read_only == 1)
        <span class="label label-danger">
            <i class="fa fa-read-only"></i> {!! __('ip.read_only') !!}
        </span>
@endif
    </div>

</div>

<div id="content">

    @include('layout.alerts')

    <div id="invoice_form">
        <div class="invoice">

            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-5">

                    <h2>
                        <a href="{{ url('clients/view/'.$invoice->client_id) }}">{{ format_client($invoice) }}</a>
@if ($isDraft)
                        <span id="invoice_change_client" class="fa fa-edit cursor-pointer small"
                              data-toggle="tooltip" data-placement="bottom"
                              title="{!! __('ip.change_client') !!}"></span>
@endif
                    </h2>
                    <br>
                    <div class="client-address">
                        @include('clients.partial_client_address', ['client' => $invoice])
                    </div>
@if ($invoice->client_phone || $invoice->client_email)
                    <hr>
@endif
@if ($invoice->client_phone)
                    <div>{!! __('ip.phone') !!}:&nbsp;{{ $invoice->client_phone }}</div>
@endif
@if ($invoice->client_email)
                    <div>{!! __('ip.email') !!}:&nbsp;{!! LegacyHtml::autoLink(e($invoice->client_email)) !!}</div>
@endif

                </div>

                <div class="col-xs-12 visible-xs"><br></div>

                <div class="col-xs-12 col-sm-5 col-sm-offset-1 col-md-6 col-md-offset-1">
                    <div class="details-box panel panel-default panel-body">
                        <div class="row">
@if ($invoice->invoice_sign == -1)
                            <div class="col-xs-12">
                                <div class="alert alert-warning small">
                                    <i class="fa fa-credit-invoice"></i>&nbsp;<a href="{{ url('invoices/view/'.$invoice->creditinvoice_parent_id) }}">{!! __('ip.credit_invoice_for_invoice').' '.e($parentInvoiceNumber) !!}</a>
                                </div>
                            </div>
@endif

                            <div class="col-xs-12 col-md-6">

                                <div class="invoice-properties">
                                    <label for="invoice_number">{!! __('ip.invoice') !!} #</label>
                                    <input type="text" id="invoice_number" class="form-control"
@if ($invoice->invoice_number)
                                           value="{{ $invoice->invoice_number }}"
@else
                                           placeholder="{!! __('ip.not_set') !!}"
@endif
                                           {!! $disabled !!}
                                    >

                                </div>

                                <div class="invoice-properties has-feedback">
                                    <label>{!! __('ip.date') !!}</label>

                                    <div class="input-group">
                                        <input name="invoice_date_created" id="invoice_date_created"
                                               class="form-control datepicker"
                                               value="{{ date_from_mysql($invoice->invoice_date_created) }}"
                                               {!! $disabled !!}>
                                        <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                                    </div>
                                </div>

                                <div class="invoice-properties has-feedback">
                                    <label>{!! __('ip.due_date') !!}</label>

                                    <div class="input-group">
                                        <input name="invoice_date_due" id="invoice_date_due"
                                               class="form-control datepicker"
                                               value="{{ date_from_mysql($invoice->invoice_date_due) }}"
                                               {!! $disabled !!}>
                                        <span class="input-group-addon"><i class="fa fa-calendar fa-fw"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xs-12 col-md-6">

                                <div class="invoice-properties">
                                    <label>
                                        {!! __('ip.status') !!}{!! $canSave ? ' <span class="small">('.__('ip.can_be_changed').')</span>' : '' !!}
                                    </label>
                                    <select name="invoice_status_id" id="invoice_status_id"
                                            class="form-control simple-select" data-minimum-results-for-search="Infinity"
                                            {!! $readOnlyPaid ? 'disabled="disabled"' : '' !!}
                                    >
@foreach ($invoiceStatuses as $key => $invoiceStatus)
                                        <option value="{{ $key }}"{!! $key == $invoice->invoice_status_id ? ' selected="selected"' : '' !!}>
                                            {!! $invoiceStatus['label'] !!}
                                        </option>
@endforeach
                                    </select>
                                </div>

                                <div class="invoice-properties">
                                    <label>{!! __('ip.payment_method') !!}</label>
                                    <select name="payment_method" id="payment_method"
                                            class="form-control simple-select"
                                            {!! $readOnlyPaid ? 'disabled="disabled"' : '' !!}
                                    >
                                        <option value="0">{!! __('ip.select_payment_method') !!}</option>
@foreach ($paymentMethods as $paymentMethod)
                                        <option {!! $invoice->payment_method == $paymentMethod->payment_method_id ? 'selected="selected"' : '' !!}
                                            value="{{ $paymentMethod->payment_method_id }}">
                                            {{ $paymentMethod->payment_method_name }}
                                        </option>
@endforeach
                                    </select>
                                </div>

                                <div class="invoice-properties">
                                    <label>{!! __('ip.invoice_password') !!}</label>
                                    <input type="text" id="invoice_password" class="form-control"
                                           value="{{ $invoice->invoice_password }}"
                                           {!! $disabled !!}>
                                </div>
                            </div>

@if ($invoice->invoice_status_id != 1)
                            <div class="col-xs-12">
                                <div class="form-group">
                                    <label for="invoice-guest-url">{!! __('ip.guest_url') !!}</label>
                                    <div class="input-group">
                                        <input type="text" id="invoice-guest-url" readonly class="form-control"
                                               value="{{ Strangler::url('guest/view/invoice/'.$invoice->invoice_url_key) }}">
                                        <span class="input-group-addon to-clipboard cursor-pointer"
                                              data-clipboard-target="#invoice-guest-url">
                                            <i class="fa fa-clipboard fa-fw"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
@endif

                        </div>
                    </div>
                </div>

            </div>

            <br>

@include('invoices.partial_itemlist_table')

            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">

                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">
                            {!! __('ip.invoice_terms') !!}
                        </div>
                        <div class="panel-body">
                            <textarea id="invoice_terms" name="invoice_terms" class="form-control" rows="3"
                                      {!! $disabled !!}
                            >{{ $invoice->invoice_terms }}</textarea>
                        </div>
                    </div>

                    <div class="col-xs-12 visible-xs visible-sm"><br></div>

                </div>
                <div class="col-xs-12 col-md-6">

                    {{-- _dropzone_html(): uploads are not ported, so the panel has no "add files" button and no dropzone script. --}}
                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">{!! __('ip.attachments') !!}</div>
                        <div class="panel-body clearfix">
                            <div class="row">
                                <div id="actions" class="col-xs-12">
                                    <div id="previews" class="table table-condensed files no-margin"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
