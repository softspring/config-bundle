<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\DependencyInjection\EnvVarProcessor;

use Closure;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class ConfigEnvVarProcessor implements EnvVarProcessorInterface
{
    public function __construct(private readonly ConfigProviderInterface $provider)
    {
    }

    public function getEnv(string $prefix, string $name, Closure $getEnv): mixed
    {
        return $this->provider->get(str_replace(':', '.', $name));
    }

    public static function getProvidedTypes(): array
    {
        return ['sfs_config' => 'string|int|float|bool|array'];
    }
}
