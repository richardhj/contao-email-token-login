<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/contao-email-token-login.
 *
 * Copyright (c) Richard Henkenjohann
 *
 * @license LGPL-3.0-or-later
 */

namespace Richardhj\ContaoEmailTokenLoginBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Richardhj\ContaoEmailTokenLoginBundle\RichardhjContaoEmailTokenLoginBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(RichardhjContaoEmailTokenLoginBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class, 'notification_center']),
        ];
    }

    /**
     * Returns a collection of routes for this bundle.
     *
     * @throws \Exception
     *
     * @return RouteCollection|null
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__.'/../Resources/config/routing.yml')
            ->load(__DIR__.'/../Resources/config/routing.yml')
        ;
    }
}
