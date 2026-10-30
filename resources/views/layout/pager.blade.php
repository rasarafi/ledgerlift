{{-- pager_helper.php: pager() --}}
<div class="model-pager btn-group btn-group-sm">
@if ($pager->previousOffset() >= 0)
    <a class="btn btn-default" href="{{ $baseUrl }}/0" title="{!! __('ip.first') !!}"><i class="fa fa-fast-backward no-margin"></i></a>
    <a class="btn btn-default" href="{{ $baseUrl }}/{{ $pager->previousOffset() }}" title="{!! __('ip.prev') !!}"><i class="fa fa-backward no-margin"></i></a>
@else
    <a class="btn btn-default disabled" href="#" title="{!! __('ip.first') !!}"><i class="fa fa-fast-backward no-margin"></i></a>
    <a class="btn btn-default disabled" href="#" title="{!! __('ip.prev') !!}"><i class="fa fa-backward no-margin"></i></a>
@endif
@if ($pager->nextOffset() <= $pager->lastOffset())
    <a class="btn btn-default" href="{{ $baseUrl }}/{{ $pager->nextOffset() }}" title="{!! __('ip.next') !!}"><i class="fa fa-forward no-margin"></i></a>
    <a class="btn btn-default" href="{{ $baseUrl }}/{{ $pager->lastOffset() }}" title="{!! __('ip.last') !!}"><i class="fa fa-fast-forward no-margin"></i></a>
@else
    <a class="btn btn-default disabled" href="#" title="{!! __('ip.next') !!}"><i class="fa fa-forward no-margin"></i></a>
    <a class="btn btn-default disabled" href="#" title="{!! __('ip.last') !!}"><i class="fa fa-fast-forward no-margin"></i></a>
@endif
</div>
