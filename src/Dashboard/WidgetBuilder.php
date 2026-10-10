<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Dashboard;

use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\NeoAdmin\Crud\ValueRenderer;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\NeoAdmin\Menu\MenuBuilder;
use NeoPHP\NeoAdmin\NeoAdminManagerInterface;
use NeoPHP\NeoAdmin\Page\Block\LinkList;
use NeoPHP\NeoAdmin\Page\Block\Stat;
use NeoPHP\NeoAdmin\Page\Block\Tile;
use NeoPHP\NeoAdmin\Page\Contract\BlockInterface;
use NeoPHP\NeoAdmin\Page\Contract\WidgetInterface;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;

/**
 * @internal
 */
class WidgetBuilder
{
    public const SPANS = ['small' => 1, 'large' => 2];

    public function __construct(
        protected NeoAdminManagerInterface $admin,
        protected OrmManagerInterface $orm,
        protected MenuBuilder $menu,
        protected ValueRenderer $values,
        protected TranslationManagerInterface $translator,
        protected ContainerManagerInterface $container,
    ) {
    }

    public function build(): array
    {
        $blocks = [];

        foreach ($this->admin->getWidgets() as $widget) {
            $block = $this->menu->isGranted($widget['role']) ? $this->widget($widget) : null;

            if ($block === null) {
                continue;
            }

            if ($widget['size'] !== null && method_exists($block, 'span')) {
                $block->span(self::SPANS[$widget['size']]);
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    public function tiles(): array
    {
        $tiles = [];

        foreach ($this->admin->getEntities() as $definition) {
            if ($this->can($definition, $definition->getDefault())) {
                $tiles[] = new Tile($this->trans($definition->getLabel()), $this->menu->url('admin_crud', ['entity' => $definition->getSlug()]), (string) $definition->getOption('icon', 'list'));
            }
        }

        return $tiles;
    }

    protected function widget(array $widget): ?BlockInterface
    {
        if ($widget['type'] === 'custom') {
            $instance = $this->container->get((string) $widget['widget']);

            if (!$instance instanceof WidgetInterface) {
                throw new NeoAdminException('The widget "{class}" must implement {interface}.', 0, null, ['class' => (string) $widget['widget'], 'interface' => WidgetInterface::class]);
            }

            return $instance->build();
        }

        if ($widget['type'] === 'links') {
            $links = [];

            foreach ($widget['links'] as $link) {
                if ($this->menu->isGranted($link['role'])) {
                    $links[] = [
                        'label' => $this->trans((string) $link['label']),
                        'icon' => $link['icon'] !== 'link' ? $link['icon'] : null,
                        'url' => $link['url'] ?? $this->menu->url((string) $link['route'], (array) $link['parameters']),
                        'target' => $link['target'],
                    ];
                }
            }

            return $links === [] ? null : new LinkList($this->trans((string) $widget['label']), $links, $widget['icon'] ?? 'link');
        }

        $definition = $this->admin->getEntity((string) $widget['entity']);

        if (!$this->menu->isGranted($definition->getRole())) {
            return null;
        }

        $label = $this->trans((string) ($widget['label'] ?? $definition->getLabel()));
        $icon = $widget['icon'] ?? (string) $definition->getOption('icon', 'list');
        $index = $this->can($definition, 'index') ? $this->menu->url('admin_crud_index', ['entity' => $definition->getSlug()]) : null;
        $repository = $this->orm->getRepository($definition->getClass());

        if ($widget['type'] === 'count') {
            return new Stat($label, $repository->count(), $icon, null, null, $index);
        }

        $metadata = $this->orm->getMetadata($definition->getClass());
        $sort = [];

        foreach ($widget['sort'] !== [] ? $widget['sort'] : [$metadata->getIdentifier() => 'desc'] as $field => $direction) {
            if ($metadata->hasField((string) $field)) {
                $sort[(string) $field] = strtolower((string) $direction) === 'asc' ? 'ASC' : 'DESC';
            }
        }

        $action = $this->can($definition, 'show') ? 'admin_crud_show' : ($this->can($definition, 'edit') ? 'admin_crud_edit' : null);
        $links = [];

        foreach ($repository->findBy([], $sort, (int) $widget['limit']) as $entity) {
            $links[] = [
                'label' => $this->values->label($entity),
                'url' => $action !== null ? $this->menu->url($action, ['entity' => $definition->getSlug(), 'id' => (string) $metadata->getIdentifierValue($entity)]) : null,
            ];
        }

        return new LinkList($label, $links, $icon, null, $index);
    }

    protected function can(EntityDefinition $definition, string $action): bool
    {
        return $definition->hasAction($action) && $this->menu->isGranted($definition->getRole()) && $this->menu->isGranted($definition->getActionRole($action));
    }

    protected function trans(string $key): string
    {
        return $this->translator->translate($key, [], 'admin');
    }
}