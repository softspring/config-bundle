<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Integration;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Attribute\SfsConfig;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\Config\ConfigValueInterface;
use Softspring\Bundle\ConfigBundle\DependencyInjection\SfsConfigExtension;
use Softspring\Bundle\ConfigBundle\Exception\ConfigNotFoundException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ConfigInjectionTest extends TestCase
{
    public function testAttributeAndYamlKeepReadingRuntimeValuesInADumpedContainer(): void
    {
        $container = $this->container();
        $container->register('test.attribute_consumer', AttributeConfigConsumer::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load('config_value_injection.yaml');
        $container->compile(true);
        $class = 'SfsConfigInjectionTestContainer'.bin2hex(random_bytes(6));
        $file = tempnam(sys_get_temp_dir(), 'sfs-config-injection-');
        self::assertIsString($file);
        try {
            file_put_contents($file, new PhpDumper($container)->dump(['class' => $class]));
            require $file;
            $runtime = new $class();
            $attribute = $runtime->get('test.attribute_consumer');
            $yaml = $runtime->get('test.yaml_consumer');
            $provider = $runtime->get('test.runtime_provider');
            self::assertSame(0, $provider->calls, 'Compiling and constructing consumers must not read settings.');
            self::assertSame('other', $attribute->other->get());
            foreach (['first', 'updated', false, 0, '', [], null] as $value) {
                $provider->values['dynamic.key'] = $value;
                self::assertSame($value, $attribute->value->get());
                self::assertSame($value, $yaml->value->get());
            }
            self::assertSame(15, $provider->calls);
            $this->expectException(ConfigNotFoundException::class);
            $attribute->missing->get();
        } finally {
            unlink($file);
        }
    }

    public function testAttributeRejectsScalarParametersDuringCompilation(): void
    {
        $container = $this->container();
        $container->register('test.invalid_consumer', ScalarConfigConsumer::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must accept ConfigValueInterface or ConfigValue');
        $container->compile(true);
    }

    public function testAttributeRejectsMethodParametersDuringCompilation(): void
    {
        $container = $this->container();
        $container->register('test.invalid_consumer', MethodConfigConsumer::class)->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('only supported on constructor parameters');
        $container->compile(true);
    }

    public function testAttributeRejectsEmptyKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SfsConfig(' ');
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        new SfsConfigExtension()->load([], $container);
        $container->register('test.runtime_provider', RuntimeConfigProvider::class)->setPublic(true);
        $container->setAlias('sfs_config.provider', 'test.runtime_provider');

        return $container;
    }
}

class AttributeConfigConsumer
{
    public function __construct(
        #[SfsConfig('dynamic.key')]
        public readonly ConfigValueInterface $value,
        #[SfsConfig('other.key')]
        public readonly ConfigValueInterface $other,
        #[SfsConfig('missing.key')]
        public readonly ConfigValueInterface $missing,
    ) {
    }
}

class YamlConfigConsumer
{
    public function __construct(public readonly ConfigValueInterface $value)
    {
    }
}

class ScalarConfigConsumer
{
    public function __construct(#[SfsConfig('dynamic.key')] public readonly string $value)
    {
    }
}

class MethodConfigConsumer
{
    public function configure(#[SfsConfig('dynamic.key')] ConfigValueInterface $value): void
    {
    }
}

class RuntimeConfigProvider implements ConfigProviderInterface
{
    public array $values = ['dynamic.key' => 'initial', 'other.key' => 'other'];
    public int $calls = 0;

    public function get(string $key): mixed
    {
        ++$this->calls;
        if (!array_key_exists($key, $this->values)) {
            throw new ConfigNotFoundException($key);
        }

        return $this->values[$key];
    }
}
