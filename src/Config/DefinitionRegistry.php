<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Config;

use Softspring\Bundle\ConfigBundle\Exception\ConfigNotFoundException;

final class DefinitionRegistry
{
    public const DEFAULT_GROUPS = ['general' => [
        'label' => 'groups.general.label', 'description' => null,
        'translation_domain' => 'sfs_config', 'priority' => -100,
    ]];

    public function __construct(
        private readonly array $definitions = [],
        private readonly array $groups = self::DEFAULT_GROUPS,
    ) {
    }

    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $key): array
    {
        return $this->definitions[$key] ?? throw new ConfigNotFoundException($key);
    }

    public function groups(): array
    {
        $groups = $this->groups;
        uksort($groups, fn (string $first, string $second): int => ($groups[$second]['priority'] <=> $groups[$first]['priority']) ?: strcmp($first, $second));

        return $groups;
    }
}
