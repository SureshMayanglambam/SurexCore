<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../App/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// The framework lives in vendor/ (in the release zip; for a Git clone run: composer install).
if (! is_file($paths->systemDirectory . '/Boot.php')) {
    header('HTTP/1.1 503 Service Unavailable', true, 503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>SurexCore</title>'
        . '<div style="font:16px/1.7 system-ui,sans-serif;max-width:640px;margin:15vh auto;padding:0 20px">'
        . '<h1 style="font-size:1.5rem">SurexCore: the vendor/ folder is missing</h1>'
        . '<p>The framework (CodeIgniter) is installed into <code>vendor/</code>. Either:</p><ul>'
        . '<li>use the release zip <code>SurexCore-x.y.z.zip</code>, which already includes <code>vendor/</code>, or</li>'
        . '<li>run <code>composer install</code> in the project folder (with Docker: <code>docker compose exec app composer install</code>).</li>'
        . '</ul></div>';

    exit(1);
}

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
