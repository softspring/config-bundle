<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Exception;

use RuntimeException;

class ConfigNotFoundException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('Configuration "%s" is not defined.', $key));
    }
}
