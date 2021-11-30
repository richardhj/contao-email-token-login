<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['token_login'] = str_replace('autologin', 'nc_notification', $GLOBALS['TL_DCA']['tl_module']['palettes']['login']);
