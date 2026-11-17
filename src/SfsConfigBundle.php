<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SfsConfigBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
