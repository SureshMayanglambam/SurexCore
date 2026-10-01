<?php

namespace App\Controller;

use CodeIgniter\Controller;

abstract class BaseController extends Controller
{
    /**
     * Helpers available in every controller and Blade view.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'text'];

    /**
     * Render View/{name}.blade.php, e.g. $this->render('frontend.index') or $this->render('frontend.store.detail', ['item' => $item]).
     */
    protected function render(string $view, array $data = []): string
    {
        return blade($view, $data + $this->sharedData());
    }

    /**
     * Variables available to every view rendered by this controller.
     */
    protected function sharedData(): array
    {
        return [];
    }
}
