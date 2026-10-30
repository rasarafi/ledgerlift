@extends('layout.app')

@section('content')
@php
    // Custom fields and e-invoicing are not ported: no custom-field rows are read and the e-invoicing panel never shows.
    $v = fn (string $key) => $form[$key] ?? '';
    $selected = fn (mixed $a, mixed $b) => $a == $b ? 'selected="selected"' : '';
    $clientActive = $v('client_active');
    $active = ($clientActive == 1 || ! is_numeric($clientActive)) ? ' checked="checked"' : '';
    $itsCompany = $v('client_company') || $v('client_vat_id');
    // Only English ships (legacy lists the folders of application/language).
    $languages = ['english'];
    $titleChoices = ['mr', 'mrs', 'doctor', 'professor', 'custom'];
@endphp
<script type="text/javascript">
    $(function () {
        $("#client_country").select2({
            placeholder: "{!! __('ip.country') !!}",
            allowClear: true
        });

const ClientTitleEnum = new Array(
    'mr',
    'mrs',
    'doctor',
    'professor'
);

$('#client_title').change((event) => {
    const client_title_custom_element = $('#client_title_custom');

    if (ClientTitleEnum.indexOf(event.target.value) == -1) {
        client_title_custom_element.removeClass('hidden')
    } else {
        client_title_custom_element.addClass('hidden');
        client_title_custom_element.val('');
    }
});

    });

</script>

<form method="post">
    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.client_form') !!}</h1>
        @include('layout.header_buttons')
    </div>
    <div id="content">

        @include('layout.alerts')

        <input class="hidden" name="is_update" type="hidden" value="{{ $v('is_update') ? '1' : '0' }}">

        <div class="row">
            <div class="col-xs-12 col-sm-6"><!-- personal -->
                <div class="panel panel-default">
                    <div class="panel-heading form-inline clearfix">
                        {!! __('ip.personal_information') !!}
                        <div class="pull-right">
                            <label for="client_active" class="control-label">
                                {!! __('ip.active_client') !!}
                                <input id="client_active" name="client_active" type="checkbox" value="1"{!! $active !!}>
                            </label>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="client_name">
                                {!! __('ip.client_name') !!}
                            </label>
                            <input id="client_name" name="client_name" type="text" class="form-control"
                                   autofocus
                                   value="{{ $v('client_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="client_surname">
                                {!! __('ip.client_surname_optional') !!}
                            </label>
                            <input id="client_surname" name="client_surname" type="text" class="form-control"
                                   value="{{ $v('client_surname') }}">
                        </div>
                        <div class="form-group">
                            <label for="client_company">{!! __('ip.client_company') !!} ({!! __($itsCompany ? 'ip.required_field' : 'ip.optional') !!})</label>

                            <div class="controls">
                                <input id="client_company" name="client_company" type="text" class="form-control"
                                       value="{{ $v('client_company') }}">
                            </div>
                        </div>
                        <div class="form-group no-margin">
                            <label for="client_language">
                                {!! __('ip.language') !!}
                            </label>
                            <select name="client_language" id="client_language" class="form-control simple-select">
                                <option value="system">
                                    {!! __('ip.use_system_language') !!}
                                </option>
@foreach ($languages as $language)
                                <option value="{{ $language }}"
                                    {!! $selected($v('client_language'), $language) !!}>
                                    {{ ucfirst($language) }}
                                </option>
@endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-xs-12 col-sm-6"><!-- Address -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        {!! __('ip.address') !!}
                    </div>

                    <div class="panel-body">
                        <div class="form-group">
                            <label for="client_address_1">{!! __('ip.street_address') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_address_1" id="client_address_1" class="form-control"
                                       value="{{ $v('client_address_1') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_address_2">{!! __('ip.street_address_2') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_address_2" id="client_address_2" class="form-control"
                                       value="{{ $v('client_address_2') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_city">{!! __('ip.city') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_city" id="client_city" class="form-control"
                                       value="{{ $v('client_city') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_state">{!! __('ip.state') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_state" id="client_state" class="form-control"
                                       value="{{ $v('client_state') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_zip">{!! __('ip.zip_code') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_zip" id="client_zip" class="form-control"
                                       value="{{ $v('client_zip') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_country">{!! __('ip.country') !!}</label>

                            <div class="controls">
                                <select name="client_country" id="client_country" class="form-control">
                                    <option value="">{!! __('ip.none') !!}</option>
                                    @foreach (__('countries') as $cldr => $country)
                                        <option value="{{ $cldr }}"
                                            {!! $selected($selectedCountry, $cldr) !!}
                                        >{!! $country !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="col-xs-12 col-sm-6"><!-- Contact -->

                <div class="panel panel-default">

                    <div class="panel-heading">
                        {!! __('ip.contact_information') !!}
                    </div>

                    <div class="panel-body">
                        <div class="form-group">
                            <label for="client_invoicing_contact">{!! __('ip.contact') !!} ({!! __('ip.invoicing') !!})</label>

                            <div class="controls">
                                <input type="text" name="client_invoicing_contact" id="client_invoicing_contact" class="form-control"
                                    value="{{ $v('client_invoicing_contact') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_phone">{!! __('ip.phone_number') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_phone" id="client_phone" class="form-control"
                                       value="{{ $v('client_phone') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_fax">{!! __('ip.fax_number') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_fax" id="client_fax" class="form-control"
                                       value="{{ $v('client_fax') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_mobile">{!! __('ip.mobile_number') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_mobile" id="client_mobile" class="form-control"
                                       value="{{ $v('client_mobile') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_email">{!! __('ip.email_address') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_email" id="client_email" class="form-control"
                                       value="{{ $v('client_email') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_web">{!! __('ip.web_address') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_web" id="client_web" class="form-control"
                                       value="{{ $v('client_web') }}">
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-xs-12 col-sm-6"><!-- Tax -->

                <div class="panel panel-default">
                    <div class="panel-heading">
                        {!! __('ip.tax_information') !!}
                    </div>

                    <div class="panel-body">
                        <div class="form-group">
                            <label for="client_vat_id">{!! __('ip.vat_id') !!} ({!! __($itsCompany ? 'ip.required_field' : 'ip.optional') !!})</label>

                            <div class="controls">
                                <input type="text" name="client_vat_id" id="client_vat_id" class="form-control"
                                       value="{{ $v('client_vat_id') }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_tax_code">{!! __('ip.tax_code') !!}</label>

                            <div class="controls">
                                <input type="text" name="client_tax_code" id="client_tax_code" class="form-control"
                                       value="{{ $v('client_tax_code') }}">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-xs-12 col-sm-6"><!-- Personal -->
                <div class="panel panel-default">

                    <div class="panel-heading">
                        {!! __('ip.personal_information') !!}
                    </div>

                    <div class="panel-body">
                        <div class="form-group">
                            <label for="client_gender">{!! __('ip.gender') !!}</label>
                            <div class="controls">
                                <select name="client_gender" id="client_gender"
                                        class="form-control simple-select" data-minimum-results-for-search="Infinity">
@foreach ([__('ip.gender_male'), __('ip.gender_female'), __('ip.gender_other')] as $key => $label)
                                    <option value=" {{ $key }}" {!! $selected($key, $v('client_gender')) !!}>
                                        {!! $label !!}
                                    </option>
@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
@php
    $clientTitle = $v('client_title');
    // ClientTitleEnum::tryFrom() finds nothing for an empty title, so a client without one counts as custom.
    $isCustomTitle = ! in_array($clientTitle, ['mr', 'mrs', 'doctor', 'professor'], true);
@endphp
                            <label for="client_title">{!! __('ip.client_title') !!}</label>
                            <select name="client_title" id="client_title" class="form-control simple-select">
@foreach ($titleChoices as $choice)
                                <option
                                    value="{{ $choice }}"
                                    {{ $clientTitle === $choice ? 'selected' : '' }}
                                    {{ $isCustomTitle && $choice === 'custom' ? 'selected' : '' }}
                                >
                                    {{ ucfirst(__('ip.'.$choice)) }}
                                </option>
@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <input
                                id="client_title_custom"
                                name="client_title_custom"
                                type="text"
                                class="form-control{{ $isCustomTitle ? '' : ' hidden' }}"
                                placeholder="{{ __('ip.custom_title') }}"
                                value="{{ $clientTitle }}"
                            >
                        </div>
                        <div class="form-group has-feedback">
                            <label for="client_birthdate">{!! __('ip.birthdate') !!}</label>
@php
    $birthdate = $v('client_birthdate');
    $birthdate = ($birthdate && $birthdate != '0000-00-00') ? date_from_mysql($birthdate) : '';
@endphp
                            <div class="input-group">
                                <input type="text" name="client_birthdate" id="client_birthdate"
                                    class="form-control datepicker"
                                    value="{{ $birthdate }}">
                                <span class="input-group-addon">
                                    <i class="fa fa-calendar fa-fw"></i>
                                </span>
                            </div>
                        </div>

@if (get_setting('sumex') == '1')
                        <div class="form-group">
                            <label for="client_avs">{!! __('ip.sumex_ssn') !!}</label>
                            <div class="controls">
                                <input type="text" name="client_avs" id="client_avs" class="form-control"
                                       value="{{ format_avs($v('client_avs')) }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_insurednumber">{!! __('ip.sumex_insurednumber') !!}</label>
                            <div class="controls">
                                <input type="text" name="client_insurednumber" id="client_insurednumber" class="form-control"
                                       value="{!! $v('client_insurednumber') == null ? '' : htmlentities($v('client_insurednumber'), ENT_COMPAT) !!}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="client_veka">{!! __('ip.sumex_veka') !!}</label>
                            <div class="controls">
                                <input type="text" name="client_veka" id="client_veka" class="form-control"
                                       value="{!! $v('client_veka') == null ? '' : htmlentities($v('client_veka'), ENT_COMPAT) !!}">
                            </div>
                        </div>
@endif
                    </div>

                </div>

            </div>
        </div>

    </div>
</form>
@endsection
