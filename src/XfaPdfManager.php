<?php

declare(strict_types=1);

namespace Xfa\Pdf;

use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\PdfBinaryService;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\Services\RepeatableService;
use Xfa\Pdf\Services\TemplateService;

/**
 * Main entry point for the XFA PDF package.
 *
 * Simple, stateful API:
 *   $xfa->setFile('path/to/form.pdf');
 *   $xfa->read('Section_3_AppraisalDetails');
 *   $xfa->readField('Section_3_AppraisalDetails', 'appraisalYear');
 *   $xfa->update('Section_3_AppraisalDetails', ['field' => 'value']);
 *   $xfa->updateField('Section_3_AppraisalDetails', 'appraisalYear', '2025');
 *   $xfa->preview();
 *   $xfa->save();
 *
 * Fluent section access:
 *   $xfa->personalDetails()->read();
 *   $xfa->scopeOfWork()->updateField('changesToPractice', 'New value');
 *   $xfa->cpd()->readField('commentaryOnActivities');
 */
class XfaPdfManager
{
    private PdfBinaryService $pdfBinary;
    private DatasetService $dataset;
    private TemplateService $template;
    private RepeatableService $repeatable;
    private PreviewService $previewService;
    private NamespaceService $namespace;

    /** @var XfaPdf|null Internal state after setFile() */
    private ?XfaPdf $loaded = null;

    /**
     * Section name mapping: method name => XFA section name.
     */
    private const SECTION_MAP = [
        'personalDetails'       => 'Section_3_AppraisalDetails',
        'scopeOfWork'           => 'Section_4_ScopeOfWork',
        'previousAppraisals'    => 'Section_5_PreviousAppraisals',
        'lastYearsPdp'          => 'Section_6_LastYearsPDP',
        'cpd'                   => 'Section_7_CPD',
        'qualityImprovement'    => 'Section_8_QualityImprovement',
        'significantEvents'     => 'Section_9_SignificantEvents',
        'feedback'              => 'Section_10_Feedback',
        'complaints'            => 'Section_11_Complaints',
        'achievements'          => 'Section_12_AchievementsChallanges',
        'probity'               => 'Section_13_Probity',
        'additionalInfo'        => 'Section_14_AdditionalInfo',
        'supportingInformation' => 'Section_15_SupportingInformation',
        'preAppraisalPrep'      => 'Section_16_preApprisalPrep',
        'checklist'             => 'Section_17_Checklist',
        'agreedPdp'             => 'Section_18_TheAgreedPDP',
        'appraisalSummary'      => 'Section_19_AppraisalSummary',
        'appraisalOutputs'      => 'Section_20_AppraisalOutputs',
        'commonSections'        => 'CommonSections',
        'formControls'          => 'FormControls',
    ];

    public function __construct(
        PdfBinaryService $pdfBinary,
        DatasetService $dataset,
        TemplateService $template,
        RepeatableService $repeatable,
        PreviewService $previewService,
        NamespaceService $namespace
    ) {
        $this->pdfBinary = $pdfBinary;
        $this->dataset = $dataset;
        $this->template = $template;
        $this->repeatable = $repeatable;
        $this->previewService = $previewService;
        $this->namespace = $namespace;
    }

    // =========================================================================
    // Simple Stateful API
    // =========================================================================

    /**
     * Load an XFA PDF file. Call this before any other method.
     *
     * @param string $filePath Path to the XFA PDF file
     */
    public function setFile(string $filePath): self
    {
        $pdf = clone $this->pdfBinary;
        $pdf->load($filePath);

        $dom = $this->dataset->extract($pdf);
        $templateXml = $this->template->extract($pdf);

        $this->loaded = new XfaPdf($pdf, $dom, $templateXml, $filePath);

        return $this;
    }

    /**
     * Read all fields in a section.
     *
     * @return array<string, mixed>
     */
    public function read(string $sectionName): array
    {
        $this->ensureLoaded();

        return $this->dataset->getFields($this->loaded->getDatasetsDom(), $sectionName);
    }

    /**
     * Read a single field value from a section.
     */
    public function readField(string $sectionName, string $fieldName): ?string
    {
        $this->ensureLoaded();

        $fieldPath = $sectionName . '/' . $fieldName;

        return $this->dataset->getFieldValue($this->loaded->getDatasetsDom(), $fieldPath);
    }

