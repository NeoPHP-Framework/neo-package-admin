<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Helper\View;

use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Component\View\Contract\ViewSafeHtmlInterface;
use NeoPHP\NeoAdmin\Crud\ValueRenderer;
use NeoPHP\NeoAdmin\Definition\FieldDefinition;

/**
 * @internal
 */
class AdminValueViewHelper implements ViewFunctionInterface, ViewSafeHtmlInterface
{
    public function __construct(protected ValueRenderer $values)
    {
    }

    public function getName(): string
    {
        return 'admin_value';
    }

    public function __invoke(object $entity, FieldDefinition $field, string $page = 'index'): string
    {
        return $this->values->render($entity, $field, $page);
    }
}