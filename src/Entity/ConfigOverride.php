<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sfs_config_value')]
class ConfigOverride
{
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', type: 'string', length: 190)]
    private string $id;

    #[ORM\Column(name: 'setting_value', type: 'json', nullable: true)]
    private mixed $value;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $key, mixed $value = null)
    {
        $this->id = $key;
        $this->setValue($value);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