    /**
     * Update multiple fields in a section.
     *
     * @param array<string, string> $fields ['fieldName' => 'value', ...]
     */
    public function update(string $sectionName, array $fields): self
    {
        $this->ensureLoaded();

        // Prefix each field name with the section name
        $prefixed = [];
        foreach ($fields as $fieldName => $value) {
            $prefixed[$sectionName . '/' . $fieldName] = $value;
        }

        $dom = $this->dataset->setFieldValues($this->loaded->getDatasetsDom(), $prefixed);
        $this->loaded->setDatasetsDom($dom);

        return $this;
    }

    /**
     * Update a single field in a section.
     */
    public function updateField(string $sectionName, string $fieldName, string $value): self
    {
        $this->ensureLoaded();

        $fieldPath = $sectionName . '/' . $fieldName;
        $dom = $this->dataset->setFieldValue($this->loaded->getDatasetsDom(), $fieldPath, $value);
        $this->loaded->setDatasetsDom($dom);

        return $this;
    }

    /**
     * Generate an HTML preview of the loaded XFA PDF.
     */
    public function preview(): string
    {
        $this->ensureLoaded();

        return $this->generatePreviewInternal($this->loaded);
    }

    /**
     * Save changes back to the PDF file.
     *
     * @param string|null $outputPath Save to a different file (null = overwrite original)
     */
    public function save(?string $outputPath = null): bool
    {
        $this->ensureLoaded();

        return $this->loaded->getPdf()->writeIncrementalUpdate(
            $this->loaded->getDatasetsDom(),
            $outputPath
        );
    }

    /**
     * Get all section names from the loaded PDF.
     *
     * @return string[]
     */
    public function sections(): array
    {
        $this->ensureLoaded();

        return $this->dataset->getSections($this->loaded->getDatasetsDom());
    }

    /**
     * Get the raw XFA datasets XML string.
     */
    public function rawXml(): string
    {
        $this->ensureLoaded();

        return $this->dataset->extractRaw($this->loaded->getPdf());
    }

    /**
     * Convert all datasets to a nested PHP array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->ensureLoaded();

        return $this->dataset->toArray($this->loaded->getDatasetsDom());
    }

    /**
     * Get field metadata from the template (types, options, captions).
     *
     * @return array<string, array{type: string, options: string[], caption: string}>
     */
    public function fieldMetadata(): array
    {
        $this->ensureLoaded();

        $templateXml = $this->loaded->getTemplateXml();

        return $templateXml ? $this->template->getFieldMetadata($templateXml) : [];
    }

    /**
     * Get repeatable subform metadata.
     *
     * @return array<string, array{fields: string[], min: int, max: int}>
     */
    public function repeatableSubforms(): array
    {
        $this->ensureLoaded();

        $templateXml = $this->loaded->getTemplateXml();

        return $templateXml ? $this->template->getRepeatableSubforms($templateXml) : [];
    }

    /**
     * Get navigation section labels from the template.
     *
     * @return array<int, string>
     */
    public function navigationSections(): array
    {
        $this->ensureLoaded();

        $templateXml = $this->loaded->getTemplateXml();

        return $templateXml ? $this->template->getNavigationSections($templateXml) : [];
    }

    /**
     * Get conditional visibility rules extracted from XFA template event scripts.
     *
     * @return array<string, array{targets: string[], visibleWhen: array<string, string[]>}>
     */
    public function conditionalRules(): array
    {
        $this->ensureLoaded();

        $templateXml = $this->loaded->getTemplateXml();

        return $templateXml ? $this->template->getConditionalRules($templateXml) : [];
    }

    // =========================================================================
    // Section Accessors (fluent)
    // =========================================================================

    /**
     * Access a section by its XFA name (dynamic).
     */
    public function section(string $sectionName): Section
    {
        return new Section($this, $sectionName);
    }

    /** Section 3: Personal Details / Applicant Details */
    public function personalDetails(): Section
    {
        return new Section($this, self::SECTION_MAP['personalDetails']);
    }

    /** Section 4: Scope of Work */
    public function scopeOfWork(): Section
    {
        return new Section($this, self::SECTION_MAP['scopeOfWork']);
    }

    /** Section 5: Previous Appraisals */
    public function previousAppraisals(): Section
    {
        return new Section($this, self::SECTION_MAP['previousAppraisals']);
    }

    /** Section 6: Last Year's PDP */
    public function lastYearsPdp(): Section
    {
        return new Section($this, self::SECTION_MAP['lastYearsPdp']);
    }

    /** Section 7: Continuing Professional Development */
    public function cpd(): Section
    {
        return new Section($this, self::SECTION_MAP['cpd']);
    }

    /** Section 8: Quality Improvement Activity */
    public function qualityImprovement(): Section
    {
        return new Section($this, self::SECTION_MAP['qualityImprovement']);
    }

