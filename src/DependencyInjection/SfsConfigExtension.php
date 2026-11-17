<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\DependencyInjection;

use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\Config\DefaultsConfigProvider;
use Softspring\Bundle\ConfigBundle\DependencyInjection\EnvVarProcessor\ConfigEnvVarProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class SfsConfigExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->register('sfs_config.provider', DefaultsConfigProvider::class)
            ->setArgument('$defaults', $config['defaults']);
        $container->setAlias(ConfigProviderInterface::class, 'sfs_config.provider');
        $container->register('sfs_config.env_var_processor', ConfigEnvVarProcessor::class)
            ->setArgument('$provider', new Reference(ConfigProviderInterface::class))
            ->addTag('container.env_var_processor');
    }
}
