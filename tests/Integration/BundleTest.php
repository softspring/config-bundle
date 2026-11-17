<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\SfsConfigBundle;
use stdClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class BundleTest extends TestCase
{
    public function testBundleBootsWithRuntimeValuesInADumpedContainer(): void
    {
        $kernel = new class('test', false) extends Kernel {
            use MicroKernelTrait;

            private string $directory;

            public function __construct(string $environment, bool $debug)
            {
                parent::__construct($environment, $debug);
                $this->directory = sys_get_temp_dir().'/sfs-config-test-'.bin2hex(random_bytes(8));
            }

            public function registerBundles(): iterable
            {
                yield new FrameworkBundle();
                yield new SfsConfigBundle();
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
                $container->extension('framework', ['secret' => 'test-only']);
                $container->parameters()->set('env(SFS_CONFIG_TEST_KEY)', 'synthetic-key');
                $container->extension('sfs_config', ['defaults' => [
                    'mailchimp.api_key' => '%env(SFS_CONFIG_TEST_KEY)%',
                    'feature.enabled' => false,
                    'feature.limit' => 0,
                    'feature.options' => ['one', 'two'],
                    'feature.optional' => null,
                ]]);
                $container->services()->alias('test.provider', ConfigProviderInterface::class)->public();
                $container->services()->set('test.consumer', stdClass::class)->public()
                    ->property('apiKey', '%env(sfs_config:mailchimp:api_key)%')
                    ->property('enabled', '%env(sfs_config:feature:enabled)%')
                    ->property('limit', '%env(sfs_config:feature:limit)%')
                    ->property('options', '%env(sfs_config:feature:options)%')
                    ->property('optional', '%env(sfs_config:feature:optional)%');
            }
        };

        $previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        try {
            $kernel->boot();
            $consumer = $kernel->getContainer()->get('test.consumer');
            self::assertInstanceOf(stdClass::class, $consumer);
            self::assertSame('synthetic-key', $consumer->apiKey);
            self::assertFalse($consumer->enabled);
            self::assertSame(0, $consumer->limit);
            self::assertSame(['one', 'two'], $consumer->options);
            self::assertNull($consumer->optional);
            $provider = $kernel->getContainer()->get('test.provider');
            self::assertInstanceOf(ConfigProviderInterface::class, $provider);
            self::assertSame('synthetic-key', $provider->get('mailchimp.api_key'));
        } finally {
            $kernel->shutdown();
            new Filesystem()->remove(dirname($kernel->getCacheDir()));
            $currentExceptionHandler = set_exception_handler(null);
            restore_exception_handler();
            if ($currentExceptionHandler !== $previousExceptionHandler) {
                restore_exception_handler();
            }
        }
    }
}
