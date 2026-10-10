<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page;

use DateTimeImmutable;
use DateTimeInterface;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\NeoAdmin\Menu\MenuBuilder;
use NumberFormatter;
use Throwable;

final class PageContext
{
    public function __construct(
        protected Request $request,
        protected MenuBuilder $menu,
        protected string $locale = 'en',
        protected bool $framed = true,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function isFramed(): bool
    {
        return $this->framed;
    }

    public function nested(): self
    {
        $context = clone $this;
        $context->framed = false;

        return $context;
    }

    public function url(array $query = []): string
    {
        $parameters = array_filter(array_replace($this->request->query->all(), $query), static fn (mixed $value): bool => $value !== null && $value !== '');
        $query = http_build_query($parameters);

        return $this->request->getRequestPath() . ($query !== '' ? '?' . $query : '');
    }

    public function route(string $name, array $parameters = []): string
    {
        return $this->menu->url($name, $parameters);
    }

    public function isGranted(?string $role): bool
    {
        return $this->menu->isGranted($role);
    }

    public function formatNumber(int|float $value, int $decimals = 0): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format($value);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return number_format($value, $decimals, '.', ',');
    }

    public function formatDate(mixed $value, string $format): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        try {
            return (new DateTimeImmutable((string) $value))->format($format);
        } catch (Throwable) {
            return (string) $value;
        }
    }

    public static function isDate(mixed $value): bool
    {
        return $value instanceof DateTimeInterface || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $value) === 1);
    }
}