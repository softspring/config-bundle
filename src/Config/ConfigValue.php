<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

class ConfigValue implements ConfigValueInterface
{
    public function __construct(
        private readonly ConfigProviderInterface $provider,
        private readonly string $key,
    ) {
    }

    public function get(): mixed
    {
        return $this->provider->get($this->key);
    }
}
