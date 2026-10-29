<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Provider;

use NeoPHP\Component\Config\ConfigManagerInterface;
use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Component\Container\Contract\AbstractProvider;
use NeoPHP\Component\Form\FormManagerInterface;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\NeoAdmin\Crud\EntityFormFactory;
use NeoPHP\NeoAdmin\Crud\EntityQuery;
use NeoPHP\NeoAdmin\Crud\FieldResolver;
use NeoPHP\NeoAdmin\Crud\TranslationMapper;
use NeoPHP\NeoAdmin\Crud\ValueRenderer;
use NeoPHP\NeoAdmin\Dashboard\WidgetBuilder;
use NeoPHP\NeoAdmin\Menu\MenuBuilder;
use NeoPHP\NeoAdmin\NeoAdminManager;
use NeoPHP\NeoAdmin\NeoAdminManagerInterface;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Package\Security\SecurityManagerInterface;
use NeoPHP\Package\Translation\TranslationManagerInterface;
use NeoPHP\NeoAdmin\Contract\LocaleProviderInterface;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;

/**
 * @internal
 */
class NeoAdminProvider extends AbstractProvider
{
    public function register(ContainerManagerInterface $container): void
    {
        $container->singleton(NeoAdminManagerInterface::class, static function (ContainerManagerInterface $container): NeoAdminManagerInterface {
            $config = $container->has(ConfigManagerInterface::class) ? $container->get(ConfigManagerInterface::class) : null;

            return new NeoAdminManager(
                (array) ($config?->get(NeoAdminManager::CONFIG_KEY . '.admin', []) ?? []),
                (array) ($config?->get(NeoAdminManager::CONFIG_KEY . '.entities', []) ?? []),
            );
        });

        $container->alias(NeoAdminManager::class, NeoAdminManagerInterface::class);

        $container->singleton(MenuBuilder::class, static fn (ContainerManagerInterface $container): MenuBuilder => new MenuBuilder(
            $container->get(NeoAdminManagerInterface::class),
            $container->get(RoutingManagerInterface::class),
            $container->get(SecurityManagerInterface::class),
            $container,
        ));

        $container->singleton(FieldResolver::class, static fn (ContainerManagerInterface $container): FieldResolver => new FieldResolver(
            $container->get(OrmManagerInterface::class),
        ));

        $container->singleton(EntityQuery::class, static fn (ContainerManagerInterface $container): EntityQuery => new EntityQuery(
            $container->get(OrmManagerInterface::class),
            $container->get(FieldResolver::class),
        ));

        $container->singleton(TranslationMapper::class, static function (ContainerManagerInterface $container): TranslationMapper {
            $provider = $container->get(NeoAdminManagerInterface::class)->getConfig()['locale_provider'] ?? null;

            if ($provider !== null && (!class_exists((string) $provider) || !is_subclass_of((string) $provider, LocaleProviderInterface::class))) {
                throw new NeoAdminException('The locale provider "{class}" of config/packages/admin/admin.yaml must be a class implementing {interface}.', 0, null, ['class' => (string) $provider, 'interface' => LocaleProviderInterface::class]);
            }

            return new TranslationMapper(
                $container->get(OrmManagerInterface::class),
                $container->get(TranslationManagerInterface::class),
                $provider !== null ? $container->get((string) $provider) : null,
            );
        });

        $container->singleton(ValueRenderer::class, static fn (ContainerManagerInterface $container): ValueRenderer => new ValueRenderer(
            $container->get(OrmManagerInterface::class),
            $container->get(TranslationManagerInterface::class),
            $container->get(TranslationMapper::class),
        ));

        $container->singleton(EntityFormFactory::class, static fn (ContainerManagerInterface $container): EntityFormFactory => new EntityFormFactory(
            $container->get(FormManagerInterface::class),
            $container->get(OrmManagerInterface::class),
            $container->get(ValueRenderer::class),
            $container->get(TranslationMapper::class),
        ));

        $container->singleton(WidgetBuilder::class, static fn (ContainerManagerInterface $container): WidgetBuilder => new WidgetBuilder(
            $container->get(NeoAdminManagerInterface::class),
            $container->get(OrmManagerInterface::class),
            $container->get(MenuBuilder::class),
            $container->get(ValueRenderer::class),
            $container->get(TranslationManagerInterface::class),
            $container,
        ));
    }
}