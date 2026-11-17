<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

use Doctrine\DBAL\Connection;

final class DoctrineConfigProvider implements ConfigProviderInterface
{
    public function __construct(private readonly Connection $connection, private readonly ConfigProviderInterface $defaults)
    {
    }

    public function get(string $key): mixed
    {
        // A fresh read avoids retaining stale overrides in workers or across requests.
        $row = $this->connection->fetchAssociative('SELECT setting_value FROM sfs_config_value WHERE setting_key = ?', [$key]);
        if (false === $row) {
            return $this->defaults->get($key);
        }

        return null === $row['setting_value'] ? null : json_decode($row['setting_value'], true, 512, JSON_THROW_ON_ERROR);
    }
}
