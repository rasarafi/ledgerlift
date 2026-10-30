<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;

final class LegacyJson
{
    /**
     * JSON served as text/html, as the legacy AJAX controllers do (json_encode_ajax echoes it).
     * json_parse() in scripts.js calls data.search() on the response, which a parsed
     * application/json body would break.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function make(array $payload): Response
    {
        return new Response(json_encode($payload), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
