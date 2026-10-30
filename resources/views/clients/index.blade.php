@extends('layout.app')

@section('content')
@php
    // The buttons and the pager are built from the third URI segment ($this->uri->segment(3)), as typed.
    $statusBase = url('clients/status').'/'.$statusSegment;
    $isActive = fn (string $status) => $statusSegment == $status || ($status === 'active' && ! $statusSegment);
@endphp
<div id="headerbar">

    <h1 class="headerbar-title">{!! __('ip.clients') !!}</h1>

    <div class="headerbar-item pull-right">
        <button type="button" class="btn btn-default btn-sm submenu-toggle hidden-lg"
                data-toggle="collapse" data-target="#ip-submenu-collapse">
            <i class="fa fa-bars"></i> {!! __('ip.submenu') !!}
        </button>
        <a class="btn btn-primary btn-sm" href="{{ url('clients/form') }}">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right visible-lg">
        @include('layout.pager', ['baseUrl' => $statusBase])
    </div>

    <div class="headerbar-item pull-right visible-lg">
        <div class="btn-group btn-group-sm index-options">
            <a href="{{ url('clients/status/active') }}"
               class="btn {{ $isActive('active') ? 'btn-primary' : 'btn-default' }}">
                {!! __('ip.active') !!}
            </a>
            <a href="{{ url('clients/status/inactive') }}"
               class="btn  {{ $statusSegment == 'inactive' ? 'btn-primary' : 'btn-default' }}">
                {!! __('ip.inactive') !!}
            </a>
            <a href="{{ url('clients/status/all') }}"
               class="btn  {{ $statusSegment == 'all' ? 'btn-primary' : 'btn-default' }}">
                {!! __('ip.all') !!}
            </a>
        </div>
    </div>

</div>

<div id="submenu">
    <div class="collapse clearfix" id="ip-submenu-collapse">

        <div class="submenu-row">
            @include('layout.pager', ['baseUrl' => $statusBase])
        </div>

        <div class="submenu-row">
            <div class="btn-group btn-group-sm index-options">
                <a href="{{ url('clients/status/active') }}"
                   class="btn {{ $isActive('active') ? 'btn-primary' : 'btn-default' }}">
                    {!! __('ip.active') !!}
                </a>
                <a href="{{ url('clients/status/inactive') }}"
                   class="btn  {{ $statusSegment == 'inactive' ? 'btn-primary' : 'btn-default' }}">
                    {!! __('ip.inactive') !!}
                </a>
                <a href="{{ url('clients/status/all') }}"
                   class="btn  {{ $statusSegment == 'all' ? 'btn-primary' : 'btn-default' }}">
                    {!! __('ip.all') !!}
                </a>
            </div>
        </div>

    </div>
</div>

<div id="content" class="table-content">

    @include('layout.alerts')

    <div id="filter_results">
        @include('clients.partial_client_table')
    </div>

</div>
@endsection
