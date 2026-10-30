@extends('layout.app')

@section('content')
<form method="post">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.products_form') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        @include('layout.alerts')

        <div class="row">
            <div class="col-xs-12 col-md-6">

                <div class="panel panel-default">
                    <div class="panel-heading">

@if (! empty($form['product_id']))
                        #{{ $form['product_id'] }}&nbsp;
                        {{ $form['product_name'] ?? '' }}
@else
                        {!! __('ip.new_product') !!}
@endif

                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="family_id">
                                {!! __('ip.family') !!}
                            </label>

                            <select name="family_id" id="family_id" class="form-control simple-select">
                                <option value="0">{!! __('ip.select_family') !!}</option>
@foreach ($families as $family)
                                <option value="{{ $family->family_id }}"
                                    {!! ($form['family_id'] ?? '') == $family->family_id ? 'selected="selected"' : '' !!}
                                >{{ $family->family_name }}</option>
@endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="product_sku">
                                {!! __('ip.product_sku') !!}
                            </label>

                            <input type="text" name="product_sku" id="product_sku" class="form-control"
                                   value="{{ $form['product_sku'] ?? '' }}">
                        </div>

                        <div class="form-group">
                            <label for="product_name">
                                {!! __('ip.product_name') !!}
                            </label>

                            <input type="text" name="product_name" id="product_name" class="form-control" required
                                   value="{{ $form['product_name'] ?? '' }}" required>
                        </div>

                        <div class="form-group">
                            <label for="product_description">
                                {!! __('ip.product_description') !!}
                            </label>

                            <textarea name="product_description" id="product_description" class="form-control"
                                      rows="3">{{ $form['product_description'] ?? '' }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="product_price">
                                {!! __('ip.product_price') !!}
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" name="product_price" id="product_price" class="form-control"
                                       value="{{ format_amount($form['product_price'] ?? '') }}" required>
                                <span class="input-group-addon">{!! get_setting('currency_symbol') !!}</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="unit_id">
                                {!! __('ip.product_unit') !!}
                            </label>

                            <select name="unit_id" id="unit_id" class="form-control simple-select">
                                <option value="0">{!! __('ip.select_unit') !!}</option>
@foreach ($units as $unit)
                                <option value="{{ $unit->unit_id }}"
                                    {!! ($form['unit_id'] ?? '') == $unit->unit_id ? 'selected="selected"' : '' !!}
                                >{{ $unit->unit_name.'/'.$unit->unit_name_plrl }}</option>
@endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tax_rate_id">
                                {!! __('ip.tax_rate') !!}
                            </label>

                            <select name="tax_rate_id" id="tax_rate_id" class="form-control simple-select">
                                <option value="0">{!! __('ip.none') !!}</option>
@foreach ($taxRates as $taxRate)
                                <option value="{{ $taxRate->tax_rate_id }}"
                                    {!! ($form['tax_rate_id'] ?? '') == $taxRate->tax_rate_id ? 'selected="selected"' : '' !!}
                                >{{ $taxRate->tax_rate_name.' ('.format_amount($taxRate->tax_rate_percent).'%)' }}</option>
@endforeach
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-xs-12 col-md-6">

                <div class="panel panel-default">
                    <div class="panel-heading">
                        {!! __('ip.extra_information') !!}
                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="provider_name">
                                {!! __('ip.provider_name') !!}
                            </label>

                            <input type="text" name="provider_name" id="provider_name" class="form-control"
                                   value="{{ $form['provider_name'] ?? '' }}">
                        </div>

                        <div class="form-group">
                            <label for="purchase_price">
                                {!! __('ip.purchase_price') !!}
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" name="purchase_price" id="purchase_price" class="form-control"
                                       value="{{ format_amount($form['purchase_price'] ?? '') }}">
                                <span class="input-group-addon">{!! get_setting('currency_symbol') !!}</span>
                            </div>
                        </div>

                    </div>
                </div>
@if (get_setting('sumex') == '1')

                <div class="panel panel-default">
                    <div class="panel-heading">
                        {!! __('ip.invoice_sumex') !!}
                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="product_tariff">
                                {!! __('ip.product_tariff') !!}
                            </label>

                            <input type="text" name="product_tariff" id="product_tariff" class="form-control"
                                   value="{{ $form['product_tariff'] ?? '' }}">
                        </div>

                    </div>
                </div>
@endif
            </div>
        </div>

    </div>

</form>
@endsection
