<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * When maintenance mode is on (Settings → General), visitors get a 503 page.
 * The admin panel and logged-in users are unaffected.
 */
class Maintenance implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! service('installer')->isInstalled()
            || preg_match('#^(' . preg_quote(config('Cms')->adminPath, '#') . '|install)(/|$)#', $request->getPath()) === 1
            || setting('maintenance_mode') !== '1'
            || current_user() !== null) {
            return null;
        }

        return service('response')
            ->setStatusCode(503)
            ->setHeader('Retry-After', '3600')
            ->setBody(blade('system.maintenance', ['siteName' => setting('site_name', 'SurexCore')]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
