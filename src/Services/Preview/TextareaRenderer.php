<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

class TextareaRenderer implements FieldRendererInterface
{
    public function render(string $name, string $value, array $meta): string
    {
        $escapedName = e($name);
        $escapedValue = e($value);

        return '<textarea name="' . $escapedName . '" rows="3">' . $escapedValue . '</textarea>';
    }
}
