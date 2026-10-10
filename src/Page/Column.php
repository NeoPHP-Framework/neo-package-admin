<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page;

use ArrayAccess;
use DateTimeInterface;
use NeoPHP\Component\Form\Accessor\PropertyAccessor;
use Stringable;
use Throwable;
use UnitEnum;

final class Column
{
    public const TYPES = ['text', 'number', 'date', 'bool', 'badge', 'code', 'link'];

    protected array $options = [];

    protected static ?PropertyAccessor $accessor = null;

    public function __construct(protected string $key, protected string $label, protected ?string $type = null)
    {
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'text');
    }

    public static function number(string $key, string $label, int $decimals = 0): self
    {
        return (new self($key, $label, 'number'))->option('decimals', $decimals);
    }

    public static function date(string $key, string $label, string $format = 'Y-m-d H:i'): self
    {
        return (new self($key, $label, 'date'))->option('format', $format);
    }

    public static function bool(string $key, string $label): self
    {
        return new self($key, $label, 'bool');
    }

    public static function badge(string $key, string $label, array $variants = []): self
    {
        return (new self($key, $label, 'badge'))->option('variants', $variants);
    }

    public static function code(string $key, string $label): self
    {
        return new self($key, $label, 'code');
    }

    public static function link(string $key, string $label, callable|string|null $url = null): self
    {
        return (new self($key, $label, 'link'))->url($url ?? $key);
    }

    public function url(callable|string $url): self
    {
        return $this->option('url', $url);
    }

    public function truncate(?int $length): self
    {
        return $this->option('truncate', $length);
    }

    public function blank(): self
    {
        return $this->option('target', '_blank');
    }

    public function option(string $name, mixed $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function withType(string $type): self
    {
        $column = clone $this;
        $column->type = in_array($type, self::TYPES, true) ? $type : 'text';

        return $column;
    }

    public function isNumeric(): bool
    {
        return $this->type === 'number';
    }

    public function value(mixed $row): mixed
    {
        return self::read($row, $this->key);
    }

    public function guess(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value), is_float($value) => 'number',
            $value instanceof DateTimeInterface => 'date',
            $value instanceof UnitEnum => 'badge',
            default => 'text',
        };
    }

    public function cell(mixed $row, PageContext $context): array
    {
        $value = $this->value($row);
        $cell = ['text' => '', 'title' => null, 'url' => null, 'target' => null, 'badge' => null, 'code' => false, 'empty' => false, 'translate' => false];

        if ($value === null || $value === '') {
            return ['empty' => true] + $cell;
        }

        switch ($this->type) {
            case 'number':
                $cell['text'] = is_numeric($value) ? $context->formatNumber(+$value, (int) ($this->options['decimals'] ?? 0)) : self::string($value);
                break;
            case 'date':
                $cell['text'] = $context->formatDate($value, (string) ($this->options['format'] ?? 'Y-m-d H:i'));
                break;
            case 'bool':
                $cell['text'] = $value ? 'Yes' : 'No';
                $cell['badge'] = $value ? 'success' : 'neutral';
                $cell['translate'] = true;
                break;
            case 'badge':
                $text = $value instanceof UnitEnum ? $value->name : self::string($value);
                $variants = (array) ($this->options['variants'] ?? []);
                $cell['text'] = $text;
                $cell['badge'] = (string) ($variants[$text] ?? 'primary');
                break;
            default:
                $text = self::string($value);
                $length = $this->options['truncate'] ?? null;
                $cell['title'] = $text;
                $cell['text'] = is_int($length) && mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
                $cell['code'] = $this->type === 'code';
        }

        if (isset($this->options['url'])) {
            $url = $this->options['url'];
            $cell['url'] = is_callable($url) && !is_string($url) ? $url($row) : self::read($row, (string) $url);
            $cell['url'] = $cell['url'] !== null && $cell['url'] !== '' ? self::string($cell['url']) : null;
            $cell['target'] = $this->options['target'] ?? null;
        }

        return $cell;
    }

    public static function read(mixed $row, string $key): mixed
    {
        if (is_array($row) || $row instanceof ArrayAccess) {
            return $row[$key] ?? null;
        }

        if (!is_object($row)) {
            return null;
        }

        try {
            return (self::$accessor ??= new PropertyAccessor())->getValue($row, $key);
        } catch (Throwable) {
            return null;
        }
    }

    protected static function string(mixed $value): string
    {
        return match (true) {
            is_scalar($value), $value instanceof Stringable => (string) $value,
            $value instanceof UnitEnum => $value->name,
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => get_debug_type($value),
        };
    }
}