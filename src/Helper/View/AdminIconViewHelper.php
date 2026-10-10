<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Helper\View;

use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Component\View\Contract\ViewSafeHtmlInterface;
use NeoPHP\NeoAdmin\Icon\Icons;

/**
 * @internal
 */
class AdminIconViewHelper implements ViewFunctionInterface, ViewSafeHtmlInterface
{
    public function getName(): string
    {
        return 'admin_icon';
    }

    public function __invoke(?string $name, string $class = 'na-icon'): string
    {
        return Icons::svg($name ?? Icons::FALLBACK, $class);
    }
}