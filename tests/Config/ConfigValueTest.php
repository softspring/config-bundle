<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Config;

use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\Config\ConfigValue;
use Softspring\Bundle\ConfigBundle\Config\DefaultsConfigProvider;
use Softspring\Bundle\ConfigBundle\Exception\ConfigNotFoundException;

class ConfigValueTest extends TestCase
{
    public function testDefaultsPreserveTypesAndNull(): void
    {
        $provider = new DefaultsConfigProvider(['enabled' => false, 'limit' => 0, 'optional' => null]);
        self::assertFalse($provider->get('enabled'));
        self::assertSame(0, $provider->get('limit'));
        self::assertNull($provider->get('optional'));
    }

    public function testMissingValuesFailExplicitly(): void
    {
        $this->expectException(ConfigNotFoundException::class);
        new DefaultsConfigProvider()->get('missing');
    }

    public function testValueReadsTheProviderOnEveryCall(): void
    {
        $provider = $this->createMock(ConfigProviderInterface::class);
        $provider->expects(self::exactly(2))->method('get')->with('mailchimp.api_key')
            ->willReturnOnConsecutiveCalls('first', 'second');
        $value = new ConfigValue($provider, 'mailchimp.api_key');

        self::assertSame('first', $value->get());
        self::assertSame('second', $value->get());
    }
}
