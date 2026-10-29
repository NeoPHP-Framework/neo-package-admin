<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

use DateTimeInterface;
use NeoPHP\Component\Form\Accessor\PropertyAccessor;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;
use NeoPHP\Package\Orm\Contract\ProxyInterface;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * @internal
 */
class ValueRenderer
{
    public const LABEL_FIELDS = ['name', 'title', 'label', 'username', 'email', 'slug'];

    public const DATE_FORMATS = ['date' => 'Y-m-d', 'date_immutable' => 'Y-m-d', 'time' => 'H:i', 'time_immutable' => 'H:i'];

    public const TO_MANY_LIMIT = 5;

    protected PropertyAccessor $accessor;

    public function __construct(protected OrmManagerInterface $orm, protected TranslationManagerInterface $translator, protected ?TranslationMapper $translations = null)
    {
        $this->accessor = new PropertyAccessor();
    }

    public function getTranslator(): TranslationManagerInterface
    {
        return $this->translator;
    }

    public function value(object $entity, FieldDefinition $field): mixed
    {
        $this->load($entity);

        if ($field->isMapped()) {
            return $this->orm->getMetadata($entity)->getValue($entity, $field->getName());
        }

        try {
            return $this->accessor->getValue($entity, $field->getName());
        } catch (Throwable) {
            return null;
        }
    }

    public function render(object $entity, FieldDefinition $field, string $page = 'index'): string
    {
        $value = $this->value($entity, $field);

        if ($field->getOption('type') === TranslationMapper::TYPE && is_iterable($value)) {
            return $this->translations($value, $field, $page);
        }

        if ($value === null || $value === '') {
            return '<span class="na-subtle">—</span>';
        }

        if (is_bool($value)) {
            return $value
                ? '<span class="na-badge na-badge-success">' . $this->e($this->trans('Yes')) . '</span>'
                : '<span class="na-badge">' . $this->e($this->trans('No')) . '</span>';
        }

        if ($value instanceof DateTimeInterface) {
            return '<time datetime="' . $this->e($value->format(DATE_ATOM)) . '">' . $this->e($value->format((string) $field->getOption('format', self::DATE_FORMATS[$field->getType()] ?? 'Y-m-d H:i'))) . '</time>';
        }

        if ($value instanceof UnitEnum) {
            return '<span class="na-badge na-badge-primary">' . $this->e($this->enum($value)) . '</span>';
        }

        if (is_array($value) || $field->getType() === 'json') {
            return '<code class="na-code">' . $this->e($this->truncate((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $page === 'index' ? 60 : null)) . '</code>';
        }

        if (is_iterable($value)) {
            return $this->collection($value, $field, $page);
        }

        if (is_object($value)) {
            return $this->e($this->label($value));
        }

        $text = is_scalar($value) ? (string) $value : get_debug_type($value);
        $truncate = $field->getOption('truncate', $page === 'index' ? 80 : null);

        if ($page !== 'index' && $field->getType() === 'text') {
            return '<div class="na-pre">' . $this->e($text) . '</div>';
        }

        return $this->e($this->truncate($text, $truncate !== null ? (int) $truncate : null));
    }

    public function label(object $entity): string
    {
        if ($entity instanceof Stringable) {
            return (string) $entity;
        }

        if ($entity instanceof UnitEnum) {
            return $this->enum($entity);
        }

        try {
            $metadata = $this->orm->getMetadata($entity);
        } catch (Throwable) {
            return get_debug_type($entity);
        }

        $this->load($entity);

        foreach (self::LABEL_FIELDS as $field) {
            if ($metadata->hasField($field)) {
                $value = $metadata->getValue($entity, $field);

                if (is_scalar($value) && (string) $value !== '') {
                    return (string) $value;
                }
            }
        }

        $short = substr((string) strrchr('\\' . $metadata->getName(), '\\'), 1);

        return $short . ' #' . (string) $metadata->getIdentifierValue($entity);
    }

    protected function collection(iterable $value, FieldDefinition $field, string $page): string
    {
        $labels = [];
        $count = 0;

        foreach ($value as $item) {
            ++$count;

            if (count($labels) < self::TO_MANY_LIMIT) {
                $labels[] = is_object($item) ? $this->label($item) : (is_scalar($item) ? (string) $item : get_debug_type($item));
            }
        }

        if ($count === 0) {
            return '<span class="na-subtle">—</span>';
        }

        if ($page === 'index') {
            return $this->e($this->trans('{count, plural, one {# item} other {# items}}', ['count' => $count]));
        }

        $html = '<ul class="na-tags">';

        foreach ($labels as $label) {
            $html .= '<li class="na-badge">' . $this->e($label) . '</li>';
        }

        if ($count > count($labels)) {
            $html .= '<li class="na-badge na-subtle">' . $this->e($this->trans('and {count} more', ['count' => $count - count($labels)])) . '</li>';
        }

        return $html . '</ul>';
    }

    protected function translations(iterable $rows, FieldDefinition $field, string $page): string
    {
        $this->translations ??= new TranslationMapper($this->orm, $this->translator);
        $config = $this->translations->resolve($field, $field->getName());
        $values = $this->translations->values($rows, $config);

        if ($page === 'index') {
            $name = (string) $field->getOption('field', array_key_first($config['fields']));
            $locale = isset($values[$this->translator->getLocale()][$name]) ? $this->translator->getLocale() : (string) array_key_first(array_filter($values, static fn (array $locale): bool => isset($locale[$name])));
            $text = $values[$locale][$name] ?? null;

            if ($text === null || $text === '') {
                return '<span class="na-subtle">—</span>';
            }

            $truncate = $field->getOption('truncate', 80);

            return $this->e($this->truncate(is_scalar($text) ? (string) $text : get_debug_type($text), $truncate !== null ? (int) $truncate : null));
        }

        $html = '';

        foreach ($config['locales'] as $locale => $label) {
            $items = '';

            foreach ($config['fields'] as $name => $options) {
                $text = $values[$locale][$name] ?? null;

                if ($text !== null && $text !== '') {
                    $items .= '<dt class="na-subtle">' . $this->e($this->trans((string) $options['label'])) . '</dt><dd>' . $this->e(is_scalar($text) ? (string) $text : get_debug_type($text)) . '</dd>';
                }
            }

            if ($items !== '') {
                $html .= '<div><span class="na-badge na-badge-primary">' . $this->e($label) . '</span><dl>' . $items . '</dl></div>';
            }
        }

        return $html !== '' ? '<div class="na-stack">' . $html . '</div>' : '<span class="na-subtle">—</span>';
    }

    protected function enum(UnitEnum $value): string
    {
        if (method_exists($value, 'label')) {
            return (string) $value->label();
        }

        return $value->name;
    }

    protected function load(object $entity): void
    {
        if ($entity instanceof ProxyInterface && !$entity->__neoIsInitialized()) {
            $entity->__neoLoad();
        }
    }

    protected function truncate(string $text, ?int $length): string
    {
        return $length !== null && mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
    }

    protected function trans(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters, 'admin');
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}