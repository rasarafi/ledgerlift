@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.payments') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('payments/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('payments/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div id="filter_results">
        @include('payments.partial_payment_table')
    </div>

</div>
@endsection
