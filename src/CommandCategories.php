<?php

namespace Gemvc\CLI;

/**
 * Command metadata for gemvc/cli-dev (development commands only).
 * Core commands (init, db:migrate) are documented in gemvc/library.
 */
class CommandCategories
{
    public const CATEGORIES = [
        'Code Generation' => [
            'create:service' => 'Create a new service with optional components (-c: controller, -m: model, -t: table)',
            'create:controller' => 'Create a new controller for handling business logic',
            'create:model' => 'Create a new model for data processing and business rules',
            'create:table' => 'Create a new table class for database operations',
            'create:crud' => 'Create complete CRUD operations for a resource (service, controller, model, table)',
        ],
        'Database (development)' => [
            'db:init' => 'Initialize database based on configuration',
            'db:list' => 'List base tables and SQL views (columns)',
            'db:describe' => 'Describe a table or view (columns, indexes, FKs; view definition for views)',
            'db:drop' => 'Drop a specific table or view (db:drop Name)',
            'db:unique' => 'Add unique constraint to table column(s)',
        ],
        'Admin' => [
            'admin:setpassword' => 'Set admin password for accessing system pages in development mode',
            'admin:setadmin' => 'Create the first admin user with email and password',
        ],
    ];

    public static function getCommandClass(string $command): string
    {
        $commandMappings = [
            'create:service' => 'CreateService',
            'create:controller' => 'CreateController',
            'create:model' => 'CreateModel',
            'create:table' => 'CreateTable',
            'create:crud' => 'CreateCrud',
            'db:init' => 'DbInit',
            'db:list' => 'DbList',
            'db:describe' => 'DbDescribe',
            'db:drop' => 'DbDrop',
            'db:unique' => 'DbUnique',
            'admin:setpassword' => 'AdminSetpassword',
            'admin:setadmin' => 'SetAdmin',
        ];

        return $commandMappings[$command] ?? '';
    }

    public static function getCategory(string $command): string
    {
        foreach (self::CATEGORIES as $category => $commands) {
            if (isset($commands[$command])) {
                return $category;
            }
        }
        return 'Other';
    }

    public static function getDescription(string $command): string
    {
        foreach (self::CATEGORIES as $commands) {
            if (isset($commands[$command])) {
                return $commands[$command];
            }
        }
        return '';
    }

    /**
     * @return array<string, string|array<string>>
     */
    public static function getExamples(): array
    {
        return [
            'create:service' => [
                'vendor/bin/gemvc create:service User',
                'vendor/bin/gemvc create:service User -cmt',
            ],
            'create:controller' => 'vendor/bin/gemvc create:controller User',
            'create:model' => 'vendor/bin/gemvc create:model User',
            'create:table' => 'vendor/bin/gemvc create:table User',
            'create:crud' => 'vendor/bin/gemvc create:crud User',
            'db:init' => 'vendor/bin/gemvc db:init',
            'db:list' => 'vendor/bin/gemvc db:list',
            'db:describe' => [
                'vendor/bin/gemvc db:describe users',
                'vendor/bin/gemvc db:describe user_order_summary',
            ],
            'db:drop' => [
                'vendor/bin/gemvc db:drop users',
                'vendor/bin/gemvc db:drop user_order_summary --force',
            ],
            'db:unique' => 'vendor/bin/gemvc db:unique users/email',
            'admin:setpassword' => 'vendor/bin/gemvc admin:setpassword',
            'admin:setadmin' => 'vendor/bin/gemvc admin:setadmin',
        ];
    }
}
