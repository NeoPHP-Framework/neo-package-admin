<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page;

final class Action
{
    public const VARIANTS = ['primary', 'secondary', 'danger', 'ghost'];

    public function __construct(
        protected string $label,
        protected string $url,
        protected ?string $icon = null,
        protected string $variant = 'secondary',
        protected string $method = 'GET',
        protected ?string $token = null,
        protected ?string $confirm = null,
        protected ?string $target = null,
    ) {
    }

    public static function link(string $label, string $url, ?string $icon = null): self
    {
        return new self($label, $url, $icon);
    }

    public static function post(string $label, string $url, string $token, ?string $confirm = null, ?string $icon = null): self
    {
        return new self($label, $url, $icon, 'secondary', 'POST', $token, $confirm);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function primary(): self
    {
        $this->variant = 'primary';

        return $this;
    }

    public function danger(): self
    {
        $this->variant = 'danger';

        return $this;
    }

    public function ghost(): self
    {
        $this->variant = 'ghost';

        return $this;
    }

    public function confirm(?string $message): self
    {
        $this->confirm = $message;

        return $this;
    }

    public function target(?string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function blank(): self
    {
        return $this->target('_blank');
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'variant' => in_array($this->variant, self::VARIANTS, true) ? $this->variant : 'secondary',
            'method' => strtoupper($this->method),
            'token' => $this->token,
            'confirm' => $this->confirm,
            'target' => $this->target,
        ];
    }
}