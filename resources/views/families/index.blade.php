@extends('layout.app')

@section('content')
<div id="headerbar">
    <h1 class="headerbar-title">{!! __('ip.families') !!}</h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="{{ url('families/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right">
        @include('layout.pager', ['baseUrl' => url('families/index')])
    </div>

</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div id="filter_results">
        @include('families.partial_families_table')
    </div>

</div>
@endsection
