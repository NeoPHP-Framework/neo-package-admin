# NeoAdmin

Modular administration area for [NeoPHP](https://github.com/neophp-framework/neophp): a Tailwind layout, a menu described in YAML, the CRUD of the ORM entities and a dashboard with widgets.

## Installation

```bash
php bin/neo neophp:package:install neophp-package/neo-admin
```

The command copies `config/packages/admin/admin.yaml` and imports the routes (`resource: '@admin'` in `config/routes.yaml`). The administration is served on `/admin` and requires `ROLE_ADMIN`.

Requirements: PHP 8.2, `neophp/framework` 2.1 with the ORM, Security, Form and Translation modules, Twig.

## Configuration

`config/packages/admin/admin.yaml`:

```yaml
title: Administration
role: ROLE_ADMIN
per_page: 25
entity_namespace: App\Entity

menu:
    - { type: dashboard }
    - { type: section, label: Content }
    - { type: entity, entity: BlogPost, icon: file }
    - { type: entity, entity: Category, icon: tag }
    - type: submenu
      label: Users
      icon: users
      role: ROLE_SUPER_ADMIN
      children:
          - { type: entity, entity: User }
          - { type: entity, entity: Group }
    - { type: link, label: Website, url: '/', icon: external, target: _blank }

dashboard:
    widgets:
        - { type: count, entity: BlogPost, icon: file }
        - { type: count, entity: User, label: Members, icon: users }
        - { type: latest, entity: BlogPost, limit: 5, sort: { createdAt: desc } }
        - type: links
          label: Shortcuts
          links:
              - { label: New article, route: admin_crud_new, parameters: { entity: blog-post }, icon: plus }
```

### Menu items

| Type | Keys |
|---|---|
| `dashboard` | `label` (default `Dashboard`) |
| `entity` | `entity` (required): the short name of an entity of `entity_namespace` |
| `submenu` | `label`, `children` (required) |
| `link` | `label`, `url` or `route` + `parameters`, `target` |
| `section` | `label` (required): a title between groups of items |

Every item accepts `icon`, `role`, `badge` and `badge_variant`. An item whose role is not granted is hidden, as is an empty submenu or section.

### Menu badges

`badge` is a short text (`New`, `Beta`) or a class of the project implementing `BadgeInterface`, whose value is computed on each page: a counter of records waiting for a moderation, for example. `badge_variant` colors it: `primary`, `success`, `warning`, `danger` or `info` (neutral without it).

```php
namespace App\Admin\Badge;

use App\Entity\Report;
use NeoPHP\NeoAdmin\Menu\Contract\BadgeInterface;
use NeoPHP\Package\Orm\OrmManagerInterface;

class PendingReportsBadge implements BadgeInterface
{
    public function __construct(protected OrmManagerInterface $orm)
    {
    }

    public function getValue(): int|string|null
    {
        return $this->orm->getRepository(Report::class)->count(['status' => 'pending']);
    }
}
```

```yaml
menu:
    - { type: entity, entity: Report, icon: shield-alert, badge: App\Admin\Badge\PendingReportsBadge, badge_variant: danger }
    - { type: link, label: Changelog, route: admin_changelog, badge: New, badge_variant: info }
```

The class is created by the container, so its constructor receives the services it needs. A value of `null`, `0` or `''` hides the badge, and a number above 99 is shown as `99+`. The value is computed only for the items the user can see, on every page of the administration: keep the query light (a `count()` on an indexed column) or cache it.

Icons: `home`, `users`, `user`, `file`, `folder`, `tag`, `list`, `settings`, `link`, `external`, `chart`, `shield`, `mail`, `box`, `plus`, `edit`, `trash`, `eye`, `search`, `logout`, `menu`, `x`, `chevron-down`, `chevron-right`, `sun`, `moon`, `circle`.

### Dashboard widgets

| Type | Keys |
|---|---|
| `count` | `entity`: the number of records, linked to the list |
| `latest` | `entity`, `limit` (5), `sort` (`{ field: desc }`): the last records |
| `links` | `label`, `links`: a list of links (same keys as a `link` menu item) |
| `custom` | `widget`: a class implementing `WidgetInterface` (see [Custom widgets](#custom-widgets)) |

Every widget accepts `label`, `icon`, `role` and `size` (`small` or `large`). Without widgets, the dashboard shows a card per entity.

## Entities

Each entity of the menu gets a list, a show page and forms guessed from its ORM mapping. To customize them, generate its file:

```bash
php bin/neo make:admin:entity BlogPost
```

`config/packages/admin/entities/BlogPost.yaml`:

```yaml
label: Articles
icon: file
role: ROLE_EDITOR
actions: [index, show, new, edit, delete]
default: index
roles: { delete: ROLE_SUPER_ADMIN }

index:
    fields: [title, status, author, createdAt]
    search: [title, content]
    sort: { createdAt: desc }
    per_page: 20

show:
    fields:
        title: ~
        content: { label: Body }
        createdAt: { format: 'd/m/Y H:i' }

form:
    fields:
        title: { help: 'Displayed on the home page.' }
        content: { type: textarea, attr: { rows: 12 } }
        status: { expanded: true }
        author: ~
```

| Key | Description |
|---|---|
| `class` | The class when it is not in `entity_namespace` |
| `label`, `icon` | The name and the icon of the entity |
| `role` | The role required for the entity, on top of the role of the administration |
| `actions` | The enabled pages among `index`, `show`, `new`, `edit` and `delete` |
| `default` | The page of `/admin/<entity>`: `index` or `new` |
| `roles` | A role per action |
| `index.fields`, `show.fields`, `form.fields` | The fields of each page, as a list or a mapping with options |
| `index.search` | The fields searched by the search box (the text fields by default) |
| `index.sort` | The default order |
| `index.per_page` | The number of rows per page |

Options of a field: `label`, `format` (dates), `truncate` (list), `sortable`, and for the forms `type` (`text`, `textarea`, `email`, `url`, `tel`, `password`, `integer`, `number`, `checkbox`, `choice`, `enum`, `entity`, `date`, `datetime`, `time`, `color`, `hidden` or a form type class), `help`, `required`, `disabled`, `attr`, `placeholder`, `choices`, `multiple`, `expanded` and `options` (`color`, `hidden`, `translations` or a form type class).

A field which is not mapped by the ORM can be displayed when the entity has a property or a getter with its name.

### Translations

The `translations` type edits a OneToMany association holding the translations of the entity: the form shows a block per locale with a field per translated property, and the rows are created, updated and removed on save (an empty value removes its row).

```yaml
form:
    fields:
        slug: ~
        translations:
            type: translations
            locales: { fr: Français, en: English }
            fields:
                name: { label: Name, required: true }
                description: { label: Description, type: textarea }

index:
    fields:
        translations: { type: translations, label: Name, field: name }

show:
    fields:
        translations: { type: translations, locales: { fr: Français, en: English }, fields: [name, description] }
```

Two layouts of the translation entity are supported:

- one row per locale and field, with `locale`, `field` and `value` properties (`CategoryTranslated { translatable, locale, field, value }`), used when the target has a `field` property;
- one row per locale, with a property per translated field (`ProductTranslation { product, locale, title, summary }`).

| Option | Description |
|---|---|
| `locales` | The locales, as a list (`[fr, en]`) or a mapping of codes and labels (the locales of the Translation module by default) |
| `fields` | The translated fields, as a list or a mapping with `label`, `type`, `required`, `help`, `attr`, `placeholder`, `choices` and `options` (`name` by default, or the text properties of a one-row-per-locale target) |
| `locale_property` | The property of the locale (`locale`) |
| `field_property` | The property of the field name (`field` when the target has it, `null` for a row per locale) |
| `value_property` | The property of the value (`value`) |
| `field` | In the list, the field displayed in the current locale (the first field by default) |

The association needs a `mappedBy`. The new rows are persisted by the administration, so no `cascade` is required; without `orphanRemoval: true`, the rows emptied in the form are removed explicitly.

## Routes

| Name | Path |
|---|---|
| `admin_dashboard` | `GET /admin` |
| `admin_crud` | `GET /admin/{entity}` |
| `admin_crud_index` | `GET /admin/{entity}/list` |
| `admin_crud_new` | `GET\|POST /admin/{entity}/new` |
| `admin_crud_show` | `GET /admin/{entity}/{id}` |
| `admin_crud_edit` | `GET\|POST /admin/{entity}/{id}/edit` |
| `admin_crud_delete` | `POST /admin/{entity}/{id}/delete` |

`{entity}` is the slug of the entity: `BlogPost` gives `blog-post`.

## Pages

Build the pages of the administration in the controller: extend `NeoPHP\NeoAdmin\Contract\AbstractAdminController`, describe the page with `$this->page()` and render it. The layout, the components and the styles come from the package: no template is needed.

```php
namespace App\Controller\Admin;

use App\Service\Analytics;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\NeoAdmin\Contract\AbstractAdminController;
use NeoPHP\NeoAdmin\Page\Action;
use NeoPHP\NeoAdmin\Page\Block\Grid;
use NeoPHP\NeoAdmin\Page\Column;

class AnalyticsController extends AbstractAdminController
{
    #[Route('/admin/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function index(Request $request, Analytics $analytics): Response
    {
        $this->denyUnlessAdmin();

        $days = in_array($days = $request->query->getInt('days', 7), [7, 30, 90], true) ? $days : 7;
        $summary = $analytics->summary($days);

        return $this->page('Analytics')
            ->description(sprintf('Page views of the last %d days.', $days))
            ->action(Action::link('Open the site', '/', 'external')->blank())
            ->tabs('days', [7 => '7 days', 30 => '30 days', 90 => '90 days'], $days)
            ->grid(3, fn (Grid $grid) => $grid
                ->stat('Page views', $summary['views'], 'eye', trend: 8.2)
                ->stat('Unique visitors', $summary['visitors'], 'users')
                ->stat('Average response', $summary['avg_ms'] . ' ms', 'chart'))
            ->chart('Views per day', $analytics->perDay($days), ['views' => 'Views', 'visitors' => 'Visitors'])
            ->grid(2, fn (Grid $grid) => $grid
                ->table('Top pages', $analytics->topPaths($days), [
                    'path' => Column::code('path', 'Page')->url(fn (array $row) => $row['path']),
                    'views' => 'Views',
                ], icon: 'file', empty: 'No page view yet.')
                ->table('Not found (404)', $analytics->notFound($days), ['path' => 'Page', 'views' => 'Views'], icon: 'search'))
            ->render();
    }
}
```

The routes of the application are matched before the ones of the package, so `/admin/analytics` is not taken for an entity. Add the page to the menu with `- { type: link, label: Analytics, route: admin_analytics, icon: chart }`.

### Page

| Method | Description |
|---|---|
| `heading(string)` | A title above the content (the page title is shown in the top bar) |
| `description(string)` | A line of text under the heading |
| `action(Action)` | A button at the top right, repeatable |
| `render(int $status = 200)` | Renders the page in the layout of the administration |

### Blocks

Every block method returns the page (or the grid / card), so the calls chain. A block also accepts `span` (1 to 4) to take several columns of a grid.

| Method | Description |
|---|---|
| `grid(int $columns, callable)` | A responsive grid of 1 to 4 columns: one column on phones, two on tablets |
| `card(string $title, callable, ?string $icon)` | A card holding other blocks |
| `stat(label, value, ?icon, ?trend, ?help, ?url)` | A number with an icon, an optional trend in percent (`8.2` or `-3.1`) and a "View all" link |
| `table(?title, rows, columns, ?icon, ?empty, ?actions)` | A table of arrays or objects; `actions` is a callable returning the `Action` list of a row |
| `chart(title, rows, series, ?icon, ?empty)` | A bar chart; `rows` are keyed by label or date (`Y-m-d`), `series` maps up to two keys to their label |
| `links(title, links, ?icon, ?empty, ?more)` | A list of links: `label`, `url`, `icon`, `meta`, `target` |
| `tile(label, url, ?icon)` | A link card |
| `tabs(parameter, options, current)` | Links changing a query parameter, like a period selector |
| `form(FormInterface, submit, ?cancel, ?title)` | A form of the framework, rendered with the theme of the administration |
| `alert(message, type)` | An `info`, `success`, `warning` or `error` message |
| `text(string)` | A paragraph |
| `empty(title, ?text, ?icon)` | An empty state |
| `template(name, data)` | A Twig template of the project, for anything else |
| `add(BlockInterface)` | A block object, for example `(new Table(...))->paginate($total, $page, 20)` |

### Columns

A column is a label (`'views' => 'Views'`, its type is guessed from the values) or a `NeoPHP\NeoAdmin\Page\Column`:

| Column | Rendering |
|---|---|
| `Column::text(key, label)->truncate(60)` | Text |
| `Column::number(key, label, decimals)` | A number formatted for the locale, aligned to the right |
| `Column::date(key, label, 'd/m/Y')` | A date |
| `Column::bool(key, label)` | A Yes / No badge |
| `Column::badge(key, label, ['draft' => 'warning'])` | A badge: `primary`, `success`, `warning`, `danger`, `info` |
| `Column::code(key, label)` | Monospace text |
| `Column::link(key, label, fn ($row) => $url)` | A link; `->url()` and `->blank()` work on every column |

### Actions

```php
Action::link('Edit', $url, 'edit')->primary();
Action::link('Open', $url, 'external')->blank();
Action::post('Delete', $url, 'delete_' . $id, 'Delete this item?', 'trash')->danger();
```

A POST action sends a CSRF token for the given id: check it with `$this->isCsrfTokenValid('delete_' . $id, $request->request->get('_token'))`.

### Forms

```php
$form = $this->createForm(SettingsType::class, $settings);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $this->addFlash('success', 'Saved.');

    return $this->redirectToRoute('admin_settings');
}

return $this->page('Settings')->form($form, 'Save', $this->generateUrl('admin_dashboard'))->render($form->isSubmitted() ? 422 : 200);
```

### Custom widgets

A widget of the dashboard can be any block, built by a class of the project:

```php
namespace App\Admin\Widget;

use App\Service\Analytics;
use NeoPHP\NeoAdmin\Page\Block\Stat;
use NeoPHP\NeoAdmin\Page\Contract\BlockInterface;
use NeoPHP\NeoAdmin\Page\Contract\WidgetInterface;

class TrafficWidget implements WidgetInterface
{
    public function __construct(protected Analytics $analytics)
    {
    }

    public function build(): BlockInterface
    {
        return new Stat('Views today', $this->analytics->summary(1)['views'], 'eye');
    }
}
```

```yaml
dashboard:
    widgets:
        - { type: custom, widget: App\Admin\Widget\TrafficWidget }
```

The class is created by the container, so its constructor receives the services it needs.

## Templates and translations

Every template can be overridden in `templates/packages/admin/` (`layout.html.twig`, `page.html.twig`, `components/table.html.twig`, `crud/index.html.twig`...). The texts are in the `admin` domain (English and French): override them in `translations/admin.<locale>.yaml`.

## Styles

The package ships its compiled stylesheet, `assets/css/admin.css`: the projects build nothing.

### Theme

The colors are CSS variables. Change them in a stylesheet of the project, declared in `config/packages/admin/admin.yaml` and loaded after the one of the package:

```yaml
assets:
    stylesheets: [css/admin.css]
    javascripts: []
```

```css
:root {
    --na-primary: #e11d48;
    --na-primary-hover: #f43f5e;
    --na-primary-soft: #fff1f2;
    --na-primary-fg: #be123c;
}

.dark {
    --na-primary: #f43f5e;
    --na-primary-soft: rgb(244 63 94 / .12);
    --na-primary-fg: #fda4af;
}
```

Other variables: `--na-font`, `--na-radius`, `--na-bg`, `--na-surface`, `--na-border`, `--na-text`, `--na-text-muted`, `--na-chart-1`, `--na-chart-2`, and the `success`, `warning`, `danger` and `info` colors.

### Components

The templates of a project can use the classes of the package: `na-card`, `na-card-header`, `na-card-title`, `na-card-body`, `na-stat`, `na-table`, `na-btn` (`na-btn-primary`, `na-btn-danger`, `na-btn-ghost`), `na-badge` (`na-badge-success`...), `na-alert`, `na-tabs`, `na-grid` (`na-grid-2` to `na-grid-4`), `na-empty`, `na-input`, `na-link`, `na-muted`... A set of Tailwind utilities is included too (grid columns, flex, gaps, margins, paddings, text sizes).

### Development of the package

`assets/css/admin.css` is compiled from `resources/tailwind/admin.css` with the [Tailwind CSS standalone CLI](https://tailwindcss.com/blog/standalone-cli) 4, after a change of the styles or of the templates:

```bash
tailwindcss -i resources/tailwind/admin.css -o assets/css/admin.css --minify
```

## License

MIT