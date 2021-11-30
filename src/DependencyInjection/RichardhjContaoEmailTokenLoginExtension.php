<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * This is the Bundle extension.
 */
class RichardhjContaoEmailTokenLoginExtension extends Extension
{
    /**
     * Loads a specific configuration.
     *
     * @throws \Exception if something went wrong
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
