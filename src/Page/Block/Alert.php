<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Alert extends AbstractBlock
{
    public const TYPES = ['info', 'success', 'warning', 'error'];

    public function __construct(protected string $message, protected string $type = 'info')
    {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/alert.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return ['message' => $this->message, 'type' => in_array($this->type, self::TYPES, true) ? $this->type : 'info'];
    }
}