@php
    // alerts.php: flash alert_<type> messages. The message is output as is, like legacy.
    $alertClass = 'alert'.(isset($withoutMargin) ? ' no-margin' : '');
    $alertTypes = ['success' => ['success', 'info-circle'], 'info' => ['info', 'info-circle'], 'warning' => ['warning', 'exclamation-circle'], 'error' => ['danger', 'warning']];
@endphp
@foreach ($alertTypes as $type => [$class, $icon])
@if (session('alert_'.$type))
<div class="{{ $alertClass }} alert-{{ $class }} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <i class="fa fa-fw fa-lg fa-{{ $icon }}"></i><span>{!! session('alert_'.$type) !!}</span></div>
@endif
@endforeach
