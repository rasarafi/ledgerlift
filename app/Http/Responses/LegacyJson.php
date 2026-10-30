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
        return self::text((string) json_encode($payload));
    }

    /** A body that is already JSON (or, where legacy echoes twice, several documents), served as text/html. */
    public static function text(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
