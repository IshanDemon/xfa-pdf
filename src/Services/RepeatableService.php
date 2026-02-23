<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Xfa\Pdf\Exceptions\FieldNotFoundException;

class RepeatableService
{
    private NamespaceService $namespaceService;

    public function __construct(NamespaceService $namespaceService)
    {
        $this->namespaceService = $namespaceService;
    }

    /**
     * Get all existing items from a repeatable container in the datasets.
     *
     * @return array<int, array<string, string>>
     */
    public function getItems(DOMDocument $dom, string $sectionName, string $container): array
    {
        $xpath = $this->createXPath($dom);
        $containerNode = $this->findContainer($xpath, $sectionName, $container);

        if (!$containerNode) {
            return [];
        }

        $items = [];
        foreach ($containerNode->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $items[] = $this->elementToArray($child);
        }

        return $items;
    }

    /**
     * Add a new item to a repeatable container (mirrors XFA instanceManager.addInstance).
     *
     * @param array<string, string> $data Field name => value pairs
     */
    public function addItem(DOMDocument $dom, string $sectionName, string $container, array $data): DOMDocument
    {
        $xpath = $this->createXPath($dom);
        $containerNode = $this->findContainer($xpath, $sectionName, $container);

        if (!$containerNode) {
            throw FieldNotFoundException::containerNotFound($sectionName, $container);
        }

        // Determine element name from existing children, or use container name singularized
        $elementName = $this->guessElementName($containerNode);
        $newElement = $this->createElementLikeParent($dom, $containerNode, $elementName);

        foreach ($data as $fieldName => $value) {
            $fieldElement = $this->createElementLikeParent($dom, $newElement, $fieldName);
            $fieldElement->appendChild($dom->createTextNode($value ?? ''));
            $newElement->appendChild($fieldElement);
        }

        $containerNode->appendChild($newElement);

        return $dom;
    }

    /**
     * Remove an item at a specific index (mirrors XFA instanceManager.removeInstance).
     */
    public function removeItem(DOMDocument $dom, string $sectionName, string $container, int $index): DOMDocument
    {
        $xpath = $this->createXPath($dom);
        $containerNode = $this->findContainer($xpath, $sectionName, $container);

        if (!$containerNode) {
            throw FieldNotFoundException::containerNotFound($sectionName, $container);
        }

        $elements = [];
        foreach ($containerNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $elements[] = $child;
            }
        }

        if (!isset($elements[$index])) {
            throw FieldNotFoundException::atPath("{$sectionName}/{$container}[{$index}]");
        }

        $containerNode->removeChild($elements[$index]);

        return $dom;
    }

    /**
     * Update an item at a specific index.
     *
     * @param array<string, string> $data Field name => value pairs
     */
    public function updateItem(DOMDocument $dom, string $sectionName, string $container, int $index, array $data): DOMDocument
    {
        $xpath = $this->createXPath($dom);
        $containerNode = $this->findContainer($xpath, $sectionName, $container);

        if (!$containerNode) {
            throw FieldNotFoundException::containerNotFound($sectionName, $container);
        }

        $elements = [];
        foreach ($containerNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $elements[] = $child;
            }
        }

        if (!isset($elements[$index])) {
            throw FieldNotFoundException::atPath("{$sectionName}/{$container}[{$index}]");
        }

        $element = $elements[$index];

        foreach ($data as $fieldName => $value) {
            $found = false;
            foreach ($element->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $fieldName) {
                    while ($child->firstChild) {
                        $child->removeChild($child->firstChild);
                    }
                    $child->appendChild($dom->createTextNode($value ?? ''));
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $fieldElement = $this->createElementLikeParent($dom, $element, $fieldName);
                $fieldElement->appendChild($dom->createTextNode($value ?? ''));
                $element->appendChild($fieldElement);
            }
        }

        return $dom;
    }

    /**
     * Replace all items in a repeatable container (ported from MagExportService.applyRepeatableToDOM).
     *
     * @param array<int, array<string, string>> $items Array of item records
     */
    public function setItems(
        DOMDocument $dom,
        string $sectionName,
        string $container,
        string $element,
        array $items
    ): DOMDocument {
        $xpath = $this->createXPath($dom);
        $containerNode = $this->findContainer($xpath, $sectionName, $container);

        if (!$containerNode) {
            throw FieldNotFoundException::containerNotFound($sectionName, $container);
        }

        // Remove existing child elements with the repeatable element name
        $existingItems = [];
        foreach ($containerNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $element) {
                $existingItems[] = $child;
            }
        }
        foreach ($existingItems as $existing) {
            $containerNode->removeChild($existing);
        }

        // Create new elements for each item
        foreach ($items as $item) {
            $newElement = $this->createElementLikeParent($dom, $containerNode, $element);

            foreach ($item as $fieldName => $value) {
                $fieldElement = $this->createElementLikeParent($dom, $newElement, $fieldName);
                $fieldElement->appendChild($dom->createTextNode($value ?? ''));
                $newElement->appendChild($fieldElement);
            }

            $containerNode->appendChild($newElement);
        }

        return $dom;
    }

    /**
     * Get the field names and types that a new empty row should have,
     * based on the template XML.
     *
     * @return array<string, string> Field name => type
     */
    public function getRowTemplate(string $templateXml, string $container): array
    {
        $templateService = new TemplateService();
        $meta = $templateService->getFieldMetadata($templateXml);
        $repeatables = $templateService->getRepeatableSubforms($templateXml);

        if (!isset($repeatables[$container])) {
            return [];
        }

        $fields = [];
        foreach ($repeatables[$container]['fields'] as $fieldRef) {
            $parts = explode('.', $fieldRef);
            $fieldName = end($parts);
            $type = $meta[$fieldRef]['type'] ?? 'text';
            $fields[$fieldName] = $type;
        }

        return $fields;
    }

    /**
     * Create a child element using the same namespace as the parent.
     * Ported from MagExportService.createElementLikeParent.
     */
    private function createElementLikeParent(DOMDocument $dom, DOMNode $parent, string $localName): DOMElement
    {
        $namespace = $parent->namespaceURI;
        $prefix = $parent->prefix;

        if ($namespace) {
            $qualifiedName = $prefix ? "{$prefix}:{$localName}" : $localName;

            return $dom->createElementNS($namespace, $qualifiedName);
        }

        return $dom->createElement($localName);
    }

    private function createXPath(DOMDocument $dom): DOMXPath
    {
        $xml = $dom->saveXML();
        $this->namespaceService->detect($xml);

        $xpath = new DOMXPath($dom);
        $this->namespaceService->registerOnXPath($xpath);

        return $xpath;
    }

    /**
     * Find a container element in the datasets DOM.
     */
    private function findContainer(DOMXPath $xpath, string $sectionName, string $container): ?DOMNode
    {
        $query = '//xfa:data/*/*[local-name()="' . $sectionName . '"]'
            . '//*[local-name()="' . $container . '"]';

        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0);
    }

    /**
     * Guess the element name from existing child elements.
     */
    private function guessElementName(DOMNode $container): string
    {
        foreach ($container->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return $child->localName;
            }
        }

        return 'item';
    }

    /**
     * Convert a DOM element to a flat associative array.
     *
     * @return array<string, string>
     */
    private function elementToArray(DOMNode $element): array
    {
        $result = [];

        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $result[$child->localName] = $child->textContent;
            }
        }

        return $result;
    }
}
