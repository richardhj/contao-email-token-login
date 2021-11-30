<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_member_login_token'] = [
    'config' => [
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'member' => 'index',
                'token' => 'unique',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'member' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'token' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'expires' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'jumpTo' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];
