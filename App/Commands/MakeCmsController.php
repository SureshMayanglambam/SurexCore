<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark make:cms-controller Store [--model=StoreModel] [--force]
 * → App/Controller/Store.php with index() and detail(), using App\Model\StoreModel.
 */
class MakeCmsController extends BaseCommand
{
    use CmsGenerator;

    protected $group       = 'SurexCore';
    protected $name        = 'make:cms-controller';
    protected $description = 'Create a frontend controller: App/Controller/{Name}.php';
    protected $usage       = 'make:cms-controller <Name> [options]';
    protected $arguments   = ['Name' => 'Controller name, e.g. Store'];
    protected $options     = [
        '--model' => 'Model it uses (default: {Name}Model)',
        '--force' => 'Overwrite an existing file',
    ];

    public function run(array $params)
    {
        $name = $this->className($params[0] ?? CLI::prompt('Controller name (e.g. Store)'));
        if ($name === null) {
            CLI::error('Use letters and numbers, starting with a letter, e.g. Store or ProductItem.');

            return EXIT_USER_INPUT;
        }

        $model = $this->className($this->option('model') ?? $name, 'Model');
        if ($model === null) {
            CLI::error('Invalid --model name.');

            return EXIT_USER_INPUT;
        }
        $model .= 'Model';

        $code = <<<PHP
            <?php

            namespace App\\Controller;

            use App\\Model\\{$model};

            class {$name} extends FrontController
            {
                public function index(): string
                {

                }

                public function detail(string \$slug): string
                {

                }
            }

            PHP;

        if (! $this->writeFile("Controller/{$name}.php", $code)) {
            return EXIT_ERROR;
        }

        if (! is_file(APPPATH . "Model/{$model}.php")) {
            CLI::write("Next: php spark make:cms-model " . substr($model, 0, -5), 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
