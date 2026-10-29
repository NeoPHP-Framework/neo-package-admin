<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin;

use NeoPHP\Component\Form\FormManager;
use NeoPHP\Component\Kernel\Attribute\Package;
use NeoPHP\Component\Routing\RoutingManager;
use NeoPHP\Component\View\ViewManager;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\NeoAdmin\Page\Contract\WidgetInterface;
use NeoPHP\NeoAdmin\Provider\NeoAdminProvider;
use NeoPHP\Package\Orm\OrmManager;
use NeoPHP\Package\Security\SecurityManager;
use NeoPHP\Package\Translation\TranslationManager;

#[Package(provider: NeoAdminProvider::class, requires: [ViewManager::class, RoutingManager::class, SecurityManager::class, OrmManager::class, FormManager::class, TranslationManager::class])]
final class NeoAdminManager implements NeoAdminManagerInterface
{
    public const CONFIG_KEY = 'packages.admin';

    public const DEFAULT_CONFIG = [
        'title' => 'Administration',
        'role' => 'ROLE_ADMIN',
        'per_page' => 25,
        'entity_namespace' => 'App\\Entity',
        'menu' => [['type' => 'dashboard']],
        'dashboard' => ['widgets' => []],
        'assets' => ['stylesheets' => [], 'javascripts' => []],
        'locale_provider' => null,
    ];

    public const BADGE_VARIANTS = ['primary', 'success', 'warning', 'danger', 'info'];

    public const MENU_TYPES = ['dashboard', 'entity', 'submenu', 'link', 'section'];

    public const MENU_ICONS = ['dashboard' => 'home', 'entity' => 'list', 'submenu' => 'folder', 'link' => 'link', 'section' => null];

    public const ENTITY_KEYS = ['class', 'label', 'role', 'default', 'actions'];

    public const WIDGET_TYPES = ['count', 'latest', 'links', 'custom'];

    public const WIDGET_SIZES = ['small', 'large'];

    protected array $config;

    protected ?array $menu = null;

    protected ?array $widgets = null;

    protected array $definitions = [];

