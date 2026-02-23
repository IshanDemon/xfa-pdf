<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

class SelectRenderer implements FieldRendererInterface
{
    public function render(string $name, string $value, array $meta): string
    {
        $options = $meta['options'] ?? [];
        $escapedName = e($name);
        $escapedValue = e($value);

        $html = '<select name="' . $escapedName . '">';

        if (empty($options) || !in_array($value, $options)) {
            $html .= '<option value="' . $escapedValue . '" selected>' . $escapedValue . '</option>';
        }

        foreach ($options as $opt) {
            $selected = ($opt === $value) ? ' selected' : '';
            $html .= '<option value="' . e($opt) . '"' . $selected . '>' . e($opt) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
