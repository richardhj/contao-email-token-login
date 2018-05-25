<?php

/**
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) 2018-2018 Richard Henkenjohann
 *
 * @package   richardhj/contao-email-token-login
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2018-2018 Richard Henkenjohann
 * @license   https://github.com/richardhj/contao-email-token-login/blob/master/LICENSE
 */

use Richardhj\ContaoEmailTokenLoginBundle\Module\TokenLogin;


$GLOBALS['FE_MOD']['user']['token_login'] = TokenLogin::class;

/**
 * Notification Center Notification Types
 */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'] = array_merge_recursive(
    (array)$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'],
    [
        'contao' => [
            'member_token_login' => [
                'recipients'           => ['recipient_email'],
                'email_subject'        => ['domain', 'link', 'member_*', 'recipient_email'],
                'email_text'           => ['domain', 'link', 'member_*', 'recipient_email'],
                'email_html'           => ['domain', 'link', 'member_*', 'recipient_email'],
                'file_name'            => ['domain', 'link', 'member_*', 'recipient_email'],
                'file_content'         => ['domain', 'link', 'member_*', 'recipient_email'],
                'email_sender_name'    => ['recipient_email'],
                'email_sender_address' => ['recipient_email'],
                'email_recipient_cc'   => ['recipient_email'],
                'email_recipient_bcc'  => ['recipient_email'],
                'email_replyTo'        => ['recipient_email'],
            ],
        ],
    ]
);
