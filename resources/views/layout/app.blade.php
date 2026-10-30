<!DOCTYPE html>

<!--[if lt IE 7]>
<html class="no-js ie6 oldie" lang="{{ __('ip.cldr') }}"> <![endif]-->
<!--[if IE 7]>
<html class="no-js ie7 oldie" lang="{{ __('ip.cldr') }}"> <![endif]-->
<!--[if IE 8]>
<html class="no-js ie8 oldie" lang="{{ __('ip.cldr') }}"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js" lang="{{ __('ip.cldr') }}"> <!--<![endif]-->

<head>
@include('layout.head')
</head>
<body class="{{ get_setting('disable_sidebar') ? 'hidden-sidebar' : '' }}">

<noscript>
    <div class="alert alert-danger no-margin">{{ __('ip.please_enable_js') }}</div>
</noscript>

@include('layout.navbar')

    <div id="main-area">
@if (get_setting('disable_sidebar') != 1)
@include('layout.sidebar')
@endif
         <div id="main-content">
@yield('content')
         </div>

    </div>

    <div id="modal-placeholder"></div>

@include('layout.fullpage-loader')

    <script defer src="{{ core_asset('js/scripts.min.js') }}"></script>
@if (__('ip.cldr') != 'en')
    <script src="{{ core_asset('js/locales/bootstrap-datepicker.'.__('ip.cldr').'.js') }}"></script>
@endif

</body>
</html>
