<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Command;

use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\NeoAdmin\Crud\FieldResolver;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\NeoAdminManagerInterface;
use NeoPHP\Package\Orm\Metadata\ClassMetadata;
use NeoPHP\Package\Orm\OrmManagerInterface;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputArgument;
use Throwable;

/**
 * @internal
 */
#[AsCommand(name: 'make:admin:entity', description: 'Generates config/packages/admin/entities/<Name>.yaml from the ORM mapping of an entity')]
class MakeAdminEntityCommand extends AbstractConsole
{
    public const DIRECTORY = 'config/packages/admin/entities';

    public function __construct(protected ContainerManagerInterface $container)
    {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addArgument('entity', InputArgument::REQUIRED, 'The name of the entity (User, BlogPost) or its class', null, 'Name of the entity (e.g. User, BlogPost)');
        $this->setHelp('Reads the ORM mapping of the entity and writes the list, show and form pages of the administration in config/packages/admin/entities/<Name>.yaml. An existing file is only replaced with --force.');
        $this->addExample('make:admin:entity User');
        $this->addExample('make:admin:entity "App\Entity\Blog\Post"');
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $admin = $this->container->get(NeoAdminManagerInterface::class);
        $argument = trim(str_replace('/', '\\', (string) $input->getArgument('entity')), '\\');
        $namespace = trim((string) ($admin->getConfig()['entity_namespace'] ?? 'App\\Entity'), '\\');
        $class = str_contains($argument, '\\') ? $argument : $namespace . '\\' . $argument;
        $name = substr($class, (int) strrpos('\\' . $class, '\\'));

        if (!class_exists($class)) {
            $output->error(sprintf('The class "%s" does not exist.', $class));

            return self::FAILURE;
        }

        try {
            $metadata = $this->container->get(OrmManagerInterface::class)->getMetadata($class);
        } catch (Throwable $exception) {
            $output->error(sprintf('The class "%s" is not an ORM entity: %s', $class, $exception->getMessage()));

            return self::FAILURE;
        }

        $file = rtrim((string) $this->container->get('kernel.root_path'), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::DIRECTORY) . DIRECTORY_SEPARATOR . $name . '.yaml';

        if (is_file($file) && !$input->getOption('force')) {
            $output->error(sprintf('The file "%s" already exists: use --force to overwrite it.', $file));

            return self::FAILURE;
        }

        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true) && !is_dir(dirname($file))) {
            $output->error(sprintf('Unable to create the directory "%s".', dirname($file)));

            return self::FAILURE;
        }

        file_put_contents($file, $this->yaml($metadata, $name, $class !== $namespace . '\\' . $name ? $class : null));

        $output->writeln(sprintf('  <success>created</success>  %s', $file));
        $output->success(sprintf('The administration of %s is configured.', $name));

        if (!$this->inMenu($admin->getMenu(), $name)) {
            $output->text(sprintf('Add it to the menu of <info>config/packages/admin/admin.yaml</info>: <info>- { type: entity, entity: %s }</info>', $name));
        }

        return self::SUCCESS;
    }

    protected function yaml(ClassMetadata $metadata, string $name, ?string $class): string
    {
        $resolver = $this->container->get(FieldResolver::class);
        $list = static fn (array $items): string => '[' . implode(', ', $items) . ']';
        $lines = [];

        if ($class !== null) {
            $lines[] = 'class: ' . $class;
        }

        $lines[] = 'label: ' . EntityDefinition::humanize($name);
        $lines[] = 'icon: list';
        $lines[] = 'actions: ' . $list(EntityDefinition::ACTIONS);
        $lines[] = 'default: index';
        $lines[] = '';
        $lines[] = 'index:';
        $lines[] = '    fields: ' . $list($resolver->guess($metadata, 'index'));
        $lines[] = '    search: ' . $list($resolver->searchable($metadata));
        $lines[] = '    sort: { ' . $metadata->getIdentifier() . ': desc }';
        $lines[] = '';
        $lines[] = 'show:';
        $lines[] = '    fields: ' . $list($resolver->guess($metadata, 'show'));
        $lines[] = '';
        $lines[] = 'form:';
        $lines[] = '    fields:';

        foreach ($resolver->guess($metadata, 'form') as $field) {
            $lines[] = '        ' . $field . ': ~';
        }

        return implode("\n", $lines) . "\n";
    }

    protected function inMenu(array $items, string $name): bool
    {
        foreach ($items as $item) {
            if (($item['type'] === 'entity' && $item['entity'] === $name) || $this->inMenu((array) $item['children'], $name)) {
                return true;
            }
        }

        return false;
    }
}