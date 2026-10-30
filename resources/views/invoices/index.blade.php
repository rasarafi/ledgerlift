@extends('layout.app')

@section('content')
@php
    // The pager is built from the third URI segment ($this->uri->segment(3)), as typed.
    $statusBase = url('invoices/status').'/'.$statusSegment;
    $statuses = ['all', 'draft', 'sent', 'viewed', 'paid', 'overdue'];
@endphp
<div id="headerbar">

    <h1 class="headerbar-title">{!! __('ip.invoices') !!}</h1>

    <div class="headerbar-item pull-right">
        <button type="button" class="btn btn-default btn-sm submenu-toggle hidden-lg"
                data-toggle="collapse" data-target="#ip-submenu-collapse">
            <i class="fa fa-bars"></i> {!! __('ip.submenu') !!}
        </button>
        <a class="create-invoice btn btn-sm btn-primary" href="#">
            <i class="fa fa-plus"></i> {!! __('ip.new') !!}
        </a>
    </div>

    <div class="headerbar-item pull-right visible-lg">
        @include('layout.pager', ['baseUrl' => $statusBase])
    </div>

    <div class="headerbar-item pull-right visible-lg">
        <div class="btn-group btn-group-sm index-options">
@foreach ($statuses as $key)
            <a href="{{ url('invoices/status/'.$key) }}"
               class="btn {{ $status == $key ? 'btn-primary' : 'btn-default' }}">
                {!! __('ip.'.$key) !!}
            </a>
@endforeach
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
@foreach ($statuses as $key)
                {{-- index.php: every submenu button but the first has two spaces after "btn". --}}
                <a href="{{ url('invoices/status/'.$key) }}"
                   class="btn {{ $key === 'all' ? '' : ' ' }}{{ $status == $key ? 'btn-primary' : 'btn-default' }}">
                    {!! __('ip.'.$key) !!}
                </a>
@endforeach
            </div>
        </div>

    </div>
</div>

<div id="content" class="table-content">
    <div id="filter_results">
        @include('invoices.partial_invoice_table')
    </div>
</div>
@endsection
