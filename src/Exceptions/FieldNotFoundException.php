<?php

declare(strict_types=1);

namespace Xfa\Pdf\Exceptions;

class FieldNotFoundException extends XfaPdfException
{
    public static function atPath(string $fieldPath): self
    {
        return new static("Field not found at path: {$fieldPath}");
    }

    public static function containerNotFound(string $sectionName, string $containerName): self
    {
        return new static(
            "Repeatable container '{$containerName}' not found in section '{$sectionName}'."
        );
    }
}
