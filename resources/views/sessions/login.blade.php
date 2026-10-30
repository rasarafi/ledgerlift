@use('App\Support\Strangler')
<!doctype html>

<!--[if lt IE 7]>
<html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>
<html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>
<html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js" lang="en"> <!--<![endif]-->

<head>
    <title>{{ get_setting('custom_title', 'ledgerlift') }} - {!! __('ip.login') !!}</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width">
    <meta name="robots" content="NOINDEX,NOFOLLOW">

    <link rel="icon" href="{{ core_asset('img/favicon.png') }}" type="image/png">

    <link rel="stylesheet" href="{{ theme_asset('css/style.css') }}" type="text/css">
    <link rel="stylesheet" href="{{ core_asset('css/custom.css') }}" type="text/css">
</head>

<body>

<noscript>
    <div class="alert alert-danger no-margin">{!! __('ip.please_enable_js') !!}</div>
</noscript>

<br>

<div class="container">

    <div id="login" class="col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3">

            <h1>{!! __('ip.login') !!}</h1>

        <div class="row">@include('layout.alerts')</div>

        <form method="post" action="{{ url('sessions/login') }}">

            <input type="hidden" name="_ip_csrf" value="{{ csrf_token() }}">
            <div class="form-group">
                <label for="email" class="control-label">{!! __('ip.email') !!}</label>
                <input type="email" name="email" id="email" class="form-control"
                       placeholder="{!! __('ip.email') !!}" required autofocus
                >
            </div>

            <div class="form-group">
                <label for="password" class="control-label">{!! __('ip.password') !!}</label>
                <input type="password" name="password" id="password" class="form-control"
                       placeholder="{!! __('ip.password') !!}" required
                >
            </div>

            <input type="hidden" name="btn_login" value="true">

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-unlock fa-margin"></i> {!! __('ip.login') !!}
            </button>
            <a href="{{ Strangler::legacy('sessions/passwordreset') }}" class="btn btn-default">
                {!! __('ip.forgot_your_password') !!}
            </a>

        </form>

    </div>
</div>

</body>
</html>
