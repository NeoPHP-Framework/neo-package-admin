<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Definition;

class FieldDefinition
{
    public const TO_MANY = ['OneToMany', 'ManyToMany'];

    public function __construct(
        protected string $name,
        protected string $label,
        protected string $type,
        protected array $options = [],
        protected ?array $mapping = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function getMapping(): ?array
    {
        return $this->mapping;
    }

    public function isMapped(): bool
    {
        return $this->mapping !== null;
    }

    public function isAssociation(): bool
    {
        return $this->type === 'association';
    }

    public function isToMany(): bool
    {
        return $this->isAssociation() && in_array($this->mapping['type'] ?? null, self::TO_MANY, true);
    }

    public function isSortable(): bool
    {
        return (bool) ($this->options['sortable'] ?? ($this->isMapped() && !$this->isAssociation() && $this->type !== 'json'));
    }
}