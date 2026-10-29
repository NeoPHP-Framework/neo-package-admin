<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

use Locale;
use NeoPHP\Component\Form\Accessor\PropertyAccessor;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\Package\Orm\Exception\MappingException;
use NeoPHP\Package\Orm\Metadata\ClassMetadata;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;

/**
 * @internal
 */
class TranslationMapper
{
    public const TYPE = 'translations';

    public const DEFAULT_FIELDS = ['name'];

    public function __construct(protected OrmManagerInterface $orm, protected TranslationManagerInterface $translator)
    {
    }

    public function supports(FieldDefinition $field): bool
    {
        return $field->getOption('type') === self::TYPE;
    }

    public function resolve(FieldDefinition $field, string $entity): array
    {
        $mapping = (array) $field->getMapping();

        if (!$field->isAssociation() || ($mapping['type'] ?? null) !== 'OneToMany' || ($mapping['mappedBy'] ?? null) === null) {
            throw new NeoAdminException('The field "{field}" of the entity "{entity}" must be a OneToMany association with a "mappedBy" to use the "translations" type.', 0, null, ['field' => $field->getName(), 'entity' => $entity]);
        }

        $target = $this->orm->getMetadata((string) $mapping['target']);
        $localeProperty = (string) $field->getOption('locale_property', 'locale');
        $fieldProperty = array_key_exists('field_property', $field->getOptions())
            ? ($field->getOption('field_property') !== null ? (string) $field->getOption('field_property') : null)
            : ($target->hasField('field') ? 'field' : null);
        $valueProperty = $fieldProperty !== null ? (string) $field->getOption('value_property', 'value') : null;
        $locales = $this->locales($field->getOption('locales'));
        $fields = $this->fields($field->getOption('fields'), $target, $localeProperty, $fieldProperty !== null);

        if ($locales === []) {
            throw new NeoAdminException('The field "{field}" of the entity "{entity}" has no locale: set "locales" in config/packages/admin/entities/{entity}.yaml.', 0, null, ['field' => $field->getName(), 'entity' => $entity]);
        }

        foreach (array_filter([$localeProperty, $fieldProperty, $valueProperty]) as $property) {
            $this->assertField($target, $property, $field, $entity);
        }

        if ($fieldProperty === null) {
            foreach (array_keys($fields) as $property) {
                $this->assertField($target, (string) $property, $field, $entity);
            }
        }

        return [
            'target' => $target->name,
            'mapped_by' => (string) $mapping['mappedBy'],
            'orphan_removal' => (bool) ($mapping['orphanRemoval'] ?? false),
            'property' => $field->getName(),
            'locale_property' => $localeProperty,
            'field_property' => $fieldProperty,
            'value_property' => $valueProperty,
            'locales' => $locales,
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     * @throws MappingException
     */
    public function values(iterable $rows, array $config): array
    {
        $target = $this->orm->getMetadata((string) $config['target']);
        $values = [];

        foreach ($rows as $row) {
            $locale = (string) $target->getValue($row, (string) $config['locale_property']);

            if ($config['field_property'] !== null) {
                $values[$locale][(string) $target->getValue($row, (string) $config['field_property'])] = $target->getValue($row, (string) $config['value_property']);

                continue;
            }

            foreach (array_keys((array) $config['fields']) as $name) {
                $values[$locale][(string) $name] = $target->getValue($row, (string) $name);
            }
        }

        return $values;
    }

    /**
     * @param array<string, array<string, mixed>> $submitted
     * @throws MappingException
     */
    public function sync(object $owner, iterable $collection, array $submitted, array $config): void
    {
        $target = $this->orm->getMetadata((string) $config['target']);
        $rows = [];

        foreach ($collection as $row) {
            $locale = (string) $target->getValue($row, (string) $config['locale_property']);
            $key = $config['field_property'] !== null ? $locale . "\0" . (string) $target->getValue($row, (string) $config['field_property']) : $locale;
            $rows[$key] = $row;
        }

        foreach (array_keys((array) $config['locales']) as $locale) {
            $locale = (string) $locale;
            $values = array_map(self::normalize(...), (array) ($submitted[$locale] ?? []));

            if ($config['field_property'] !== null) {
                foreach (array_keys((array) $config['fields']) as $name) {
                    $name = (string) $name;
                    $row = $rows[$locale . "\0" . $name] ?? null;
                    $value = $values[$name] ?? null;

                    if ($value === null) {
                        if ($row !== null) {
                            $this->detach($owner, $collection, $row, $config);
                        }

                        continue;
                    }

                    if ($row === null) {
                        $row = $this->attach($owner, $collection, $target, $config, $locale);
                        $target->setValue($row, (string) $config['field_property'], $name);
                    }

                    $target->setValue($row, (string) $config['value_property'], $value);
                }

                continue;
            }

            $row = $rows[$locale] ?? null;

            if (array_filter($values, static fn (mixed $value): bool => $value !== null) === []) {
                if ($row !== null) {
                    $this->detach($owner, $collection, $row, $config);
                }

                continue;
            }

            $row ??= $this->attach($owner, $collection, $target, $config, $locale);

            foreach (array_keys((array) $config['fields']) as $name) {
                $name = (string) $name;
                $value = $values[$name] ?? null;

                if ($value !== null || ($target->getField($name)['nullable'] ?? false)) {
                    $target->setValue($row, $name, $value);
                }
            }
        }
    }

    protected function attach(object $owner, iterable $collection, ClassMetadata $target, array $config, string $locale): object
    {
        $row = $target->newInstance();
        $target->setValue($row, (string) $config['locale_property'], $locale);
        $target->setValue($row, (string) $config['mapped_by'], $owner);
        $adder = 'add' . ucfirst(PropertyAccessor::singularize((string) $config['property']));

        if (method_exists($owner, $adder)) {
            $owner->$adder($row);
        } elseif (is_object($collection) && method_exists($collection, 'add')) {
            $collection->add($row);
        }

        $this->orm->persist($row);

        return $row;
    }

    protected function detach(object $owner, iterable $collection, object $row, array $config): void
    {
        $remover = 'remove' . ucfirst(PropertyAccessor::singularize((string) $config['property']));

        if (method_exists($owner, $remover)) {
            $owner->$remover($row);
        } elseif (is_object($collection) && method_exists($collection, 'removeElement')) {
            $collection->removeElement($row);
        }

        if (!$config['orphan_removal']) {
            $this->orm->remove($row);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function locales(mixed $locales): array
    {
        $locales ??= $this->translator->getLocales();
        $resolved = [];

        foreach ((array) $locales as $key => $label) {
            $code = is_int($key) ? (string) $label : (string) $key;
            $resolved[$code] = is_int($key) || $label === null ? self::localeName($code) : (string) $label;
        }

        return $resolved;
    }

    public static function localeName(string $locale): string
    {
        if (!class_exists(Locale::class)) {
            return strtoupper($locale);
        }

        $name = Locale::getDisplayName($locale, $locale);

        return $name !== '' && $name !== $locale ? mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1) : strtoupper($locale);
    }

    /**
     * @return array<string, array<string, mixed>>
     * @throws MappingException
     */
    protected function fields(mixed $fields, ClassMetadata $target, string $localeProperty, bool $keyValue): array
    {
        if ($fields === null) {
            $fields = $keyValue ? self::DEFAULT_FIELDS : $this->guessFields($target, $localeProperty);
        }

        $resolved = [];

        foreach ((array) $fields as $key => $options) {
            [$name, $options] = is_int($key) ? [(string) $options, []] : [(string) $key, (array) ($options ?? [])];
            $options['label'] ??= EntityDefinition::humanize($name);

            if (!$keyValue && !isset($options['type']) && ($target->getField($name)['type'] ?? null) === 'text') {
                $options['type'] = 'textarea';
            }

            $resolved[$name] = $options;
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    protected function guessFields(ClassMetadata $target, string $localeProperty): array
    {
        $fields = [];

        foreach ($target->fields as $name => $mapping) {
            if ($name !== $localeProperty && !$mapping['id'] && in_array($mapping['type'], ['string', 'text'], true) && $mapping['enumType'] === null) {
                $fields[] = (string) $name;
            }
        }

        return $fields;
    }

    protected function assertField(ClassMetadata $target, string $property, FieldDefinition $field, string $entity): void
    {
        if (!$target->hasField($property)) {
            throw new NeoAdminException('The field "{field}" of the entity "{entity}" uses the property "{property}", which is not mapped in {target}: check the "translations" options in config/packages/admin/entities/{entity}.yaml.', 0, null, ['field' => $field->getName(), 'entity' => $entity, 'property' => $property, 'target' => $target->name]);
        }
    }

    protected static function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}