    public function __construct(array $config = [], protected array $entities = [])
    {
        $this->config = array_replace(static::DEFAULT_CONFIG, $config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getTitle(): string
    {
        return (string) $this->config['title'];
    }

    public function getRole(): string
    {
        return (string) $this->config['role'];
    }

    public function getPerPage(): int
    {
        return max(1, (int) $this->config['per_page']);
    }

    public function getMenu(): array
    {
        if ($this->menu !== null) {
            return $this->menu;
        }

        $menu = [];

        foreach (array_values((array) $this->config['menu']) as $index => $item) {
            $menu[] = $this->normalizeItem($item, 'menu[' . $index . ']');
        }

        return $this->menu = $menu;
    }

    public function getAssets(): array
    {
        $assets = (array) $this->config['assets'];

        return [
            'stylesheets' => array_values(array_map('strval', (array) ($assets['stylesheets'] ?? []))),
            'javascripts' => array_values(array_map('strval', (array) ($assets['javascripts'] ?? []))),
        ];
    }

    public function getWidgets(): array
    {
        if ($this->widgets !== null) {
            return $this->widgets;
        }

        $widgets = [];

        foreach (array_values((array) (((array) $this->config['dashboard'])['widgets'] ?? [])) as $index => $widget) {
            $widgets[] = $this->normalizeWidget($widget, 'dashboard.widgets[' . $index . ']');
        }

        return $this->widgets = $widgets;
    }

    public function hasEntity(string $name): bool
    {
        return in_array($name, $this->entityNames(), true);
    }

    public function getEntity(string $name): EntityDefinition
    {
        if (isset($this->definitions[$name])) {
            return $this->definitions[$name];
        }

        if (!$this->hasEntity($name)) {
            throw new NeoAdminException('The entity "{entity}" is not administered: add it to the menu of config/packages/admin/admin.yaml or create config/packages/admin/entities/{entity}.yaml.', 0, null, ['entity' => $name]);
        }

        $config = (array) ($this->entities[$name] ?? []);
        $class = ltrim((string) ($config['class'] ?? rtrim((string) $this->config['entity_namespace'], '\\') . '\\' . $name), '\\');

        if (!class_exists($class)) {
            throw new NeoAdminException('The class "{class}" of the administered entity "{entity}" does not exist: set "class" in config/packages/admin/entities/{entity}.yaml.', 0, null, ['class' => $class, 'entity' => $name]);
        }

        $actions = array_values(array_intersect(EntityDefinition::ACTIONS, array_map('strval', (array) ($config['actions'] ?? EntityDefinition::ACTIONS))));
        $default = (string) ($config['default'] ?? 'index');

        if (!in_array($default, EntityDefinition::DEFAULTS, true) || !in_array($default, $actions, true)) {
            throw new NeoAdminException('The default page "{default}" of the entity "{entity}" must be "index" or "new" and one of its actions ({actions}).', 0, null, ['default' => $default, 'entity' => $name, 'actions' => implode(', ', $actions)]);
        }

        return $this->definitions[$name] = new EntityDefinition(
            $name,
            $class,
            (string) ($config['label'] ?? EntityDefinition::humanize($name)),
            isset($config['role']) && $config['role'] !== '' ? (string) $config['role'] : null,
            $default,
            $actions,
            array_diff_key($config, array_flip(self::ENTITY_KEYS)),
        );
    }

    public function getEntityBySlug(string $slug): EntityDefinition
    {
        foreach ($this->entityNames() as $name) {
            if (EntityDefinition::slugify($name) === $slug) {
                return $this->getEntity($name);
            }
        }

        throw new NeoAdminException('No administered entity matches "{slug}".', 0, null, ['slug' => $slug]);
    }

    public function getEntities(): array
    {
        $entities = [];

        foreach ($this->entityNames() as $name) {
            $entities[$name] = $this->getEntity($name);
        }

        return $entities;
    }

    protected function entityNames(): array
    {
        $names = array_map('strval', array_keys($this->entities));
        $collect = static function (array $items) use (&$collect, &$names): void {
            foreach ($items as $item) {
                if ($item['type'] === 'entity') {
                    $names[] = (string) $item['entity'];
                }

                $collect($item['children']);
            }
        };
        $collect($this->getMenu());

        return array_values(array_unique($names));
    }

    protected function normalizeItem(mixed $item, string $path): array
    {
        if (!is_array($item)) {
            throw new NeoAdminException('The menu item {path} must be a mapping with a "type".', 0, null, ['path' => $path]);
        }

        $type = strtolower((string) ($item['type'] ?? ''));

        if (!in_array($type, self::MENU_TYPES, true)) {
            throw new NeoAdminException('The type "{type}" of the menu item {path} is invalid: use {types}.', 0, null, ['type' => $type, 'path' => $path, 'types' => implode(', ', self::MENU_TYPES)]);
        }

        $label = isset($item['label']) && $item['label'] !== '' ? (string) $item['label'] : null;
        $normalized = [
            'type' => $type,
            'label' => $label ?? ($type === 'dashboard' ? 'Dashboard' : null),
            'icon' => array_key_exists('icon', $item) ? ($item['icon'] === null ? null : (string) $item['icon']) : self::MENU_ICONS[$type],
            'role' => isset($item['role']) && $item['role'] !== '' ? (string) $item['role'] : null,
            'badge' => isset($item['badge']) && $item['badge'] !== '' ? (string) $item['badge'] : null,
            'badge_variant' => isset($item['badge_variant']) && $item['badge_variant'] !== '' ? strtolower((string) $item['badge_variant']) : null,
            'entity' => null,
            'url' => null,
            'route' => null,
            'parameters' => [],
            'target' => isset($item['target']) ? (string) $item['target'] : null,
            'children' => [],
        ];

        if ($normalized['badge_variant'] !== null && !in_array($normalized['badge_variant'], self::BADGE_VARIANTS, true)) {
            throw new NeoAdminException('The badge_variant "{variant}" of the menu item {path} is invalid: use {variants}.', 0, null, ['variant' => $normalized['badge_variant'], 'path' => $path, 'variants' => implode(', ', self::BADGE_VARIANTS)]);
        }

        if ($type === 'entity') {
            if (!isset($item['entity']) || !is_string($item['entity']) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $item['entity']) !== 1) {
                throw new NeoAdminException('The menu item {path} of type "entity" needs "entity": the name of the entity (User, BlogPost...).', 0, null, ['path' => $path]);
            }

            $normalized['entity'] = $item['entity'];
        }

        if (in_array($type, ['submenu', 'link', 'section'], true) && $label === null) {
            throw new NeoAdminException('The menu item {path} of type "{type}" needs a "label".', 0, null, ['path' => $path, 'type' => $type]);
        }

        if ($type === 'link') {
            if (!isset($item['url']) && !isset($item['route'])) {
                throw new NeoAdminException('The menu item {path} of type "link" needs a "url" or a "route".', 0, null, ['path' => $path]);
            }

            $normalized['url'] = isset($item['url']) ? (string) $item['url'] : null;
            $normalized['route'] = isset($item['route']) ? (string) $item['route'] : null;
            $normalized['parameters'] = (array) ($item['parameters'] ?? []);
        }

        if ($type === 'submenu') {
            if (!isset($item['children']) || !is_array($item['children']) || $item['children'] === []) {
                throw new NeoAdminException('The menu item {path} of type "submenu" needs "children": the list of its items.', 0, null, ['path' => $path]);
            }

            foreach (array_values($item['children']) as $index => $child) {
                $normalized['children'][] = $this->normalizeItem($child, $path . '.children[' . $index . ']');
            }
        }

        return $normalized;
    }

    protected function normalizeWidget(mixed $widget, string $path): array
    {
        if (!is_array($widget)) {
            throw new NeoAdminException('The widget {path} must be a mapping with a "type".', 0, null, ['path' => $path]);
        }

        $type = strtolower((string) ($widget['type'] ?? ''));

        if (!in_array($type, self::WIDGET_TYPES, true)) {
            throw new NeoAdminException('The type "{type}" of the widget {path} is invalid: use {types}.', 0, null, ['type' => $type, 'path' => $path, 'types' => implode(', ', self::WIDGET_TYPES)]);
        }

        $size = isset($widget['size']) ? strtolower((string) $widget['size']) : ($type === 'count' ? 'small' : ($type === 'custom' ? null : 'large'));

        if ($size !== null && !in_array($size, self::WIDGET_SIZES, true)) {
            throw new NeoAdminException('The size "{size}" of the widget {path} is invalid: use {sizes}.', 0, null, ['size' => $size, 'path' => $path, 'sizes' => implode(', ', self::WIDGET_SIZES)]);
        }

        $normalized = [
            'type' => $type,
            'label' => isset($widget['label']) && $widget['label'] !== '' ? (string) $widget['label'] : null,
            'icon' => isset($widget['icon']) && $widget['icon'] !== '' ? (string) $widget['icon'] : null,
            'role' => isset($widget['role']) && $widget['role'] !== '' ? (string) $widget['role'] : null,
            'size' => $size,
            'entity' => null,
            'widget' => null,
            'limit' => max(1, (int) ($widget['limit'] ?? 5)),
            'sort' => isset($widget['sort']) ? (array) $widget['sort'] : [],
            'links' => [],
        ];

        if ($type === 'count' || $type === 'latest') {
            if (!isset($widget['entity']) || !is_string($widget['entity']) || !$this->hasEntity($widget['entity'])) {
                throw new NeoAdminException('The widget {path} of type "{type}" needs "entity": the name of an administered entity.', 0, null, ['path' => $path, 'type' => $type]);
            }

            $normalized['entity'] = $widget['entity'];
        }

        if ($type === 'custom') {
            if (!isset($widget['widget']) || !is_string($widget['widget']) || !is_subclass_of($widget['widget'], WidgetInterface::class)) {
                throw new NeoAdminException('The widget {path} of type "custom" needs "widget": a class implementing {interface}.', 0, null, ['path' => $path, 'interface' => WidgetInterface::class]);
            }

            $normalized['widget'] = $widget['widget'];
        }

        if ($type === 'links') {
            if ($normalized['label'] === null || !isset($widget['links']) || !is_array($widget['links']) || $widget['links'] === []) {
                throw new NeoAdminException('The widget {path} of type "links" needs a "label" and "links": the list of its links.', 0, null, ['path' => $path]);
            }

            foreach (array_values($widget['links']) as $index => $link) {
                $item = $this->normalizeItem(is_array($link) ? ['type' => 'link'] + $link : $link, $path . '.links[' . $index . ']');

                if ($item['type'] !== 'link') {
                    throw new NeoAdminException('The link {path} of a "links" widget must be a link with a "label" and a "url" or a "route".', 0, null, ['path' => $path . '.links[' . $index . ']']);
                }

                $normalized['links'][] = $item;
            }
        }

        return $normalized;
    }
}