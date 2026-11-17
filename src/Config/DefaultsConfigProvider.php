<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

use Softspring\Bundle\ConfigBundle\Exception\ConfigNotFoundException;

class DefaultsConfigProvider implements ConfigProviderInterface
{
    public function __construct(private readonly array $defaults = [])
    {
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->defaults)) {
            throw new ConfigNotFoundException($key);
        }

        return $this->defaults[$key];
    }
}
