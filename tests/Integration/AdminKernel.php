<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\SfsConfigBundle;
use Softspring\Component\DynamicFormType\SfsDynamicFormTypeBundle;
use Softspring\CrudlBundle\SfsCrudlBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class AdminKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $directory)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new SfsDynamicFormTypeBundle();
        yield new SfsCrudlBundle();
        yield new SfsConfigBundle();
    }

    public function getProjectDir(): string
    {
        return $this->directory;
    }

    public function getCacheDir(): string
    {
        return $this->directory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory.'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test-only', 'test' => true, 'form' => true,
            'csrf_protection' => true, 'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'translator' => ['fallbacks' => ['en'], 'paths' => [__DIR__.'/Fixtures/translations']], 'validation' => ['enabled' => true]]);
        $container->extension('security', [
            'role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_CONFIG_READ']],
            'providers' => ['users' => ['memory' => ['users' => [
                'admin' => ['password' => 'unused', 'roles' => ['ROLE_ADMIN']],
                'reader' => ['password' => 'unused', 'roles' => ['ROLE_CONFIG_READ']],
                'user' => ['password' => 'unused', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['provider' => 'users', 'http_basic' => []]],
        ]);
        $container->extension('doctrine', ['dbal' => ['default_connection' => 'configuration', 'connections' => ['configuration' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
            'orm' => ['auto_mapping' => false]]);
        $container->extension('twig', ['default_path' => dirname(__DIR__, 2).'/templates']);
        $container->extension('sfs_config', [
            'defaults' => ['instructions' => 'default instructions', 'restricted' => 'restricted', 'secret' => 'synthetic secret', 'mailer.sender_name' => 'Synthetic sender', 'private.setting' => 'private setting'],
            'groups' => [
                'cms' => ['label' => 'groups.cms.label', 'description' => 'groups.cms.description', 'translation_domain' => 'config_admin_test', 'priority' => 100],
                'mailer' => ['label' => 'groups.mailer.label', 'translation_domain' => 'config_admin_test', 'priority' => 50],
                'private' => ['label' => 'Private settings'],
                'empty' => ['label' => 'Empty settings'],
            ],
            'definitions' => [
                'instructions' => ['type' => 'text', 'label' => 'Instructions', 'group' => 'cms'],
                'restricted' => ['type' => 'text', 'permissions' => ['update' => 'ROLE_SUPER_ADMIN', 'delete' => 'ROLE_SUPER_ADMIN']],
                'secret' => ['sensitive' => true],
                'mailer.sender_name' => ['type' => 'text', 'group' => 'mailer', 'label' => 'Sender name'],
                'private.setting' => ['type' => 'text', 'group' => 'private', 'permissions' => ['read' => 'ROLE_SUPER_ADMIN']],
            ],
            'admin' => ['enabled' => true, 'permissions' => ['list' => 'ROLE_CONFIG_READ', 'read' => 'ROLE_CONFIG_READ']],
        ]);
        $container->services()->alias('test.provider', ConfigProviderInterface::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach (['cms', 'mailer', 'empty', 'missing'] as $group) {
            $routes->import(dirname(__DIR__, 2).'/config/routes/admin.yaml')->prefix('/settings/'.$group)
                ->namePrefix($group.'_')->defaults(['_sfs_config_group' => $group]);
        }
        $routes->import(dirname(__DIR__, 2).'/config/routes/admin.yaml')->prefix('/settings');
    }
}
