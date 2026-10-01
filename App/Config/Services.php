<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /**
     * BladeOne template engine, rendering View/*.blade.php.
     */
    public static function blade(bool $getShared = true): \App\Libraries\Blade
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new \App\Libraries\Blade(config('Cms'));
    }

    /**
     * First-run installer and automatic database upgrades.
     */
    public static function installer(bool $getShared = true): \App\Libraries\Installer
    {
        if ($getShared) {
            return static::getSharedInstance('installer');
        }

        return new \App\Libraries\Installer(config('Cms'));
    }

    /**
     * Admin activity log writer.
     */
    public static function activity(bool $getShared = true): \App\Libraries\ActivityLogger
    {
        if ($getShared) {
            return static::getSharedInstance('activity');
        }

        return new \App\Libraries\ActivityLogger();
    }

    /**
     * Custom field definitions and values (ACF-style).
     */
    public static function fields(bool $getShared = true): \App\Libraries\Fields
    {
        if ($getShared) {
            return static::getSharedInstance('fields');
        }

        return new \App\Libraries\Fields();
    }

    /**
     * Tables and columns of content types.
     */
    public static function contentSchema(bool $getShared = true): \App\Libraries\ContentSchema
    {
        if ($getShared) {
            return static::getSharedInstance('contentSchema');
        }

        return new \App\Libraries\ContentSchema();
    }
}
