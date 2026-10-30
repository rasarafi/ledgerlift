@extends('layout.app')

@section('content')
<form method="post">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.add_unit') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-offset-3">

                @include('layout.alerts')

                <input class="hidden" name="is_update" type="hidden" value="{{ ! empty($form['is_update']) ? '1' : '0' }}">

                <div class="form-group">
                    <label for="unit_name">
                        {!! __('ip.unit_name') !!}
                    </label>
                    <input type="text" name="unit_name" id="unit_name" class="form-control"
                           value="{{ $form['unit_name'] ?? '' }}" required>
                </div>

                <div class="form-group">
                    <label for="unit_name_plrl">
                        {!! __('ip.unit_name_plrl') !!}
                    </label>
                    <input type="text" name="unit_name_plrl" id="unit_name_plrl" class="form-control"
                           value="{{ $form['unit_name_plrl'] ?? '' }}" required>
                </div>

            </div>
        </div>

    </div>

</form>
@endsection
