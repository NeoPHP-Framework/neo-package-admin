<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Form;

use NeoPHP\Component\Form\Contract\AbstractType;
use NeoPHP\Component\Form\Contract\FormInterface;
use NeoPHP\Component\Form\Model\FormBuilder;
use NeoPHP\NeoAdmin\Crud\TranslationMapper;

/**
 * @internal
 */
class TranslationsType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'admin_translations';
    }

    public function configureOptions(): array
    {
        return [
            'mapper' => null,
            'translations' => [],
            'required' => false,
        ];
    }

    public function buildForm(FormBuilder $builder, array $options): void
    {
        foreach ((array) ($options['translations']['locales'] ?? []) as $locale => $label) {
            $builder->add((string) $locale, TranslationLocaleType::class, [
                'label' => $label,
                'fields' => (array) ($options['translations']['fields'] ?? []),
                'required' => false,
            ]);
        }
    }

    public function mapDataToForms(mixed $data, array $forms, array $options, FormInterface $form): void
    {
        $values = $this->mapper($options)->values(is_iterable($data) ? $data : [], (array) $options['translations']);

        foreach ($forms as $locale => $child) {
            $child->setData($values[(string) $locale] ?? []);
        }
    }

    public function mapFormsToData(array $forms, mixed $data, array $options, FormInterface $form): mixed
    {
        $owner = $form->getParent()?->getData();

        if (!is_object($owner) || !is_iterable($data)) {
            return $data;
        }

        $submitted = [];

        foreach ($forms as $locale => $child) {
            $submitted[(string) $locale] = (array) $child->getData();
        }

        $this->mapper($options)->sync($owner, $data, $submitted, (array) $options['translations']);

        return $data;
    }

    protected function mapper(array $options): TranslationMapper
    {
        return $options['mapper'];
    }
}