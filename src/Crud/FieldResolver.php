<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\Package\Orm\Metadata\ClassMetadata;
use NeoPHP\Package\Orm\OrmManagerInterface;

/**
 * @internal
 */
class FieldResolver
{
    public const PAGES = ['index', 'show', 'form'];

    public const INDEX_LIMIT = 7;

    public function __construct(protected OrmManagerInterface $orm)
    {
    }

    public function resolve(EntityDefinition $entity, string $page): array
    {
        $metadata = $this->orm->getMetadata($entity->getClass());
        $config = (array) $entity->getOption($page, []);
        $fields = $config['fields'] ?? $this->guess($metadata, $page);
        $definitions = [];

        foreach ((array) $fields as $key => $value) {
            [$name, $options] = is_int($key) ? [(string) $value, []] : [(string) $key, (array) ($value ?? [])];
            $definitions[] = $this->field($metadata, $entity, $name, $options, $page);
        }

        return $definitions;
    }

    public function search(EntityDefinition $entity): array
    {
        $metadata = $this->orm->getMetadata($entity->getClass());
        $config = (array) $entity->getOption('index', []);

        if (isset($config['search'])) {
            return array_values(array_filter(array_map('strval', (array) $config['search']), static fn (string $field): bool => $metadata->hasField($field)));
        }

        return $this->searchable($metadata);
    }

    public function searchable(ClassMetadata $metadata): array
    {
        $fields = [];

        foreach ($metadata->fields as $name => $mapping) {
            if (in_array($mapping['type'], ['string', 'text'], true) && $mapping['enumType'] === null) {
                $fields[] = (string) $name;
            }
        }

        return $fields;
    }

    public function guess(ClassMetadata $metadata, string $page): array
    {
        $fields = [];

        foreach ($metadata->fields as $name => $mapping) {
            $skip = match ($page) {
                'index' => in_array($mapping['type'], ['text', 'json'], true),
                'form' => ($mapping['id'] && $metadata->isIdGenerated()) || $mapping['type'] === 'json',
                default => false,
            };

            if (!$skip) {
                $fields[] = (string) $name;
            }
        }

        foreach ($metadata->associations as $name => $association) {
            $toMany = in_array($association['type'], FieldDefinition::TO_MANY, true);
            $skip = match ($page) {
                'index' => $toMany,
                'form' => !$association['owning'] || $association['type'] === 'OneToMany',
                default => false,
            };

            if (!$skip) {
                $fields[] = (string) $name;
            }
        }

        return $page === 'index' ? array_slice($fields, 0, self::INDEX_LIMIT) : $fields;
    }

    protected function field(ClassMetadata $metadata, EntityDefinition $entity, string $name, array $options, string $page): FieldDefinition
    {
        $label = (string) ($options['label'] ?? EntityDefinition::humanize($name));

        if ($metadata->hasField($name)) {
            $mapping = $metadata->getField($name);

            return new FieldDefinition($name, $label, $mapping['enumType'] !== null ? 'enum' : (string) $mapping['type'], $options, $mapping);
        }

        if ($metadata->hasAssociation($name)) {
            return new FieldDefinition($name, $label, 'association', $options, $metadata->getAssociation($name));
        }

        $class = $entity->getClass();
        $readable = property_exists($class, $name) || array_filter(['get', 'is', 'has'], static fn (string $prefix): bool => method_exists($class, $prefix . ucfirst($name))) !== [];

        if ($page === 'form' && !isset($options['type'])) {
            throw new NeoAdminException('The field "{field}" of the entity "{entity}" is not mapped by the ORM: give it a "type" in the "form" section of config/packages/admin/entities/{entity}.yaml.', 0, null, ['field' => $name, 'entity' => $entity->getName()]);
        }

        if (!$readable && $page !== 'form') {
            throw new NeoAdminException('The field "{field}" does not exist in the entity "{entity}" ({class}).', 0, null, ['field' => $name, 'entity' => $entity->getName(), 'class' => $class]);
        }

        return new FieldDefinition($name, $label, 'virtual', $options);
    }
}