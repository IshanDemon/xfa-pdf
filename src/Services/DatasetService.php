<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Xfa\Pdf\Exceptions\FieldNotFoundException;
use Xfa\Pdf\Exceptions\NoXfaDataException;

class DatasetService
{
    private NamespaceService $namespaceService;

    public function __construct(NamespaceService $namespaceService)
    {
        $this->namespaceService = $namespaceService;
    }

    /**
     * Extract and parse the XFA datasets XML from a PDF.
     */
    public function extract(PdfBinaryService $pdf): DOMDocument
    {
        $xml = $this->extractRaw($pdf);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        if (!$dom->loadXML($xml)) {
            throw NoXfaDataException::failedToParse();
        }

        return $dom;
    }

    /**
     * Extract the raw XFA datasets XML string from a PDF.
     */
    public function extractRaw(PdfBinaryService $pdf): string
    {
        $pdf->discoverStreams();

        $streams = $pdf->getDatasetStreams();
        if (empty($streams)) {
            throw NoXfaDataException::noDatasetsFound();
        }

        return $streams[0]['xml'];
    }

    /**
     * Get all section names from the XFA datasets.
     *
     * @return string[]
     */
    public function getSections(DOMDocument $dom): array
    {
        $xpath = $this->createXPath($dom);

        $sections = [];
        $nodes = $xpath->query('//xfa:data/*/*');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $sections[] = $node->localName;
        }

        return $sections;
    }

    /**
     * Get all fields within a section as a nested array.
     *
     * @return array<string, mixed>
     */
    public function getFields(DOMDocument $dom, string $sectionName): array
    {
        $xpath = $this->createXPath($dom);

        $nodes = $xpath->query('//xfa:data/*/*[local-name()="' . $sectionName . '"]/*');

        if ($nodes === false) {
            return [];
        }

        $fields = [];
        foreach ($nodes as $node) {
            $fields[$node->localName] = self::nodeToArray($node);
        }

        return $fields;
    }

    /**
     * Get a specific field value using a slash-separated path.
     * Path format: "Section_3_AppraisalDetails/appraisalYear"
     */
    public function getFieldValue(DOMDocument $dom, string $fieldPath): ?string
    {
        $xpath = $this->createXPath($dom);
        $xpathQuery = $this->buildFieldXPath($fieldPath);

        $nodes = $xpath->query($xpathQuery);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0)->textContent;
    }

    /**
     * Set a single field value in the datasets DOM.
     */
    public function setFieldValue(DOMDocument $dom, string $fieldPath, string $value): DOMDocument
    {
        $xpath = $this->createXPath($dom);
        $xpathQuery = $this->buildFieldXPath($fieldPath);

        $nodes = $xpath->query($xpathQuery);

        if ($nodes === false || $nodes->length === 0) {
            throw FieldNotFoundException::atPath($fieldPath);
        }

        $node = $nodes->item(0);
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($dom->createTextNode($value));

        return $dom;
    }

    /**
     * Set multiple field values at once.
     *
     * @param array<string, string> $fields Associative array of fieldPath => value
     */
    public function setFieldValues(DOMDocument $dom, array $fields): DOMDocument
    {
        $xpath = $this->createXPath($dom);

        foreach ($fields as $fieldPath => $value) {
            $xpathQuery = $this->buildFieldXPath($fieldPath);
            $nodes = $xpath->query($xpathQuery);

            if ($nodes === false || $nodes->length === 0) {
                throw FieldNotFoundException::atPath($fieldPath);
            }

            $node = $nodes->item(0);
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($dom->createTextNode($value));
        }

        return $dom;
    }

    /**
     * Convert the entire datasets DOM to a nested PHP array.
     *
     * @return array<string, mixed>
     */
    public function toArray(DOMDocument $dom): array
    {
        $xpath = $this->createXPath($dom);
        $dataRoot = $xpath->query('//xfa:data/*/*');

        if ($dataRoot === false || $dataRoot->length === 0) {
            return [];
        }

        $result = [];
        foreach ($dataRoot as $node) {
            $result[$node->localName] = self::nodeToArray($node);
        }

        return $result;
    }

    /**
     * Convert a DOM node to a nested array/string recursively.
     *
     * @return mixed
     */
    public static function nodeToArray(DOMNode $node)
    {
        if (!$node->hasChildNodes()) {
            return $node->textContent;
        }

        $hasElementChildren = false;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $hasElementChildren = true;
                break;
            }
        }

        if (!$hasElementChildren) {
            return $node->textContent;
        }

        $result = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $childValue = self::nodeToArray($child);
            $name = $child->localName;

            if (isset($result[$name])) {
                if (!is_array($result[$name]) || !isset($result[$name][0])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $childValue;
            } else {
                $result[$name] = $childValue;
            }
        }

        return $result;
    }

    /**
     * Create a namespace-aware DOMXPath from the DOM document.
     */
    private function createXPath(DOMDocument $dom): DOMXPath
    {
        $xml = $dom->saveXML();
        $this->namespaceService->detect($xml);

        $xpath = new DOMXPath($dom);
        $this->namespaceService->registerOnXPath($xpath);

        return $xpath;
    }

    /**
     * Build an XPath query from a slash-separated field path.
     * Uses local-name() for namespace-agnostic matching.
     */
    private function buildFieldXPath(string $fieldPath): string
    {
        $parts = explode('/', $fieldPath);
        $query = '//xfa:data/*/*';

        foreach ($parts as $part) {
            $query .= '/*[local-name()="' . $part . '"]';
        }

        // If only one part, query directly under the data root children
        if (count($parts) === 1) {
            return '//xfa:data/*/*/*[local-name()="' . $parts[0] . '"]';
        }

        // First part is section, rest are field path
        $query = '//xfa:data/*/*[local-name()="' . $parts[0] . '"]';
        for ($i = 1; $i < count($parts); $i++) {
            $query .= '/*[local-name()="' . $parts[$i] . '"]';
        }

        return $query;
    }
}
