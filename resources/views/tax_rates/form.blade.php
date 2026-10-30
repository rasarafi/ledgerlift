@extends('layout.app')

@section('content')
<form method="post" class="form-horizontal">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.tax_rate_form') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        @include('layout.alerts')

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label class="control-label">
                    {!! __('ip.tax_rate_name') !!}
                </label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <input type="text" name="tax_rate_name" id="tax_rate_name" class="form-control"
                       value="{{ $form['tax_rate_name'] ?? '' }}" required>
            </div>
        </div>

        <div class="form-group has-feedback">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label class="control-label">
                    {!! __('ip.tax_rate_percent') !!}
                </label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <input type="text" name="tax_rate_percent" id="tax_rate_percent" class="form-control"
                       value="{{ format_amount($form['tax_rate_percent'] ?? '') }}" required>
                <span class="form-control-feedback">%</span>
            </div>
        </div>

    </div>

</form>
@endsection
