<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Contract;

use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Menu\MenuBuilder;
use NeoPHP\NeoAdmin\NeoAdminManagerInterface;
use NeoPHP\NeoAdmin\Page\Page;
use NeoPHP\NeoAdmin\Page\PageContext;
use NeoPHP\Package\Translation\TranslationManagerInterface;

abstract class AbstractAdminController extends AbstractController
{
    protected function admin(): NeoAdminManagerInterface
    {
        return $this->get(NeoAdminManagerInterface::class);
    }

    protected function menu(): MenuBuilder
    {
        return $this->get(MenuBuilder::class);
    }

    protected function denyUnlessAdmin(?string $role = null): void
    {
        $this->denyAccessUnlessGranted($this->admin()->getRole());

        if ($role !== null && $role !== $this->admin()->getRole()) {
            $this->denyAccessUnlessGranted($role);
        }
    }

    protected function page(string $title): Page
    {
        return new Page($title, fn (Page $page, int $status): Response => $this->renderPage($page, $status));
    }

    protected function renderPage(Page $page, int $status = 200): Response
    {
        $view = $page->build($this->pageContext());

        return $this->renderAdmin('@admin/page.html.twig', ['page' => $view['context']], $status);
    }

    protected function pageContext(): PageContext
    {
        $locale = $this->has(TranslationManagerInterface::class) ? $this->get(TranslationManagerInterface::class)->getLocale() : 'en';

        return new PageContext($this->get(Request::class), $this->menu(), $locale);
    }

    protected function renderAdmin(string $template, array $parameters = [], int $status = 200, ?EntityDefinition $entity = null): Response
    {
        $request = $this->get(Request::class);
        $route = $request->attributes->get('_route');

        return $this->render($template, [
                'admin' => [
                        'title' => $this->admin()->getTitle(),
                        'menu' => $this->menu()->build(is_string($route) ? $route : null, $entity?->getSlug()),
                        'entity' => $entity,
                    ] + $this->admin()->getAssets(),
            ] + $parameters, $status);
    }
}