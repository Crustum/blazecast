<?php
declare(strict_types=1);

/**
 * Test database schema for BlazeCast plugin tests.
 *
 * This format resembles the existing fixture schema
 * and is converted to SQL via the Schema generation
 * features of the Database package.
 */
return [
    [
        'table' => 'users',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'username' => [
                'type' => 'string',
                'length' => 50,
                'null' => false,
            ],
            'email' => [
                'type' => 'string',
                'length' => 100,
                'null' => false,
            ],
            'password' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'full_name' => [
                'type' => 'string',
                'length' => 100,
                'null' => true,
            ],
            'active' => [
                'type' => 'boolean',
                'default' => true,
                'null' => false,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'modified' => [
                'type' => 'datetime',
                'null' => true,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
            'users_username_unique' => [
                'type' => 'unique',
                'columns' => [
                    'username',
                ],
            ],
            'users_email_unique' => [
                'type' => 'unique',
                'columns' => [
                    'email',
                ],
            ],
        ],
    ],
];
