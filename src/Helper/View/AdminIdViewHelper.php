<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Helper\View;

use NeoPHP\Component\View\Contract\ViewFunctionInterface;
use NeoPHP\Package\Orm\OrmManagerInterface;

/**
 * @internal
 */
class AdminIdViewHelper implements ViewFunctionInterface
{
    public function __construct(protected OrmManagerInterface $orm)
    {
    }

    public function getName(): string
    {
        return 'admin_id';
    }

    public function __invoke(object $entity): string
    {
        return (string) $this->orm->getMetadata($entity)->getIdentifierValue($entity);
    }
}