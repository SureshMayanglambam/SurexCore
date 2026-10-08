<?php

namespace App\Commands;

use CodeIgniter\CLI\CLI;

/**
 * Shared by make:cms-model and make:cms-controller.
 */
trait CmsGenerator
{
    /**
     * "store", "product_item", "StoreModel" → "Store", "ProductItem" (null when invalid).
     */
    private function className(?string $input, string $suffix = ''): ?string
    {
        $name = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', trim((string) $input))));
        if ($suffix !== '' && str_ends_with($name, $suffix) && $name !== $suffix) {
            $name = substr($name, 0, -strlen($suffix));
        }

        return preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) ? $name : null;
    }

    /**
     * "ProductItem" → "product-item" (content type slug).
     */
    private function slugOf(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /**
     * An option given as "--name value" or "--name=value".
     */
    private function option(string $name): ?string
    {
        $value = CLI::getOption($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen($name) + 3);
            }
        }

        return null;
    }

    /**
     * Write a file under APPPATH, never overwriting unless --force.
     */
    private function writeFile(string $relative, string $contents): bool
    {
        $path = APPPATH . $relative;

        if (is_file($path) && CLI::getOption('force') === null) {
            CLI::error("App/{$relative} already exists. Use --force to overwrite it.");

            return false;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $contents);
        CLI::write('Created: ' . CLI::color("App/{$relative}", 'green'));

        return true;
    }
}
