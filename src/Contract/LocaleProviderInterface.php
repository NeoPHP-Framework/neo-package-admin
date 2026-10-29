<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Contract;

interface LocaleProviderInterface
{
    /**
     * Returns the locales edited in the "translations" fields, in display order.
     *
     * @return array<string, string|null> The labels by locale code; a null label is replaced by the name of the locale in its own language
     */
    public function getLocales(): array;
}