<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services\Preview;

class CheckboxRenderer implements FieldRendererInterface
{
    public function render(string $name, string $value, array $meta): string
    {
        $escapedName = e($name);
        $checked = ($value === '1' || $value === 'true' || $value === 'yes') ? ' checked' : '';
        $caption = !empty($meta['caption']) ? e($meta['caption']) : 'Yes';

        return '<div class="checkbox-wrap">'
            . '<input type="checkbox" name="' . $escapedName . '" value="1"' . $checked . '>'
            . '<label>' . $caption . '</label>'
            . '</div>';
    }
}
