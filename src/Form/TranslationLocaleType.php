<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Form;

use NeoPHP\Component\Form\Contract\AbstractType;
use NeoPHP\Component\Form\Model\FormBuilder;
use NeoPHP\NeoAdmin\Crud\EntityFormFactory;

/**
 * @internal
 */
class TranslationLocaleType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'admin_translation_locale';
    }

    public function configureOptions(): array
    {
        return [
            'fields' => [],
            'required' => false,
        ];
    }

    public function buildForm(FormBuilder $builder, array $options): void
    {
        foreach ((array) $options['fields'] as $name => $field) {
            $field = (array) $field;
            $type = (string) ($field['type'] ?? 'text');
            $fieldOptions = ['label' => $field['label'] ?? null, 'required' => (bool) ($field['required'] ?? false)];

            if ($type === 'textarea') {
                $fieldOptions['attr'] = ['rows' => 4];
            }

            foreach (EntityFormFactory::FIELD_OPTIONS as $option) {
                if (array_key_exists($option, $field)) {
                    $fieldOptions[$option] = $field[$option];
                }
            }

            $builder->add((string) $name, EntityFormFactory::TYPES[$type] ?? $type, array_replace($fieldOptions, (array) ($field['options'] ?? [])));
        }
    }
}