<?php

declare(strict_types=1);

namespace Xfa\Pdf;

use DOMDocument;
use Xfa\Pdf\Services\PdfBinaryService;

/**
 * Value object holding the loaded state of an XFA PDF.
 * Immutable container passed between services.
 */
class XfaPdf
{
    private PdfBinaryService $pdf;

    private ?DOMDocument $datasetsDom;

    private ?string $templateXml;

    private string $filePath;

    public function __construct(
        PdfBinaryService $pdf,
        ?DOMDocument $datasetsDom = null,
        ?string $templateXml = null,
        string $filePath = ''
    ) {
        $this->pdf = $pdf;
        $this->datasetsDom = $datasetsDom;
        $this->templateXml = $templateXml;
        $this->filePath = $filePath;
    }

    public function getPdf(): PdfBinaryService
    {
        return $this->pdf;
    }

    public function getDatasetsDom(): ?DOMDocument
    {
        return $this->datasetsDom;
    }

    public function setDatasetsDom(DOMDocument $dom): self
    {
        $this->datasetsDom = $dom;

        return $this;
    }

    public function getTemplateXml(): ?string
    {
        return $this->templateXml;
    }

    public function setTemplateXml(string $xml): self
    {
        $this->templateXml = $xml;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
