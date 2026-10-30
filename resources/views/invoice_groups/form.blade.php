@extends('layout.app')

@section('content')
<form method="post">

    <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">

    <div id="headerbar">
        <h1 class="headerbar-title">{!! __('ip.invoice_group_form') !!}</h1>
        @include('layout.header_buttons')
    </div>

    <div id="content">

        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-offset-3">

                @include('layout.alerts')

                <div class="form-group">
                    <label class="control-label" for="invoice_group_name">
                        {!! __('ip.name') !!}
                    </label>
                    <input type="text" name="invoice_group_name" id="invoice_group_name" class="form-control"
                           value="{{ $form['invoice_group_name'] ?? '' }}" required>
                </div>

                <div class="form-group">
                    <label class="control-label" for="invoice_group_identifier_format">
                        {!! __('ip.identifier_format') !!}
                    </label>
                    <input type="text" class="form-control taggable"
                           name="invoice_group_identifier_format" id="invoice_group_identifier_format"
                           value="{{ $form['invoice_group_identifier_format'] ?? '' }}"
                           placeholder="INV-{{'{{{id}}}'}}" required>
                </div>

                <div class="form-group">
                    <label class="control-label" for="invoice_group_next_id">
                        {!! __('ip.next_id') !!}
                    </label>
                    <input type="number" name="invoice_group_next_id" id="invoice_group_next_id" class="form-control"
                           value="{{ $form['invoice_group_next_id'] ?? '' }}" required>
                </div>

                <div class="form-group">
                    <label class="control-label" for="invoice_group_left_pad">
                        {!! __('ip.left_pad') !!}
                    </label>
                    <input type="number" name="invoice_group_left_pad" id="invoice_group_left_pad" class="form-control"
                           value="{{ $form['invoice_group_left_pad'] ?? '' }}" required>
                </div>

                <hr>

                <div class="form-group no-margin">

                    <label for="tags_client">{!! __('ip.identifier_format_template_tags') !!}</label>

                    <p class="small">{!! __('ip.identifier_format_template_tags_instructions') !!}</p>

                    <select id="tags_client" class="tag-select form-control">
                        <option value="{{'{{{id}}}'}}">
                            {!! __('ip.id') !!}
                        </option>
                        <option value="{{'{{{year}}}'}}">
                            {!! __('ip.current_year') !!}
                        </option>
                        <option value="{{'{{{yy}}}'}}">
                            {!! __('ip.current_yy') !!}
                        </option>
                        <option value="{{'{{{month}}}'}}">
                            {!! __('ip.current_month') !!}
                        </option>
                        <option value="{{'{{{day}}}'}}">
                            {!! __('ip.current_day') !!}
                        </option>
                    </select>

                </div>

            </div>
        </div>

    </div>

</form>
@endsection
