<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

interface FieldRendererInterface
{
    /**
     * Render an HTML form control.
     *
     * @param string $name The input name attribute
     * @param string $value The current field value
     * @param array{type: string, options: string[], caption: string} $meta Field metadata
     * @return string HTML string
     */
    public function render(string $name, string $value, array $meta): string;
}
