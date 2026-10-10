<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\Component\Form\Contract\FormInterface;
use NeoPHP\NeoAdmin\Form\AdminFormTheme;
use NeoPHP\NeoAdmin\Page\PageContext;

class Form extends AbstractBlock
{
    public function __construct(
        protected FormInterface $form,
        protected string $submit = 'Save',
        protected ?string $cancel = null,
        protected ?string $title = null,
    ) {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/form.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $view = $this->form->getView();

        if (($view->vars['theme'] ?? null) === null) {
            $view->vars['theme'] = AdminFormTheme::class;
        }

        return [
            'form' => $view,
            'submit' => $this->submit,
            'cancel' => $this->cancel,
            'title' => $this->title,
            'framed' => $context->isFramed(),
        ];
    }
}