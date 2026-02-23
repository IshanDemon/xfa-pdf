<?php

declare(strict_types=1);

namespace Xfa\Pdf\Exceptions;

class InvalidPdfException extends XfaPdfException
{
    public static function fileNotFound(string $path): self
    {
        return new static("PDF file not found: {$path}");
    }

    public static function unreadable(string $path): self
    {
        return new static("PDF file is not readable: {$path}");
    }

    public static function invalidFormat(): self
    {
        return new static('The file does not appear to be a valid PDF.');
    }
}
