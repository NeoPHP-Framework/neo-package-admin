<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Definition;

class EntityDefinition
{
    public const ACTIONS = ['index', 'show', 'new', 'edit', 'delete'];

    public const DEFAULTS = ['index', 'new'];

    public function __construct(
        protected string $name,
        protected string $class,
        protected string $label,
        protected ?string $role = null,
        protected string $default = 'index',
        protected array $actions = self::ACTIONS,
        protected array $options = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSlug(): string
    {
        return self::slugify($this->name);
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getActionRole(string $action): ?string
    {
        $roles = (array) ($this->options['roles'] ?? []);

        return isset($roles[$action]) && $roles[$action] !== '' ? (string) $roles[$action] : null;
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions, true);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public static function slugify(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', str_replace(['\\', '/', '_', ' '], '-', $name)));
    }

    public static function humanize(string $name): string
    {
        return ucfirst(strtolower(trim((string) preg_replace(['/(?<=[a-z0-9])(?=[A-Z])/', '/[_\s]+/'], ' ', $name))));
    }
}