<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark make:cms-model Store [--slug=store] [--force]
 * → App/Model/StoreModel.php, a model for the content type "store" (created in the admin).
 */
class MakeCmsModel extends BaseCommand
{
    use CmsGenerator;

    protected $group       = 'SurexCore';
    protected $name        = 'make:cms-model';
    protected $description = 'Create a model for a content type: App/Model/{Name}Model.php';
    protected $usage       = 'make:cms-model <Name> [options]';
    protected $arguments   = ['Name' => 'Model name without "Model", e.g. Store → StoreModel'];
    protected $options     = [
        '--slug'  => 'Content type slug (default: from the name, ProductItem → product-item)',
        '--force' => 'Overwrite an existing file',
    ];

    public function run(array $params)
    {
        $name = $this->className($params[0] ?? CLI::prompt('Model name (e.g. Store)'), 'Model');
        if ($name === null) {
            CLI::error('Use letters and numbers, starting with a letter, e.g. Store or ProductItem.');

            return EXIT_USER_INPUT;
        }

        $slug  = $this->option('slug') ?? $this->slugOf($name);
        $class = $name . 'Model';

        $code = <<<PHP
            <?php

            namespace App\\Model;

            /**
             * {$name} (content type "{$slug}").
             *
             *   {$class}::published()->latest()->paginate(10);
             *   {$class}::published()->where('slug', \$slug)->firstOrFail();
             */
            class {$class} extends EntryModel
            {
                protected string \$contentTypeSlug = '{$slug}';
            }

            PHP;

        if (! $this->writeFile("Model/{$class}.php", $code)) {
            return EXIT_ERROR;
        }

        if (! content_type_exists($slug)) {
            CLI::write("Next: create the content type with slug \"{$slug}\" in the admin (設定 → コンテンツタイプ).", 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
