<?php

namespace App\Http\Middleware;

use App\Support\XssClean;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin_Controller::__construct() runs XSS_Protection_Trait::filter_input() on every admin
 * POST, so what a controller reads (and the database stores) is the cleaned value. Passwords
 * are exempt, at any depth. The email-template fields (`email_template_body`, `body`) get
 * HTML Purifier in legacy; they belong to modules this app has not ported.
 */
class FilterLegacyInput
{
    private const BYPASS = ['user_password', 'user_passwordv'];

    public function __construct(private readonly XssClean $xss) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->request->count() > 0) {
            $request->request->replace($this->filter($request->request->all()));
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private function filter(array $input): array
    {
        foreach ($input as $key => $value) {
            if (in_array($key, self::BYPASS, true)) {
                continue;
            }

            $input[$key] = is_array($value) ? $this->filter($value) : $this->xss->clean((string) $value);
        }

        return $input;
    }
}
