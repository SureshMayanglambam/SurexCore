<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Security headers on every response (static files get the same from public/.htaccess).
 * The admin panel also gets a Content-Security-Policy, no caching and noindex.
 * The website gets no CSP: its developers decide which scripts and fonts it loads.
 */
class SecurityHeaders implements FilterInterface
{
    private const HEADERS = [
        'X-Content-Type-Options'            => 'nosniff',
        'X-Frame-Options'                   => 'SAMEORIGIN',
        'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
        'Permissions-Policy'                => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy'        => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    /** Everything the admin needs comes from this site (assets are self-hosted). */
    private const ADMIN_CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self' blob:; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        foreach (self::HEADERS as $name => $value) {
            $response->setHeader($name, $value);
        }
        $response->removeHeader('X-Powered-By');

        if ($request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($this->isAdmin($request)) {
            // The preview renders the website (its own scripts and fonts): no admin CSP there.
            if (! str_contains($request->getUri()->getPath(), '/preview')) {
                $response->setHeader('Content-Security-Policy', self::ADMIN_CSP);
            }
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow');
            // Admin pages hold private data: never kept by browsers or proxies (downloads keep their own headers).
            if (! $response->hasHeader('Content-Disposition')) {
                $response->setHeader('Cache-Control', 'no-store, max-age=0');
            }
        }

        return $response;
    }

    private function isAdmin(RequestInterface $request): bool
    {
        $admin = trim(config('Cms')->adminPath, '/');
        $path  = trim($request->getUri()->getPath(), '/');

        // The site may live in a subfolder: compare from the end of the base path.
        $base = trim((string) parse_url(config('App')->baseURL, PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base) + 1);
        }

        return $path === $admin || str_starts_with($path, $admin . '/');
    }
}
