<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

use NeoPHP\Component\Form\Contract\FormInterface;
use NeoPHP\Component\Form\FormManagerInterface;
use NeoPHP\Component\Form\Type\CheckboxType;
use NeoPHP\Component\Form\Type\ChoiceType;
use NeoPHP\Component\Form\Type\ColorType;
use NeoPHP\Component\Form\Type\DateTimeType;
use NeoPHP\Component\Form\Type\DateType;
use NeoPHP\Component\Form\Type\EmailType;
use NeoPHP\Component\Form\Type\EnumType;
use NeoPHP\Component\Form\Type\FormType;
use NeoPHP\Component\Form\Type\HiddenType;
use NeoPHP\Component\Form\Type\IntegerType;
use NeoPHP\Component\Form\Type\NumberType;
use NeoPHP\Component\Form\Type\PasswordType;
use NeoPHP\Component\Form\Type\TelType;
use NeoPHP\Component\Form\Type\TextareaType;
use NeoPHP\Component\Form\Type\TextType;
use NeoPHP\Component\Form\Type\TimeType;
use NeoPHP\Component\Form\Type\UrlType;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\NeoAdmin\Form\AdminFormTheme;
use NeoPHP\Package\Orm\Helper\Form\EntityType;
use NeoPHP\Package\Orm\OrmManagerInterface;
use ReflectionClass;

/**
 * @internal
 */
class EntityFormFactory
{
    public const TYPES = [
        'text' => TextType::class,
        'textarea' => TextareaType::class,
        'email' => EmailType::class,
        'url' => UrlType::class,
        'tel' => TelType::class,
        'password' => PasswordType::class,
        'integer' => IntegerType::class,
        'number' => NumberType::class,
        'checkbox' => CheckboxType::class,
        'choice' => ChoiceType::class,
        'enum' => EnumType::class,
        'entity' => EntityType::class,
        'date' => DateType::class,
        'datetime' => DateTimeType::class,
        'time' => TimeType::class,
        'color' => ColorType::class,
        'hidden' => HiddenType::class,
    ];

    public const FIELD_OPTIONS = ['label', 'help', 'required', 'disabled', 'attr', 'placeholder', 'choices', 'multiple', 'expanded'];

    public function __construct(
        protected FormManagerInterface $forms,
        protected OrmManagerInterface $orm,
        protected ValueRenderer $values,
    ) {
    }

    public function create(EntityDefinition $entity, object $data, array $fields, string $action): FormInterface
    {
        $form = $this->forms->createNamed('admin_' . str_replace('-', '_', $entity->getSlug()), FormType::class, $data, [
            'data_class' => $entity->getClass(),
            'theme' => AdminFormTheme::class,
            'action' => $action,
            'translation_domain' => 'admin',
        ]);

        foreach ($fields as $field) {
            if ($field instanceof FieldDefinition) {
                [$type, $options] = $this->guess($entity, $field);
                $form->add($field->getName(), $type, $options);
            }
        }

        return $form;
    }

    public function instantiate(EntityDefinition $entity): object
    {
        $class = $entity->getClass();
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $class();
        }

        return $this->orm->getMetadata($class)->newInstance();
    }

    protected function guess(EntityDefinition $entity, FieldDefinition $field): array
    {
        $mapping = (array) $field->getMapping();
        $options = ['label' => $field->getLabel()];
        $type = $field->getOption('type');

        if ($field->getType() === 'json' && $type === null) {
            throw new NeoAdminException('The JSON field "{field}" of the entity "{entity}" cannot be edited without a "type" in the "form" section of config/packages/admin/entities/{entity}.yaml.', 0, null, ['field' => $field->getName(), 'entity' => $entity->getName()]);
        }

        if ($field->isAssociation()) {
            $options += [
                'class' => (string) $mapping['target'],
                'multiple' => $field->isToMany(),
                'required' => !$field->isToMany() && !($mapping['nullable'] ?? true),
                'choice_label' => fn (object $choice): string => $this->values->label($choice),
            ];

            if (!$field->isToMany()) {
                $options['placeholder'] = '';
            }

            $type ??= 'entity';
        } elseif ($field->isMapped()) {
            $nullable = (bool) ($mapping['nullable'] ?? false);
            $options['required'] = !$nullable;

            [$guessed, $extra] = match ($field->getType()) {
                'text' => ['textarea', ['attr' => ['rows' => 6]]],
                'integer', 'smallint', 'bigint' => ['integer', []],
                'float' => ['number', []],
                'decimal' => ['number', ['input' => 'string', 'scale' => $mapping['scale'] ?? null]],
                'boolean' => ['checkbox', ['required' => false]],
                'datetime' => ['datetime', ['input' => 'datetime']],
                'datetime_immutable' => ['datetime', ['input' => 'datetime_immutable']],
                'date' => ['date', ['input' => 'datetime']],
                'date_immutable' => ['date', ['input' => 'datetime_immutable']],
                'time' => ['time', ['input' => 'datetime']],
                'time_immutable' => ['time', ['input' => 'datetime_immutable']],
                'enum' => ['enum', ['class' => $mapping['enumType'], 'placeholder' => $nullable ? '' : null]],
                default => ['text', isset($mapping['length']) ? ['attr' => ['maxlength' => (int) $mapping['length']]] : []],
            };

            $options = array_replace($options, $extra);
            $type ??= $guessed;
        }

        foreach (self::FIELD_OPTIONS as $name) {
            if (array_key_exists($name, $field->getOptions())) {
                $options[$name] = $field->getOption($name);
            }
        }

        $options = array_replace($options, (array) $field->getOption('options', []));
        $type = (string) ($type ?? 'text');

        return [self::TYPES[$type] ?? $type, $options];
    }
}