<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use Softspring\Bundle\ConfigBundle\Admin\ConfigActionListener;
use Softspring\Bundle\ConfigBundle\Attribute\SfsConfig;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\Config\ConfigValue;
use Softspring\Bundle\ConfigBundle\Config\DefaultsConfigProvider;
use Softspring\Bundle\ConfigBundle\Config\DefinitionRegistry;
use Softspring\Bundle\ConfigBundle\Config\DoctrineConfigProvider;
use Softspring\Bundle\ConfigBundle\DependencyInjection\EnvVarProcessor\ConfigEnvVarProcessor;
use Softspring\Bundle\ConfigBundle\Entity\ConfigOverride;
use Softspring\Bundle\ConfigBundle\Form\ConfigValueForm;
use Softspring\Component\CrudlController\Form\DefaultDeleteForm;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

class SfsConfigExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        if (!$config['admin']['enabled']) {
            return;
        }
        foreach (['doctrine', 'sfs_crudl'] as $extension) {
            if (!$container->hasExtension($extension)) {
                throw new LogicException(sprintf('Config administration requires the "%s" bundle.', $extension));
            }
        }
        $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['SfsConfig' => [
            'type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/../Entity',
            'prefix' => 'Softspring\Bundle\ConfigBundle\Entity',
        ]]]]);
        if ($container->hasExtension('doctrine_migrations')) {
            $container->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => [
                'Softspring\Bundle\ConfigBundle\Migrations' => __DIR__.'/../Migrations',
            ]]);
        }
        $actions = [];
        foreach (['list', 'read', 'update', 'delete'] as $action) {
            $actions[$action] = ['action' => $action, 'is_granted' => $config['admin']['permissions'][$action],
                'view' => '@SfsConfig/admin/'.$action.'.html.twig'];
            if ('list' === $action) {
                $actions[$action]['initialize_event_name'] = 'sfs_config.admin.list.initialize';
            } else {
                $actions[$action] += ['load_entity_event_name' => 'sfs_config.admin.'.$action.'.load',
                    'view_event_name' => 'sfs_config.admin.'.$action.'.view', 'entity_attribute' => 'setting'];
            }
            if (in_array($action, ['update', 'delete'], true)) {
                $actions[$action] += ['form' => 'update' === $action ? ConfigValueForm::class : DefaultDeleteForm::class,
                    'success_redirect_to' => 'sfs_config_admin_list',
                    'success_event_name' => 'sfs_config.admin.'.$action.'.success'];
            }
            if ('update' === $action) {
                $actions[$action]['form_prepare_event_name'] = 'sfs_config.admin.update.form_prepare';
            }
        }
        $container->prependExtensionConfig('sfs_crudl', ['controllers' => ['sfs_config' => [
            'entity_class' => ConfigOverride::class, 'actions' => $actions,
        ]]]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(SfsConfig::class, static function (ChildDefinition $definition, SfsConfig $attribute, Reflector $parameter): void {
            // PHP also exposes promoted parameter attributes on their properties.
            if ($parameter instanceof ReflectionProperty && $parameter->isPromoted()) {
                return;
            }
            if (!$parameter instanceof ReflectionParameter) {
                throw new LogicException('The SfsConfig attribute requires a constructor parameter.');
            }
            $method = $parameter->getDeclaringFunction();
            if (!$method instanceof ReflectionMethod || !$method->isConstructor()) {
                throw new LogicException('The SfsConfig attribute is only supported on constructor parameters.');
            }
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || !is_a(ConfigValue::class, $type->getName(), true)) {
                throw new LogicException(sprintf('SfsConfig parameter "$%s" must accept ConfigValueInterface or ConfigValue.', $parameter->getName()));
            }
            $definition->setArgument('$'.$parameter->getName(), new Definition(ConfigValue::class, [
                new Reference(ConfigProviderInterface::class), $attribute->key,
            ]));
        });
        $config = $this->processConfiguration(new Configuration(), $configs);
        $groups = array_replace(DefinitionRegistry::DEFAULT_GROUPS, $config['groups']);
        foreach (array_keys($groups) as $key) {
            if (!is_string($key) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/D', $key)) {
                throw new LogicException('Configuration group keys must start with a letter and contain only letters, digits, dots, underscores or hyphens.');
            }
        }
        foreach ($config['definitions'] as $key => $definition) {
            if (!isset($groups[$definition['group']])) {
                throw new LogicException(sprintf('Setting "%s" references unknown configuration group "%s".', $key, $definition['group']));
            }
            if (!is_string($key) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/D', $key) || !array_key_exists($key, $config['defaults'])) {
                throw new LogicException('Every registered setting needs a default and a key of at most 190 letters, digits, dots, underscores or hyphens.');
            }
        }
        $container->register(DefinitionRegistry::class)->setArgument('$definitions', $config['definitions'])->setArgument('$groups', $groups);
        $container->register('sfs_config.defaults_provider', DefaultsConfigProvider::class)->setArgument('$defaults', $config['defaults']);
        if ($config['admin']['enabled']) {
            $container->register('sfs_config.connection', Connection::class)
                ->setFactory([new Reference('doctrine.orm.entity_manager'), 'getConnection']);
            $container->register('sfs_config.provider', DoctrineConfigProvider::class)
                ->setArgument('$connection', new Reference('sfs_config.connection'))
                ->setArgument('$defaults', new Reference('sfs_config.defaults_provider'));
            $container->register(ConfigActionListener::class)->setAutowired(true)->setAutoconfigured(true)
                ->setArgument('$permissions', $config['admin']['permissions']);
            $container->register(ConfigValueForm::class)->setAutowired(true)->addTag('form.type');
        } else {
            $container->setAlias('sfs_config.provider', 'sfs_config.defaults_provider');
        }
        $container->setAlias(ConfigProviderInterface::class, 'sfs_config.provider');
        $container->register('sfs_config.env_var_processor', ConfigEnvVarProcessor::class)
            ->setArgument('$provider', new Reference(ConfigProviderInterface::class))->addTag('container.env_var_processor');
    }
}
