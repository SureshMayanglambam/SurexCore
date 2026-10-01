<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends every request to /install until the CMS is installed, and locks /install afterwards.
 */
class InstallCheck implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $onInstaller = preg_match('#^install(/|$)#', $request->getPath()) === 1;
        $installed   = service('installer')->isInstalled();

        if (! $installed && ! $onInstaller) {
            return redirect()->to(site_url('install'));
        }

        if ($installed && $onInstaller) {
            return redirect()->to(site_url('/'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
