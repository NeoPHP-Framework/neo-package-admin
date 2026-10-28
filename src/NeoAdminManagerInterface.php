<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin;

use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;

interface NeoAdminManagerInterface
{
    /**
     * Returns the configuration of config/packages/admin/admin.yaml with its defaults.
     *
     * @return array<string, mixed> The configuration
     */
    public function getConfig(): array;

    /**
     * Returns the title of the administration, shown in the header and the browser tab.
     *
     * @return string The title
     */
    public function getTitle(): string;

    /**
     * Returns the role required to access the administration.
     *
     * @return string The role (ROLE_ADMIN by default)
     */
    public function getRole(): string;

    /**
     * Returns the number of rows of the entity lists.
     *
     * @return int The number of rows per page
     */
    public function getPerPage(): int;

    /**
     * Returns the items of the menu, validated and completed with their defaults.
     *
     * @return list<array<string, mixed>> The items: type, label, icon, role, badge, badge_variant, entity, url, route, parameters, target and children
     * @throws NeoAdminException When an item is invalid
     */
    public function getMenu(): array;

    /**
     * Returns the stylesheets and the scripts of the project loaded by the layout after the ones of the package.
     *
     * @return array{stylesheets: list<string>, javascripts: list<string>} The paths, given to asset()
     */
    public function getAssets(): array;

    /**
     * Returns the widgets of the dashboard, validated and completed with their defaults.
     *
     * @return list<array<string, mixed>> The widgets: type, label, icon, role, size, entity, widget, limit, sort and links
     * @throws NeoAdminException When a widget is invalid
     */
    public function getWidgets(): array;

    /**
     * Tells whether an entity is administered: configured in entities/ or used in the menu.
     *
     * @param string $name Name of the entity (User, BlogPost...)
     * @return bool True when the entity is administered
     * @throws NeoAdminException When an item of the menu is invalid
     */
    public function hasEntity(string $name): bool;

    /**
     * Returns the definition of an administered entity, built from config/packages/admin/entities/<Name>.yaml.
     *
     * @param string $name Name of the entity (User, BlogPost...)
     * @return EntityDefinition The definition
     * @throws NeoAdminException When the entity is not administered or its class does not exist
     */
    public function getEntity(string $name): EntityDefinition;

    /**
     * Returns the definition of an administered entity from the slug of its URL.
     *
     * @param string $slug Slug of the entity (user, blog-post...)
     * @return EntityDefinition The definition
     * @throws NeoAdminException When no administered entity has this slug or its class does not exist
     */
    public function getEntityBySlug(string $slug): EntityDefinition;

    /**
     * Returns the definitions of every administered entity.
     *
     * @return array<string, EntityDefinition> The definitions, by entity name
     * @throws NeoAdminException When an item of the menu is invalid or the class of an entity does not exist
     */
    public function getEntities(): array;
}