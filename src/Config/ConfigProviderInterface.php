<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

interface ConfigProviderInterface
{
    public function get(string $key): mixed;
}
