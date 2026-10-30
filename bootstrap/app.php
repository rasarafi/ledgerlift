<?php

use App\Http\Middleware\AjaxOnly;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RejectDeleteOverGet;
use App\Http\Middleware\VerifyLegacyCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CodeIgniter neither trims nor nulls input, and legacy stores '' and padded values.
        $middleware->remove([TrimStrings::class, ConvertEmptyStringsToNull::class]);

        $middleware->prepend(RejectDeleteOverGet::class);
        $middleware->web(replace: [PreventRequestForgery::class => VerifyLegacyCsrfToken::class]);

        $middleware->alias(['ajax' => AjaxOnly::class]);
        $middleware->group('admin', [EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
