<?php

namespace App\Libraries;

use Config\Cms;
use eftec\bladeone\BladeOne;

/**
 * BladeOne wired into CodeIgniter: templates live in View/, compiled files in writable/cache/blade.
 */
class Blade extends BladeOne
{
    public function __construct(Cms $config)
    {
        if (! is_dir($config->bladeCache)) {
            mkdir($config->bladeCache, 0775, true);
        }

        parent::__construct($config->viewPath, $config->bladeCache, self::MODE_AUTO);

        // Use CodeIgniter's CSRF protection instead of BladeOne's own token.
        $this->directive('csrf', static fn () => '<?= csrf_field() ?>');
        $this->directive('method', static fn ($expr) => '<input type="hidden" name="_method" value="<?= esc(' . $expr . ') ?>">');

        // @error('field') {{ $message }} @enderror like Laravel: validation errors flashed as session('errors').
        $this->errorCallBack = static function ($key = null) {
            $errors = session('errors');

            return is_array($errors) && is_string($key) ? ($errors[$key] ?? false) : false;
        };

        // @json($value) like Laravel: safe to print inside <script> tags.
        $this->directive('json', static fn ($expr) => '<?= json_encode(' . $expr . ', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>');
    }
}
