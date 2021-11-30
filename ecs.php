<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__.'/vendor/contao/easy-coding-standard/config/contao.php');

    $services = $containerConfigurator->services();

    $services
        ->set(HeaderCommentFixer::class)
        ->call('configure', [[
            'header' => "This file is part of richardhj/contao-email-token-login.\n\nCopyright (c) Richard Henkenjohann\n\n@license LGPL-3.0-or-later",
        ]])
    ;
};
