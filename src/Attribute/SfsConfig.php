<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class SfsConfig
{
    public function __construct(public readonly string $key)
    {
        if ('' === trim($key)) {
            throw new InvalidArgumentException('The SfsConfig key must not be empty.');
        }
    }
}
