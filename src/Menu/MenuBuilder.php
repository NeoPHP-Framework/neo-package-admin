<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Menu;

use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\NeoAdmin\NeoAdminManagerInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;

/**
 * @internal
 */
class MenuBuilder
{
    public function __construct(
        protected NeoAdminManagerInterface $admin,
        protected RoutingManagerInterface $routing,
        protected SecurityManagerInterface $security,
    ) {
    }

    public function build(?string $route = null, ?string $entity = null): array
    {
        return $this->items($this->admin->getMenu(), $route, $entity);
    }

    public function url(string $route, array $parameters = []): string
    {
        return $this->routing->getRoutes()->has($route) ? $this->routing->generate($route, $parameters) : '#';
    }

    public function isGranted(?string $role): bool
    {
        return $role === null || $this->security->isGranted($role);
    }

    protected function items(array $items, ?string $route, ?string $entity): array
    {
        $built = [];

        foreach ($items as $item) {
            $menuItem = $this->item($item, $route, $entity);

            if ($menuItem !== null) {
                $built[] = $menuItem;
            }
        }

        $pruned = [];

        foreach ($built as $index => $menuItem) {
            $next = $built[$index + 1] ?? null;

            if ($menuItem->isSection() && ($next === null || $next->isSection())) {
                continue;
            }

            $pruned[] = $menuItem;
        }

        return $pruned;
    }

    protected function item(array $item, ?string $route, ?string $entity): ?MenuItem
    {
        $type = (string) $item['type'];
        $label = $item['label'] !== null ? (string) $item['label'] : null;
        $icon = $item['icon'] !== null ? (string) $item['icon'] : null;
        $role = $item['role'] !== null ? (string) $item['role'] : null;
        $badge = $item['badge'] !== null ? (string) $item['badge'] : null;
        $target = $item['target'] !== null ? (string) $item['target'] : null;

        if ($type === 'entity') {
            $definition = $this->admin->getEntity((string) $item['entity']);

            if (!$this->isGranted($role ?? $definition->getRole())) {
                return null;
            }

            return new MenuItem($type, $label ?? $definition->getLabel(), $icon, $this->url('admin_crud', ['entity' => $definition->getSlug()]), $entity === $definition->getSlug(), $badge, $target);
        }

        if (!$this->isGranted($role)) {
            return null;
        }

        if ($type === 'submenu') {
            $children = $this->items((array) $item['children'], $route, $entity);

            return $children === [] ? null : new MenuItem($type, $label, $icon, null, false, $badge, null, $children);
        }

        if ($type === 'section') {
            return new MenuItem($type, $label, $icon);
        }

        if ($type === 'link') {
            $linkRoute = $item['route'] !== null ? (string) $item['route'] : null;
            $url = $item['url'] !== null ? (string) $item['url'] : $this->url((string) $linkRoute, (array) $item['parameters']);

            return new MenuItem($type, $label, $icon, $url, $linkRoute !== null && $linkRoute === $route, $badge, $target);
        }

        return new MenuItem($type, $label, $icon, $this->url('admin_dashboard'), $route === 'admin_dashboard', $badge, $target);
    }
}