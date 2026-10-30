<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LegacyRedirect
{
    /**
     * CodeIgniter's redirect() under HTTP/1.1: 307 after a GET, 303 after anything else.
     */
    public static function to(Request $request, string $url): RedirectResponse
    {
        return redirect()->to($url, $request->isMethod('GET') ? 307 : 303);
    }
}
