<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Menu;

use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\NeoAdmin\Menu\Contract\BadgeInterface;
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
        protected ?ContainerManagerInterface $container = null,
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
        $variant = isset($item['badge_variant']) ? (string) $item['badge_variant'] : null;
        $target = $item['target'] !== null ? (string) $item['target'] : null;

        if ($type === 'entity') {
            $definition = $this->admin->getEntity((string) $item['entity']);

            if (!$this->isGranted($role ?? $definition->getRole())) {
                return null;
            }

            return new MenuItem($type, $label ?? $definition->getLabel(), $icon, $this->url('admin_crud', ['entity' => $definition->getSlug()]), $entity === $definition->getSlug(), $this->badge($badge), $target, [], $variant);
        }

        if (!$this->isGranted($role)) {
            return null;
        }

        if ($type === 'submenu') {
            $children = $this->items((array) $item['children'], $route, $entity);

            return $children === [] ? null : new MenuItem($type, $label, $icon, null, false, $this->badge($badge), null, $children, $variant);
        }

        if ($type === 'section') {
            return new MenuItem($type, $label, $icon);
        }

        if ($type === 'link') {
            $linkRoute = $item['route'] !== null ? (string) $item['route'] : null;
            $url = $item['url'] !== null ? (string) $item['url'] : $this->url((string) $linkRoute, (array) $item['parameters']);

            return new MenuItem($type, $label, $icon, $url, $linkRoute !== null && $linkRoute === $route, $this->badge($badge), $target, [], $variant);
        }

        return new MenuItem($type, $label, $icon, $this->url('admin_dashboard'), $route === 'admin_dashboard', $this->badge($badge), $target, [], $variant);
    }

    protected function badge(?string $badge): ?string
    {
        if ($badge === null || $this->container === null || !str_contains($badge, '\\') || !class_exists($badge)) {
            return $badge;
        }

        $instance = $this->container->get($badge);

        if (!$instance instanceof BadgeInterface) {
            throw new NeoAdminException('The badge "{class}" must implement {interface}.', 0, null, ['class' => $badge, 'interface' => BadgeInterface::class]);
        }

        $value = $instance->getValue();

        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        return is_int($value) && $value > 99 ? '99+' : (string) $value;
    }
}