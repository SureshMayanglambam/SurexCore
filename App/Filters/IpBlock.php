<?php

namespace App\Filters;

use App\Libraries\IpBlocklist;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Turns away IPs on the admin block list (writable/security/blocked-ips.txt) from every admin page,
 * the login page included. The public website stays open to them.
 */
class IpBlock implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! (new IpBlocklist())->isBlocked($request->getIPAddress())) {
            return null;
        }

        // In both languages: the visitor isn't logged in, so their language is unknown.
        return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')->setBody(
            '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow"><title>403 Forbidden</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:15vh auto;padding:0 20px;color:#1d2b24;line-height:1.7">'
            . '<h1 style="font-size:1.4rem">' . esc(lang('Admin.ipblock.title', [], 'en')) . '</h1>'
            . '<p>' . esc(lang('Admin.ipblock.body', [], 'en')) . '</p>'
            . '<hr style="border:0;border-top:1px solid #ddd;margin:24px 0">'
            . '<h1 style="font-size:1.4rem">' . esc(lang('Admin.ipblock.title', [], 'ja')) . '</h1>'
            . '<p>' . esc(lang('Admin.ipblock.body', [], 'ja')) . '</p>'
            . '</body></html>',
        );
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
