<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Package\Orm\Query\QueryBuilder;

/**
 * @internal
 */
class EntityQuery
{
    public function __construct(protected OrmManagerInterface $orm, protected FieldResolver $fields)
    {
    }

    public function paginate(EntityDefinition $entity, array $fields, string $search, ?string $sort, string $direction, int $page, int $perPage): Paginator
    {
        $metadata = $this->orm->getMetadata($entity->getClass());
        $total = (int) $this->filter($entity, $search)->select('COUNT(e.' . $metadata->getIdentifier() . ')')->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $sortable = [];

        foreach ($fields as $field) {
            if ($field instanceof FieldDefinition && $field->isSortable()) {
                $sortable[] = $field->getName();
            }
        }

        $builder = $this->filter($entity, $search);

        if ($sort !== null && in_array($sort, $sortable, true)) {
            $builder->orderBy('e.' . $sort, strtoupper($direction));
        } else {
            $sort = null;
            $default = (array) (((array) $entity->getOption('index', []))['sort'] ?? [$metadata->getIdentifier() => 'desc']);

            foreach ($default as $field => $order) {
                if ($metadata->hasField((string) $field)) {
                    $builder->addOrderBy('e.' . $field, strtolower((string) $order) === 'desc' ? 'DESC' : 'ASC');
                }
            }
        }

        $items = $builder->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getResult();

        return new Paginator(array_values($items), $page, $perPage, $total, $sort, $direction);
    }

    protected function filter(EntityDefinition $entity, string $search): QueryBuilder
    {
        $builder = $this->orm->getRepository($entity->getClass())->createQueryBuilder('e');
        $fields = $search !== '' ? $this->fields->search($entity) : [];

        if ($fields !== []) {
            $builder->andWhere('(' . implode(' OR ', array_map(static fn (string $field): string => 'e.' . $field . ' LIKE :search', $fields)) . ')')
                ->setParameter('search', '%' . $search . '%');
        }

        return $builder;
    }
}