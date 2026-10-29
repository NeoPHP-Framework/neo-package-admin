<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Form;

use NeoPHP\Component\Form\Model\FormView;
use NeoPHP\Component\Form\Renderer\FormRenderer;
use NeoPHP\Component\Form\Theme\DefaultTheme;

class AdminFormTheme extends DefaultTheme
{
    public function formRow(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $vars['row_attr'] = $this->addClass((array) ($vars['row_attr'] ?? []), 'na-form-row');

        return parent::formRow($view, $vars, $renderer);
    }

    public function buttonRow(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $vars['row_attr'] = $this->addClass((array) ($vars['row_attr'] ?? []), 'na-form-row');

        return parent::buttonRow($view, $vars, $renderer);
    }

    public function checkboxRow(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $attr = $this->addClass((array) ($vars['row_attr'] ?? []), 'na-form-row');

        return '<div' . $this->attributes($attr) . '>'
            . '<div class="na-check-row">'
            . $renderer->widget($view, $this->rowWidgetVars($vars))
            . $renderer->label($view, null, $this->rowLabelVars($vars))
            . '</div>'
            . $renderer->help($view, $this->rowHelpVars($vars))
            . $renderer->errors($view)
            . '</div>';
    }

    public function formWidgetSimple(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $type = $vars['type'] ?? 'text';

        if ($type !== 'hidden') {
            $vars['attr'] = $this->addClass((array) ($vars['attr'] ?? []), $type === 'color' ? 'na-color' : $this->input($vars));
        }

        return parent::formWidgetSimple($view, $vars, $renderer);
    }

    public function textareaWidget(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $vars['attr'] = $this->addClass((array) ($vars['attr'] ?? []), $this->input($vars));

        return parent::textareaWidget($view, $vars, $renderer);
    }

    public function checkboxWidget(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $vars['attr'] = $this->addClass((array) ($vars['attr'] ?? []), 'na-checkbox');

        return parent::checkboxWidget($view, $vars, $renderer);
    }

    public function choiceWidget(FormView $view, array $vars, FormRenderer $renderer): string
    {
        if (!($vars['expanded'] ?? false)) {
            $vars['attr'] = $this->addClass((array) ($vars['attr'] ?? []), $this->input($vars));
        }

        return parent::choiceWidget($view, $vars, $renderer);
    }

    public function choiceWidgetExpanded(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $html = '<div' . $this->attributes($this->addClass(['id' => $vars['id']] + (array) $vars['attr'], 'na-choices')) . '>';

        foreach ($this->expandedChoices($vars) as $choice) {
            $choice['attr'] = $this->addClass($choice['attr'], 'na-checkbox');
            $html .= '<div class="na-check-row">' . $this->choiceInput($choice) . '<label' . $this->attributes(['for' => $choice['id']]) . '>' . $this->e($choice['label']) . '</label></div>';
        }

        return $html . '</div>';
    }

    public function adminTranslationsWidget(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $html = '<div' . $this->attributes($this->addClass(['id' => $vars['id']] + (array) ($vars['attr'] ?? []), 'na-grid na-grid-2')) . '>';

        foreach ($view->children as $child) {
            if ($child->isRendered()) {
                continue;
            }

            $html .= '<section class="na-card">'
                . '<div class="na-card-header"><h3 class="na-card-title">' . $this->e($child->vars['label'] ?? $child->vars['name'] ?? '') . '</h3></div>'
                . '<div class="na-card-body">' . $renderer->widget($child) . '</div>'
                . '</section>';
        }

        return $html . '</div>';
    }

    public function buttonWidget(FormView $view, array $vars, FormRenderer $renderer): string
    {
        if (!isset($vars['attr']['class'])) {
            $vars['attr'] = $this->addClass((array) ($vars['attr'] ?? []), ($vars['type'] ?? 'button') === 'submit' ? 'na-btn na-btn-primary' : 'na-btn');
        }

        return parent::buttonWidget($view, $vars, $renderer);
    }

    public function formLabel(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $vars['label_attr'] = $this->addClass((array) ($vars['label_attr'] ?? []), 'na-label');

        $html = parent::formLabel($view, $vars, $renderer);

        if ($html !== '' && ($vars['required'] ?? false)) {
            $html = substr($html, 0, -8) . '<span class="na-required" aria-hidden="true">*</span></label>';
        }

        return $html;
    }

    public function formErrors(FormView $view, array $vars, FormRenderer $renderer): string
    {
        $errors = (array) ($vars['errors'] ?? []);

        if ($errors === []) {
            return '';
        }

        $messages = implode('<br>', array_map(fn (mixed $error): string => $this->e($error), $errors));

        return $view->parent === null || ($vars['compound'] ?? false)
            ? '<div role="alert" class="na-alert na-alert-error na-form-errors">' . $messages . '</div>'
            : '<p class="na-error">' . $messages . '</p>';
    }

    public function formHelp(FormView $view, array $vars, FormRenderer $renderer): string
    {
        if (empty($vars['help'])) {
            return '';
        }

        return '<p' . $this->attributes($this->addClass(['id' => $vars['id'] . '_help'] + (array) ($vars['help_attr'] ?? []), 'na-help')) . '>' . $this->e($vars['help']) . '</p>';
    }

    protected function input(array $vars): string
    {
        return 'na-input' . (($vars['valid'] ?? true) ? '' : ' is-invalid');
    }
}