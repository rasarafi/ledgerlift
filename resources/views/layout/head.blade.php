@use('App\Support\Strangler')
<title>{{ get_setting('custom_title', 'ledgerlift') }}</title>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="NOINDEX,NOFOLLOW">
<meta name="csrf_token_name" content="_ip_csrf">
<meta name="csrf_token_value" content="{{ csrf_token() }}">
<meta name="legacy_calculation" content="1">

<link rel="icon" href="{{ core_asset('img/favicon.png') }}" type="image/png">

<link rel="stylesheet" href="{{ theme_asset('css/style.css') }}" type="text/css">
<link rel="stylesheet" href="{{ core_asset('css/custom.css') }}" type="text/css">

@if (get_setting('monospace_amounts') == 1)
    <link rel="stylesheet" href="{{ theme_asset('css/monospace.css') }}" type="text/css">
@endif

<!--[if lt IE 9]>
<script src="{{ core_asset('js/legacy.min.js') }}"></script>
<![endif]-->

<script src="{{ core_asset('js/dependencies.min.js') }}"></script>
@if (__('ip.cldr') != 'en')
    <script src="{{ core_asset('js/locales/select2/'.__('ip.cldr').'.js') }}"></script>
@endif

<script>
    Dropzone.autoDiscover = false;

    @if (__('ip.cldr') != 'en')
    $.fn.select2.defaults.set('language', '{{ __('ip.cldr') }}');
    @endif

    $(function () {
        $('.nav-tabs').tab();
        $('.tip').tooltip();

        $('body').on('focus', '.datepicker', function () {
            $(this).datepicker({
                autoclose: true,
                format: '{{ app(App\Support\DateFormat::class)->datepicker() }}',
                language: '{{ __('ip.cldr') }}',
                weekStart: '{{ get_setting('first_day_of_week') }}',
                todayHighlight: true,
                todayBtn: 'linked'
            });
        });

        $(document).on('click', '.create-invoice', function () {
            $('#modal-placeholder').load("{{ Strangler::url('invoices/ajax/modal_create_invoice') }}");
        });

        $(document).on('click', '#btn_copy_invoice', function () {
            var invoice_id = $(this).data('invoice-id');
            var client_id = $(this).data('client-id');
            $('#modal-placeholder').load("{{ Strangler::url('invoices/ajax/modal_copy_invoice') }}", {
                invoice_id: invoice_id,
                client_id: client_id
            });
        });

        $(document).on('click', '#btn_create_credit', function () {
            var invoice_id = $(this).data('invoice-id');
            $('#modal-placeholder').load("{{ Strangler::url('invoices/ajax/modal_create_credit') }}", {invoice_id: invoice_id});
        });

        $(document).on('click', '.client-create-invoice', function () {
            var client_id = $(this).data('client-id');
            $('#modal-placeholder').load("{{ Strangler::url('invoices/ajax/modal_create_invoice') }}", {client_id: client_id});
        });

        $(document).on('click', '.invoice-add-payment', function () {
            var invoice_id = $(this).data('invoice-id');
            var invoice_balance = $(this).data('invoice-balance');
            var invoice_payment_method = $(this).data('invoice-payment-method');
            var payment_cf_exist =  $(this).data('payment-cf-exist');
            $('#modal-placeholder').load("{{ Strangler::url('payments/ajax/modal_add_payment') }}", {
                invoice_id: invoice_id,
                invoice_balance: invoice_balance,
                invoice_payment_method: invoice_payment_method,
                payment_cf_exist: payment_cf_exist
            });
        });

    });
</script>
