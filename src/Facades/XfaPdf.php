<?php

declare(strict_types=1);

namespace Xfa\Pdf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Xfa\Pdf\XfaPdfManager setFile(string $filePath)
 * @method static array read(string $sectionName)
 * @method static string|null readField(string $sectionName, string $fieldName)
 * @method static \Xfa\Pdf\XfaPdfManager update(string $sectionName, array $fields)
 * @method static \Xfa\Pdf\XfaPdfManager updateField(string $sectionName, string $fieldName, string $value)
 * @method static string preview()
 * @method static bool save(?string $outputPath = null)
 * @method static array sections()
 * @method static string rawXml()
 * @method static array toArray()
 * @method static array fieldMetadata()
 * @method static array repeatableSubforms()
 * @method static array navigationSections()
 * @method static \Xfa\Pdf\Section section(string $sectionName)
 * @method static \Xfa\Pdf\Section personalDetails()
 * @method static \Xfa\Pdf\Section scopeOfWork()
 * @method static \Xfa\Pdf\Section previousAppraisals()
 * @method static \Xfa\Pdf\Section lastYearsPdp()
 * @method static \Xfa\Pdf\Section cpd()
 * @method static \Xfa\Pdf\Section qualityImprovement()
 * @method static \Xfa\Pdf\Section significantEvents()
 * @method static \Xfa\Pdf\Section feedback()
 * @method static \Xfa\Pdf\Section complaints()
 * @method static \Xfa\Pdf\Section achievements()
 * @method static \Xfa\Pdf\Section probity()
 * @method static \Xfa\Pdf\Section additionalInfo()
 * @method static \Xfa\Pdf\Section supportingInformation()
 * @method static \Xfa\Pdf\Section preAppraisalPrep()
 * @method static \Xfa\Pdf\Section checklist()
 * @method static \Xfa\Pdf\Section agreedPdp()
 * @method static \Xfa\Pdf\Section appraisalSummary()
 * @method static \Xfa\Pdf\Section appraisalOutputs()
 * @method static \Xfa\Pdf\Section commonSections()
 * @method static \Xfa\Pdf\Section formControls()
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