    /** Section 9: Significant Events */
    public function significantEvents(): Section
    {
        return new Section($this, self::SECTION_MAP['significantEvents']);
    }

    /** Section 10: Colleague and Patient Feedback */
    public function feedback(): Section
    {
        return new Section($this, self::SECTION_MAP['feedback']);
    }

    /** Section 11: Complaints and Compliments */
    public function complaints(): Section
    {
        return new Section($this, self::SECTION_MAP['complaints']);
    }

    /** Section 12: Achievements, Challenges and Aspirations */
    public function achievements(): Section
    {
        return new Section($this, self::SECTION_MAP['achievements']);
    }

    /** Section 13: Probity and Health */
    public function probity(): Section
    {
        return new Section($this, self::SECTION_MAP['probity']);
    }

    /** Section 14: Additional Information */
    public function additionalInfo(): Section
    {
        return new Section($this, self::SECTION_MAP['additionalInfo']);
    }

    /** Section 15: Supporting Information */
    public function supportingInformation(): Section
    {
        return new Section($this, self::SECTION_MAP['supportingInformation']);
    }

    /** Section 16: Pre-Appraisal Preparation */
    public function preAppraisalPrep(): Section
    {
        return new Section($this, self::SECTION_MAP['preAppraisalPrep']);
    }

    /** Section 17: Checklist */
    public function checklist(): Section
    {
        return new Section($this, self::SECTION_MAP['checklist']);
    }

    /** Section 18: The Agreed PDP */
    public function agreedPdp(): Section
    {
        return new Section($this, self::SECTION_MAP['agreedPdp']);
    }

    /** Section 19: Appraisal Summary */
    public function appraisalSummary(): Section
    {
        return new Section($this, self::SECTION_MAP['appraisalSummary']);
    }

    /** Section 20: Appraisal Outputs */
    public function appraisalOutputs(): Section
    {
        return new Section($this, self::SECTION_MAP['appraisalOutputs']);
    }

    /** Common Sections (branding, metadata, declarations) */
    public function commonSections(): Section
    {
        return new Section($this, self::SECTION_MAP['commonSections']);
    }

    /** Form Controls (form status) */
    public function formControls(): Section
    {
        return new Section($this, self::SECTION_MAP['formControls']);
    }

    // =========================================================================
    // Internal API (used by Controller, CLI, and advanced usage)
    // =========================================================================

    /**
     * Load a PDF and return the internal XfaPdf value object.
     * Used by the controller and CLI command.
     *
     * @internal
     */
    public function load(string $filePath): XfaPdf
    {
        $this->setFile($filePath);

        return $this->loaded;
    }

    /**
     * Get all section names from an XfaPdf value object.
     *
     * @internal
     * @return string[]
     */
    public function getSections(XfaPdf $xfaPdf): array
    {
        return $this->dataset->getSections($xfaPdf->getDatasetsDom());
    }

    /**
     * Get all fields for a section from an XfaPdf value object.
     *
     * @internal
     * @return array<string, mixed>
     */
    public function getFields(XfaPdf $xfaPdf, string $sectionName): array
    {
        return $this->dataset->getFields($xfaPdf->getDatasetsDom(), $sectionName);
    }

    /**
     * Get a single field value from an XfaPdf value object.
     *
     * @internal
     */
    public function getFieldValue(XfaPdf $xfaPdf, string $fieldPath): ?string
    {
        return $this->dataset->getFieldValue($xfaPdf->getDatasetsDom(), $fieldPath);
    }

