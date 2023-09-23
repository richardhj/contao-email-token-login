<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

if (isset($GLOBALS['NOTIFICATION_CENTER'])) {
    $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'] = array_merge_recursive(
        (array) $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'],
        [
            'contao' => [
                'member_token_login' => [
                    'recipients' => ['recipient_email'],
                    'email_subject' => ['domain', 'link', 'member_*', 'recipient_email'],
                    'email_text' => ['domain', 'link', 'member_*', 'recipient_email'],
                    'email_html' => ['domain', 'link', 'member_*', 'recipient_email', 'login_form_html'],
                    'file_name' => ['domain', 'link', 'member_*', 'recipient_email'],
                    'file_content' => ['domain', 'link', 'member_*', 'recipient_email'],
                    'email_sender_name' => ['recipient_email'],
                    'email_sender_address' => ['recipient_email'],
                    'email_recipient_cc' => ['recipient_email'],
                    'email_recipient_bcc' => ['recipient_email'],
                    'email_replyTo' => ['recipient_email'],
                ],
            ],
        ]
    );
}
