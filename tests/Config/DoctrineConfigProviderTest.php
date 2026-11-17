<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Config;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Config\DefaultsConfigProvider;
use Softspring\Bundle\ConfigBundle\Config\DoctrineConfigProvider;

class DoctrineConfigProviderTest extends TestCase
{
    public function testOverridesRetainFalsyValuesAndRefreshAfterSaveAndReset(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE sfs_config_value (setting_key VARCHAR(190) PRIMARY KEY, setting_value CLOB NULL)');
        $provider = new DoctrineConfigProvider($connection, new DefaultsConfigProvider(['key' => 'fallback']));
        self::assertSame('fallback', $provider->get('key'));
        foreach ([false, 0, '', [], null, 'updated'] as $value) {
            $connection->delete('sfs_config_value', ['setting_key' => 'key']);
            $connection->insert('sfs_config_value', ['setting_key' => 'key', 'setting_value' => json_encode($value, JSON_THROW_ON_ERROR)]);
            self::assertSame($value, $provider->get('key'));
        }
        $connection->delete('sfs_config_value', ['setting_key' => 'key']);
        self::assertSame('fallback', $provider->get('key'));
        $connection->close();
    }
}
