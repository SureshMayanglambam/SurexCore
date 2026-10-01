<?php

namespace App\Controller;

class Errors extends FrontController
{
    public function notFound(): string
    {
        $this->response->setStatusCode(404);

        return $this->render('system.errors.404');
    }
}
