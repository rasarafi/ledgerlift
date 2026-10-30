@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.invoice_groups') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('invoice_groups/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('invoice_groups/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.name') !!}</th>
                <th>{!! __('ip.next_id') !!}</th>
                <th>{!! __('ip.left_pad') !!}</th>
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->invoice_group_name }}</td>
                    <td>{{ $row->invoice_group_next_id }}</td>
                    <td>{{ $row->invoice_group_left_pad }}</td>
                    <td>
                        @include('layout.row_options', ['module' => 'invoice_groups', 'id' => $row->invoice_group_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>

</div>
@endsection
