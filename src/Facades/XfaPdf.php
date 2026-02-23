<?php

declare(strict_types=1);

namespace Xfa\Pdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Xfa\Pdf\XfaPdf load(string $filePath)
 * @method static array getSections(\Xfa\Pdf\XfaPdf $xfaPdf)
 * @method static array getFields(\Xfa\Pdf\XfaPdf $xfaPdf, string $sectionName)
 * @method static string|null getFieldValue(\Xfa\Pdf\XfaPdf $xfaPdf, string $fieldPath)
 * @method static \Xfa\Pdf\XfaPdf setFieldValue(\Xfa\Pdf\XfaPdf $xfaPdf, string $fieldPath, string $value)
 * @method static \Xfa\Pdf\XfaPdf setFieldValues(\Xfa\Pdf\XfaPdf $xfaPdf, array $fields)
 * @method static array getFieldMetadata(\Xfa\Pdf\XfaPdf $xfaPdf)
 * @method static array getRepeatableSubforms(\Xfa\Pdf\XfaPdf $xfaPdf)
 * @method static string generatePreview(\Xfa\Pdf\XfaPdf $xfaPdf, array $sectionLabels = [])
 * @method static bool save(\Xfa\Pdf\XfaPdf $xfaPdf, ?string $outputPath = null)
 *
 * @see \Xfa\Pdf\XfaPdfManager
 */
class XfaPdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'xfa-pdf';
    }
}
