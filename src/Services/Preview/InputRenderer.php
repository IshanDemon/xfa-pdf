<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

class InputRenderer implements FieldRendererInterface
{
    public function render(string $name, string $value, array $meta): string
    {
        $type = $meta['type'] ?? 'text';
        $escapedName = e($name);
        $escapedValue = e($value);

        if ($type === 'date') {
            // Convert DD/MM/YYYY to YYYY-MM-DD for HTML date input
            $htmlDate = $value;
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $d)) {
                $htmlDate = $d[3] . '-' . $d[2] . '-' . $d[1];
            }

            return '<input type="date" name="' . $escapedName . '" value="' . e($htmlDate) . '">';
        }

        if ($type === 'number') {
            return '<input type="number" name="' . $escapedName . '" value="' . $escapedValue . '">';
        }

        return '<input type="text" name="' . $escapedName . '" value="' . $escapedValue . '">';
    }
}
