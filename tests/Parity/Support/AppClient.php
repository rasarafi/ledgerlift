<?php

namespace Tests\Parity\Support;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A browser-like HTTP client for one app: its own cookie jar (empty at the start, so every
 * scenario logs in fresh), the CSRF token of the last page it saw, and no redirect following,
 * so a scenario can compare where each app redirects to.
 */
final class AppClient
{
    private readonly CookieJar $cookies;

    private ?string $csrfToken = null;

    private ?DOMXPath $page = null;

    private ?string $location = null;

    public function __construct(public readonly string $baseUrl)
    {
        $this->cookies = new CookieJar;
    }

    /**
     * Logs in through the login form, then opens the page it redirects to, as a browser would;
     * that page carries the token for later posts (Laravel issues a new one at login).
     */
    public function login(string $email = 'admin@ledgerlift.test', string $password = 'ledgerlift-demo-1'): void
    {
        $this->get('sessions/login');
        $response = $this->post('sessions/login', ['email' => $email, 'password' => $password, 'btn_login' => 'true']);

        if (! $response->redirect() || $this->location() !== 'dashboard') {
            throw new RuntimeException("Login as $email on {$this->baseUrl} failed: {$response->status()} to ".($this->location() ?? 'nowhere'));
        }

        $this->get('dashboard');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->record($this->request()->get($path, $query));
    }

    /**
     * Posts a form. `_ip_csrf` comes from the last page unless `$data` sets it.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $path, array $data = [], bool $ajax = false): Response
    {
        $request = $this->request()->asForm();

        if ($ajax) {
            $request = $request->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        }

        if ($this->csrfToken !== null) {
            $data += ['_ip_csrf' => $this->csrfToken];
        }

        return $this->record($request->post($path, $data));
    }

    /**
     * Where the last response redirected to: a path relative to this app (`dashboard`), or the
     * full URL when it points elsewhere. Null when the last response was not a redirect.
     */
    public function location(): ?string
    {
        if ($this->location === null) {
            return null;
        }

        $base = rtrim($this->baseUrl, '/').'/';

        return str_starts_with($this->location, $base) ? substr($this->location, strlen($base)) : $this->location;
    }

    /** The trimmed text of the first node matching `$xpath` on the last response's page, or null. */
    public function text(string $xpath): ?string
    {
        $node = $this->page?->query($xpath)?->item(0);

        return $node === null ? null : trim($node->textContent);
    }

    /**
     * The text of every node matching `$xpath` on the last response's page, whitespace runs
     * collapsed to one space (the only difference templating engines are allowed to make).
     *
     * @return list<string>
     */
    public function texts(string $xpath): array
    {
        $texts = [];

        foreach ($this->page?->query($xpath) ?? [] as $node) {
            $texts[] = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
        }

        return $texts;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withOptions(['cookies' => $this->cookies])
            ->withoutRedirecting();
    }

    /**
     * Keeps the Location of a redirect, and the page of an HTML response with its CSRF token:
     * the `csrf_token_value` meta tag of admin pages, or the hidden `_ip_csrf` input of the
     * login page. Any other response leaves no page; the token is kept until a page has a new one.
     */
    private function record(Response $response): Response
    {
        $this->location = $response->redirect() ? $response->header('Location') : null;
        $this->page = null;

        // Legacy AJAX controllers answer a blank text/html 200 to non-AJAX requests.
        if ($response->redirect() || $response->body() === '' || ! str_contains($response->header('Content-Type'), 'text/html')) {
            return $response;
        }

        $document = new DOMDocument;
        $document->loadHTML($response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->page = new DOMXPath($document);

        $token = $this->page->evaluate('string(//meta[@name="csrf_token_value"]/@content)')
            ?: $this->page->evaluate('string(//input[@name="_ip_csrf"]/@value)');

        if (is_string($token) && $token !== '') {
            $this->csrfToken = $token;
        }

        return $response;
    }
}
