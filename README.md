# ContaoEmailTokenLoginBundle

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]]()

Alternative login form, in case your members don't necessarily have nor need a password.

The login form just asks for the username (or email if mailusername is installed). The user submits the form and receives an email (notifications are managed by notification_center). The user gets logged in when they hit the login in link within the email. The login link expires after 2 hours and immediately logs in the user.

Klarna is using this approach as well:

<img width="508" src="https://user-images.githubusercontent.com/1284725/52039660-62a53780-2535-11e9-86b0-ccc2dbc7afe5.png">

## Configuration:

1. Create a new notification of type "token login".
2. Place at least the token `##link##` in the message.
3. Create a module of type "token login". Select the notification, select a jumpTo page, place the module somewhere on the website.

[ico-version]: https://img.shields.io/packagist/v/richardhj/contao-email-token-login.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-LGPL-brightgreen.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/richardhj/contao-email-token-login
