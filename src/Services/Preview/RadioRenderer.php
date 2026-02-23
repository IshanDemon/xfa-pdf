<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

class RadioRenderer implements FieldRendererInterface
{
    public function render(string $name, string $value, array $meta): string
    {
        $options = $meta['options'] ?? [];
        $escapedName = e($name);

        $html = '<div class="radio-group">';

        foreach ($options as $opt) {
            $checked = ($opt === $value) ? ' checked' : '';
            $html .= '<label>';
            $html .= '<input type="radio" name="' . $escapedName . '" value="' . e($opt) . '"' . $checked . '>';
            $html .= ' ' . e(ucfirst($opt));
            $html .= '</label>';
        }

        $html .= '</div>';

        return $html;
    }
}
