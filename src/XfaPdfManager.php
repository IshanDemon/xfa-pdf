<?php

declare(strict_types=1);

namespace Xfa\Pdf;

use DOMDocument;
use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\PdfBinaryService;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\Services\RepeatableService;
use Xfa\Pdf\Services\TemplateService;

/**
 * Facade target — wires all services together for convenient access.
 */
class XfaPdfManager
{
    private PdfBinaryService $pdfBinary;
    private DatasetService $dataset;
    private TemplateService $template;
    private RepeatableService $repeatable;
    private PreviewService $preview;
    private NamespaceService $namespace;

    public function __construct(
        PdfBinaryService $pdfBinary,
        DatasetService $dataset,
        TemplateService $template,
        RepeatableService $repeatable,
        PreviewService $preview,
        NamespaceService $namespace
    ) {
        $this->pdfBinary = $pdfBinary;
        $this->dataset = $dataset;
        $this->template = $template;
        $this->repeatable = $repeatable;
        $this->preview = $preview;
        $this->namespace = $namespace;
    }

    /**
     * Load a PDF file and return an XfaPdf value object.
     */
    public function load(string $filePath): XfaPdf
    {
        $pdf = clone $this->pdfBinary;
        $pdf->load($filePath);

        $dom = $this->dataset->extract($pdf);
        $templateXml = $this->template->extract($pdf);

        return new XfaPdf($pdf, $dom, $templateXml, $filePath);
    }

    /**
     * Get all section names from a loaded XFA PDF.
     *
     * @return string[]
     */
    public function getSections(XfaPdf $xfaPdf): array
    {
        return $this->dataset->getSections($xfaPdf->getDatasetsDom());
    }

    /**
     * Get all fields for a section.
     *
     * @return array<string, mixed>
     */
    public function getFields(XfaPdf $xfaPdf, string $sectionName): array
    {
        return $this->dataset->getFields($xfaPdf->getDatasetsDom(), $sectionName);
    }

    /**
     * Get a single field value.
     */
    public function getFieldValue(XfaPdf $xfaPdf, string $fieldPath): ?string
    {
        return $this->dataset->getFieldValue($xfaPdf->getDatasetsDom(), $fieldPath);
    }

    /**
     * Set a single field value.
     */
    public function setFieldValue(XfaPdf $xfaPdf, string $fieldPath, string $value): XfaPdf
    {
        $dom = $this->dataset->setFieldValue($xfaPdf->getDatasetsDom(), $fieldPath, $value);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Set multiple field values at once.
     *
     * @param array<string, string> $fields
     */
    public function setFieldValues(XfaPdf $xfaPdf, array $fields): XfaPdf
    {
        $dom = $this->dataset->setFieldValues($xfaPdf->getDatasetsDom(), $fields);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Get field metadata from the template.
     *
     * @return array<string, array{type: string, options: string[], caption: string}>
     */
    public function getFieldMetadata(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        if (!$templateXml) {
            return [];
        }

        return $this->template->getFieldMetadata($templateXml);
    }

    /**
     * Get repeatable subform metadata.
     *
     * @return array<string, array{fields: string[], min: int, max: int}>
     */
    public function getRepeatableSubforms(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        if (!$templateXml) {
            return [];
        }

        return $this->template->getRepeatableSubforms($templateXml);
    }

    /**
     * Get navigation section labels from the template.
     *
     * @return array<int, string>
     */
    public function getNavigationSections(XfaPdf $xfaPdf): array
    {
        $templateXml = $xfaPdf->getTemplateXml();

        if (!$templateXml) {
            return [];
        }

        return $this->template->getNavigationSections($templateXml);
    }

    /**
     * Get items from a repeatable container.
     *
     * @return array<int, array<string, string>>
     */
    public function getRepeatableItems(XfaPdf $xfaPdf, string $sectionName, string $container): array
    {
        return $this->repeatable->getItems($xfaPdf->getDatasetsDom(), $sectionName, $container);
    }

    /**
     * Add an item to a repeatable container.
     *
     * @param array<string, string> $data
     */
    public function addRepeatableItem(XfaPdf $xfaPdf, string $sectionName, string $container, array $data): XfaPdf
    {
        $dom = $this->repeatable->addItem($xfaPdf->getDatasetsDom(), $sectionName, $container, $data);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Remove an item from a repeatable container by index.
     */
    public function removeRepeatableItem(XfaPdf $xfaPdf, string $sectionName, string $container, int $index): XfaPdf
    {
        $dom = $this->repeatable->removeItem($xfaPdf->getDatasetsDom(), $sectionName, $container, $index);
        $xfaPdf->setDatasetsDom($dom);

        return $xfaPdf;
    }

    /**
     * Replace all items in a repeatable container.
     *
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
     * Generate an HTML preview of the XFA PDF.
     */
    public function generatePreview(XfaPdf $xfaPdf, array $sectionLabels = []): string
    {
        $sections = $this->getSections($xfaPdf);

        $allData = [];
        foreach ($sections as $name) {
            $allData[$name] = $this->getFields($xfaPdf, $name);
        }

        $fieldMeta = $this->getFieldMetadata($xfaPdf);
        $repeatables = $this->getRepeatableSubforms($xfaPdf);

        if (empty($sectionLabels)) {
            foreach ($sections as $name) {
                $sectionLabels[$name] = PreviewService::humanize($name);
            }
        }

        return $this->preview->generate($allData, $fieldMeta, $repeatables, $sectionLabels);
    }

    /**
     * Save the modified datasets back to the PDF file.
     */
    public function save(XfaPdf $xfaPdf, ?string $outputPath = null): bool
    {
        return $xfaPdf->getPdf()->writeIncrementalUpdate($xfaPdf->getDatasetsDom(), $outputPath);
    }

    /**
     * Get the raw XFA datasets XML string.
     */
    public function getRawXml(XfaPdf $xfaPdf): string
    {
        return $this->dataset->extractRaw($xfaPdf->getPdf());
    }

    /**
     * Convert datasets to a nested PHP array.
     *
     * @return array<string, mixed>
     */
    public function toArray(XfaPdf $xfaPdf): array
    {
        return $this->dataset->toArray($xfaPdf->getDatasetsDom());
    }

    // Service accessors for advanced usage

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
        return $this->preview;
    }

    public function namespaces(): NamespaceService
    {
        return $this->namespace;
    }
}
