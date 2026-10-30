@extends('layout.app')

@section('content')
<form method="post">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __(! empty($form['is_update']) ? 'ip.family' : 'ip.add_family') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-offset-3">

                @include('layout.alerts')

                <input class="hidden" name="is_update" type="hidden" value="{{ ! empty($form['is_update']) ? '1' : '0' }}">

                <div class="form-group">
                    <label for="family_name">
                        {!! __('ip.family_name') !!}
                    </label>
                    <input type="text" name="family_name" id="family_name" class="form-control"
                           value="{{ $form['family_name'] ?? '' }}" required>
                </div>

            </div>
        </div>

    </div>

</form>
@endsection
