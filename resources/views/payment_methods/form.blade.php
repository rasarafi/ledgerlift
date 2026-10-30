@extends('layout.app')

@section('content')
<form method="post" class="form-horizontal">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.payment_method_form') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        @include('layout.alerts')

        <input class="hidden" name="is_update" type="hidden" value="{{ ! empty($form['is_update']) ? '1' : '0' }}">

        <div class="form-group">
            <div class="col-xs-12 col-sm-2 text-right text-left-xs">
                <label for="payment_method_name" class="control-label">
                    {!! __('ip.payment_method') !!}:
                </label>
            </div>
            <div class="col-xs-12 col-sm-6">
                <input type="text" name="payment_method_name" id="payment_method_name" class="form-control"
                       value="{{ $form['payment_method_name'] ?? '' }}" required>
            </div>
        </div>

    </div>

</form>
@endsection