    /**
     * Set a single field value on an XfaPdf value object.
     *
     * @internal
     */
    public function setFieldValue(XfaPdf $xfaPdf, string $fieldPath, string $value): XfaPdf
    {
        $dom = $this->dataset->setFieldValue($xfaPdf->getDatasetsDom(), $fieldPath, $value);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Set multiple field values on an XfaPdf value object.
     *
     * @internal
     * @param array<string, string> $fields
     */
    public function setFieldValues(XfaPdf $xfaPdf, array $fields): XfaPdf
    {
        $dom = $this->dataset->setFieldValues($xfaPdf->getDatasetsDom(), $fields);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Get field metadata from an XfaPdf value object.
     *
     * @internal
     * @return array<string, array{type: string, options: string[], caption: string}>
     */
    public function getFieldMetadata(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        return $templateXml ? $this->template->getFieldMetadata($templateXml) : [];
    }

    /**
     * Get repeatable subform metadata from an XfaPdf value object.
     *
     * @internal
     * @return array<string, array{fields: string[], min: int, max: int}>
     */
    public function getRepeatableSubforms(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        return $templateXml ? $this->template->getRepeatableSubforms($templateXml) : [];
    }

    /**
     * Get navigation sections from an XfaPdf value object.
     *
     * @internal
     * @return array<int, string>
     */
    public function getNavigationSections(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        return $templateXml ? $this->template->getNavigationSections($templateXml) : [];
    }

    /**
     * Get repeatable items from an XfaPdf value object.
     *
     * @internal
     * @return array<int, array<string, string>>
     */
    public function getRepeatableItems(XfaPdf $xfaPdf, string $sectionName, string $container): array
    {
        return $this->repeatable->getItems($xfaPdf->getDatasetsDom(), $sectionName, $container);
    }

    /**
     * Add repeatable item on an XfaPdf value object.
     *
     * @internal
     * @param array<string, string> $data
     */
    public function addRepeatableItem(XfaPdf $xfaPdf, string $sectionName, string $container, array $data): XfaPdf
    {
        $dom = $this->repeatable->addItem($xfaPdf->getDatasetsDom(), $sectionName, $container, $data);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Remove repeatable item on an XfaPdf value object.
     *
     * @internal
     */
    public function removeRepeatableItem(XfaPdf $xfaPdf, string $sectionName, string $container, int $index): XfaPdf
    {
        $dom = $this->repeatable->removeItem($xfaPdf->getDatasetsDom(), $sectionName, $container, $index);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Replace all repeatable items on an XfaPdf value object.
     *
     * @internal
     * @param array<int, array<string, string>> $items
     */
    public function setRepeatableItems(
        XfaPdf $xfaPdf,
        string $sectionName,
        string $container,
        string $element,
        array $items
    ): XfaPdf {
        $dom = $this->repeatable->setItems($xfaPdf->getDatasetsDom(), $sectionName, $container, $element, $items);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Generate HTML preview from an XfaPdf value object.
     *
     * @internal
     */
    public function generatePreview(XfaPdf $xfaPdf, array $sectionLabels = []): string
    {
        return $this->generatePreviewInternal($xfaPdf, $sectionLabels);
    }

    /**
     * Save from an XfaPdf value object.
     *
     * @internal
     */
    public function saveXfaPdf(XfaPdf $xfaPdf, ?string $outputPath = null): bool
    {
        return $xfaPdf->getPdf()->writeIncrementalUpdate($xfaPdf->getDatasetsDom(), $outputPath);
    }

    /**
     * Get raw XML from an XfaPdf value object.
     *
     * @internal
     */
    public function getRawXml(XfaPdf $xfaPdf): string
    {
        return $this->dataset->extractRaw($xfaPdf->getPdf());
    }

    /**
     * Get the internal XfaPdf value object (for controller/CLI).
     *
     * @internal
     */
    public function getLoaded(): ?XfaPdf
    {
        return $this->loaded;
    }

    // =========================================================================
    // Service Accessors (advanced usage)
    // =========================================================================

    public function pdf(): PdfBinaryService
    {
        return $this->pdfBinary;
    }

    public function datasets(): DatasetService
    {
        return $this->dataset;
    }

    public function templates(): TemplateService
    {
        return $this->template;
    }

    public function repeatables(): RepeatableService
    {
        return $this->repeatable;
    }

    public function previews(): PreviewService
    {
        return $this->previewService;
    }

    public function namespaces(): NamespaceService
    {
        return $this->namespace;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function ensureLoaded(): void
    {
        if ($this->loaded === null) {
            throw new \RuntimeException('No PDF loaded. Call setFile() first.');
        }
    }

    private function generatePreviewInternal(XfaPdf $xfaPdf, array $sectionLabels = []): string
    {
        $sections = $this->getSections($xfaPdf);

        $allData = [];
        foreach ($sections as $name) {
            $allData[$name] = $this->getFields($xfaPdf, $name);
        }

        $fieldMeta = $this->getFieldMetadata($xfaPdf);
        $repeatables = $this->getRepeatableSubforms($xfaPdf);
        $conditionalRules = [];
        $templateXml = $xfaPdf->getTemplateXml();
        if ($templateXml) {
            $conditionalRules = $this->template->getConditionalRules($templateXml);
        }

        if (empty($sectionLabels)) {
            foreach ($sections as $name) {
                $sectionLabels[$name] = PreviewService::humanize($name);
            }
        }

        return $this->previewService->generate($allData, $fieldMeta, $repeatables, $sectionLabels, $conditionalRules);
    }
}
