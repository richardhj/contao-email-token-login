<?php

/**
 * Front end modules
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
