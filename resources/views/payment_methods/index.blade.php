@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.payment_methods') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('payment_methods/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('payment_methods/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div class="table-responsive">
        <table class="table table-hover table-striped">

            <thead>
            <tr>
                <th>{!! __('ip.payment_method') !!}</th>
                <th>{!! __('ip.options') !!}</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->payment_method_name }}</td>
                    <td>
                        @include('layout.row_options', ['module' => 'payment_methods', 'id' => $row->payment_method_id])
                    </td>
                </tr>
            @endforeach
            </tbody>

        </table>
    </div>

</div>
@endsection
