@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.units') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('units/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('units/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.unit_name') !!}</th>
                <th>{!! __('ip.unit_name_plrl') !!}</th>
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->unit_name }}</td>
                    <td>{{ $row->unit_name_plrl }}</td>
                    <td>
                        @include('layout.row_options', ['module' => 'units', 'id' => $row->unit_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>

</div>
@endsection
