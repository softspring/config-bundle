<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

interface ConfigValueInterface
{
    public function get(): mixed;
}
