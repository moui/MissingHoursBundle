<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MissingHoursExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    /**
     * The report shows other people's balances, so it goes to the roles that
     * can already see other users' timesheets. Which users each viewer gets is
     * then narrowed per row by Kimai's own team rules (see UserScope).
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_TEAMLEAD' => ['missing_hours_report'],
                    'ROLE_ADMIN' => ['missing_hours_report'],
                    'ROLE_SUPER_ADMIN' => ['missing_hours_report'],
                ],
            ],
        ]);
    }

    public function getAlias(): string
    {
        return 'missing_hours';
    }
}
