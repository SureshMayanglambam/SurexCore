<?php

namespace App\Controller;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Serves an SVG site logo: /branding/logo-xxxxxxxxxxxx.svg (media_url() points here).
 *
 * SVG files are never served straight from public/uploads/ (its .htaccess blocks them, since an SVG can
 * contain scripts). This sends the already-sanitized logo with a Content-Security-Policy "sandbox",
 * so even when it is opened on its own, nothing in it can run or load anything.
 */
class BrandingFile extends BaseController
{
    public function show(string $name): ResponseInterface
    {
        $file = FCPATH . 'uploads/branding/' . $name;

        if (! preg_match('/^logo-[a-f0-9]{12}\.svg$/', $name) || ! is_file($file)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox")
            ->setHeader('X-Content-Type-Options', 'nosniff')
            // A new upload gets a new random name, so the file never changes: cache it for a year.
            ->setHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->setBody((string) file_get_contents($file));
    }
}
