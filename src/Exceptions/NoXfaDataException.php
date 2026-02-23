<?php

declare(strict_types=1);

namespace Xfa\Pdf\Exceptions;

class NoXfaDataException extends XfaPdfException
{
    public static function noDatasetsFound(): self
    {
        return new static('No XFA datasets found in the PDF. Ensure the PDF contains XFA form data.');
    }

    public static function noTemplateFound(): self
    {
        return new static('No XFA template found in the PDF. Ensure the PDF contains an XFA template packet.');
    }

    public static function failedToParse(): self
    {
        return new static('Failed to parse XFA datasets XML. The XML may be malformed.');
    }
}
