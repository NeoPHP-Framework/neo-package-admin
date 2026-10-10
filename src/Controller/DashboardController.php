<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Controller;

use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\NeoAdmin\Contract\AbstractAdminController;
use NeoPHP\NeoAdmin\Dashboard\WidgetBuilder;
use NeoPHP\NeoAdmin\Page\Block\Grid;

class DashboardController extends AbstractAdminController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessAdmin();

        $builder = $this->get(WidgetBuilder::class);
        $user = $this->getUser();
        $page = $this->page($this->translate('Dashboard', [], 'admin'))
            ->heading($this->translate('Welcome to the administration.', [], 'admin'))
            ->description($user !== null ? $this->translate('You are logged in as {user}.', ['user' => $user->getUserIdentifier()], 'admin') : null);

        $widgets = $builder->build();

        if ($widgets !== []) {
            return $page->grid(4, static function (Grid $grid) use ($widgets): void {
                foreach ($widgets as $widget) {
                    $grid->add($widget);
                }
            })->render();
        }

        $tiles = $builder->tiles();

        if ($tiles !== []) {
            return $page->grid(3, static function (Grid $grid) use ($tiles): void {
                foreach ($tiles as $tile) {
                    $grid->add($tile);
                }
            })->render();
        }

        return $page->empty('No entity is administered yet.', 'Add entities to the menu of config/packages/admin/admin.yaml.')->render();
    }
}