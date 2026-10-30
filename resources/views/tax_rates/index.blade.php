@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.tax_rates') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('tax_rates/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('tax_rates/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.tax_rate_name') !!}</th>
                <th>{!! __('ip.tax_rate_percent') !!}</th>
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->tax_rate_name }}</td>
                    <td>{{ format_amount($row->tax_rate_percent) }}%</td>
                    <td>
                        @include('layout.row_options', ['module' => 'tax_rates', 'id' => $row->tax_rate_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>

</div>
@endsection
