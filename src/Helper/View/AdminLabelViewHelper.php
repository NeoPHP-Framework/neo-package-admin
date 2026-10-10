<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Helper\View;

use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\NeoAdmin\Crud\ValueRenderer;

/**
 * @internal
 */
class AdminLabelViewHelper implements ViewFunctionInterface
{
    public function __construct(protected ValueRenderer $values)
    {
    }

    public function getName(): string
    {
        return 'admin_label';
    }

    public function __invoke(object $entity): string
    {
        return $this->values->label($entity);
    }
}