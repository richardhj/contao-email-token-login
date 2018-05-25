# ContaoEmailTokenLoginBundle

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]]()
[![Dependency Status][ico-dependencies]][link-dependencies]

Alternative login form, in case your members don't necessarily have nor need a password.

The login form just contains the input for the username (or email if mailusername is installed). The member then needs to hit the login in link from the mail he just got sent. The login link expires after 2 hours and immediately logs in the user.

## Configuration:

* Create a new notification of type "token login".
* Place at least the token `##link##` in the message.
* Create a module of type "token login". Select the notification, select a jumpTo page, place the module.

[ico-version]: https://img.shields.io/packagist/v/richardhj/contao-email-token-login.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-LGPL-brightgreen.svg?style=flat-square
[ico-dependencies]: https://www.versioneye.com/php/richardhj:contao-email-token-login/badge.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/richardhj/contao-email-token-login
[link-dependencies]: https://www.versioneye.com/php/richardhj:contao-email-token-login
