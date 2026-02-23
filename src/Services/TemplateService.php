<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use DOMDocument;
use DOMXPath;

class TemplateService
{
    /**
     * Extract the XFA template XML from the PDF binary.
     */
    public function extract(PdfBinaryService $pdf): ?string
    {
        $binary = $pdf->getBinary();
        $offset = 0;

        while (preg_match('/>>[\r\n]*stream[\r\n]/', $binary, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $from = $m[0][1] + strlen($m[0][0]);
            $endPos = strpos($binary, 'endstream', $from);
            if ($endPos === false) {
                break;
            }
            $raw = substr($binary, $from, $endPos - $from);
            $offset = $endPos + 9;

            $decompressed = $pdf->decompressStream($raw);
            if ($decompressed && strpos($decompressed, 'xfa-template') !== false) {
                return $decompressed;
            }
        }

        return null;
    }

    /**
     * Extract field metadata (types, options, captions) from the XFA template XML.
     *
     * @return array<string, array{type: string, options: string[], caption: string}>
     */
    public function getFieldMetadata(string $templateXml): array
    {
        $dom = new DOMDocument();
        @$dom->loadXML($templateXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('t', 'http://www.xfa.org/schema/xfa-template/3.0/');

        $meta = [];

        // Extract data-bound fields
        $fields = $xpath->query('//t:field[t:bind[@ref]]');
        foreach ($fields as $field) {
            $bind = $xpath->query('t:bind', $field)->item(0);
            $ref = $bind ? $bind->getAttribute('ref') : '';
            if (!$ref || strpos($ref, '$.') !== 0) {
                continue;
            }
            $dataPath = substr($ref, 2); // Remove "$."

            $uiType = 'text';
            $options = [];

            if ($xpath->query('t:ui/t:choiceList', $field)->length > 0) {
                $uiType = 'select';
                $items = $xpath->query('t:items/t:text', $field);
                foreach ($items as $item) {
                    $options[] = $item->textContent;
                }
            } elseif ($xpath->query('t:ui/t:dateTimeEdit', $field)->length > 0) {
                $uiType = 'date';
            } elseif ($xpath->query('t:ui/t:checkButton', $field)->length > 0) {
                $uiType = 'checkbox';
            } elseif ($xpath->query('t:ui/t:numericEdit', $field)->length > 0) {
                $uiType = 'number';
            } elseif ($xpath->query('t:ui/t:textEdit', $field)->length > 0) {
                $textEdit = $xpath->query('t:ui/t:textEdit', $field)->item(0);
                $multiLine = $textEdit->getAttribute('multiLine');
                $uiType = ($multiLine === '1') ? 'textarea' : 'text';
            }

            $caption = '';
            $captionNode = $xpath->query('t:caption/t:value/t:text', $field);
            if ($captionNode->length > 0) {
                $caption = $captionNode->item(0)->textContent;
            }

            $meta[$dataPath] = [
                'type' => $uiType,
                'options' => $options,
                'caption' => $caption,
            ];
        }

        // Extract radio button groups (exclGroup)
        $exclGroups = $xpath->query('//t:exclGroup[t:bind[@ref]]');
        foreach ($exclGroups as $group) {
            $bind = $xpath->query('t:bind', $group)->item(0);
            $ref = $bind ? $bind->getAttribute('ref') : '';
            if (!$ref || strpos($ref, '$.') !== 0) {
                continue;
            }
            $dataPath = substr($ref, 2);

            $options = [];
            $radioItems = $xpath->query('t:field/t:items/t:text', $group);
            foreach ($radioItems as $item) {
                $val = $item->textContent;
                if (!in_array($val, $options)) {
                    $options[] = $val;
                }
            }

            $meta[$dataPath] = [
                'type' => 'radio',
                'options' => $options,
                'caption' => '',
            ];
        }

        return $meta;
    }

    /**
     * Extract repeatable subform metadata from the XFA template.
     *
     * @return array<string, array{fields: string[], min: int, max: int}>
     */
    public function getRepeatableSubforms(string $templateXml): array
    {
        $dom = new DOMDocument();
        @$dom->loadXML($templateXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('t', 'http://www.xfa.org/schema/xfa-template/3.0/');

        $repeatables = [];

        $occurs = $xpath->query('//t:subform/t:occur');
        foreach ($occurs as $occur) {
            $max = $occur->getAttribute('max');
            if ($max !== '-1' && (int) $max <= 1) {
                continue;
            }

            $subform = $occur->parentNode;
            $name = $subform->getAttribute('name');
            if (!$name) {
                continue;
            }

            $min = (int) $occur->getAttribute('min');

            // Check for data binding override
            $bindNode = $xpath->query('t:bind', $subform)->item(0);
            $dataName = $name;
            if ($bindNode) {
                $ref = $bindNode->getAttribute('ref');
                if ($ref && strpos($ref, '$.') === 0) {
                    $bindPath = substr($ref, 2);
                    $bindPath = preg_replace('/\[\*\]$/', '', $bindPath);
                    $parts = explode('.', $bindPath);
                    $dataName = end($parts);
                }
            }

            // Extract field names within this subform
            $fields = [];
            $allFields = $xpath->query('.//t:field[t:bind[@ref]]', $subform);
            foreach ($allFields as $fieldNode) {
                $bind = $xpath->query('t:bind', $fieldNode)->item(0);
                $ref = $bind ? $bind->getAttribute('ref') : '';
                if ($ref && strpos($ref, '$.') === 0) {
                    $fields[] = substr($ref, 2);
                }
            }

            $entry = [
                'fields' => $fields,
                'min' => $min,
                'max' => (int) $max,
            ];

            $repeatables[$dataName] = $entry;
            if ($dataName !== $name) {
                $repeatables[$name] = $entry;
            }
        }

        return $repeatables;
    }

    /**
     * Get the field type for a specific field name from the template.
     */
    public function getFieldType(string $templateXml, string $fieldName): ?string
    {
        $meta = $this->getFieldMetadata($templateXml);

        foreach ($meta as $path => $info) {
            $parts = explode('.', $path);
            if (end($parts) === $fieldName) {
                return $info['type'];
            }
        }

        return null;
    }

    /**
     * Extract navigation section labels from MenuBar button tooltips in the template.
     *
     * @return array<int, string> Section number => label
     */
    public function getNavigationSections(string $templateXml): array
    {
        $dom = new DOMDocument();
        @$dom->loadXML($templateXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('t', 'http://www.xfa.org/schema/xfa-template/3.0/');

        $sections = [];

        // Find MenuBar buttons with tooltips
        $menuBarFields = $xpath->query('//t:subform[@name="MenuBar"]//t:field[starts-with(@name, "Button")]');
        foreach ($menuBarFields as $field) {
            $buttonName = $field->getAttribute('name');

            // Extract number from "ButtonN"
            if (preg_match('/Button(\d+)/', $buttonName, $m)) {
                $num = (int) $m[1];
                $tooltip = $xpath->query('t:assist/t:toolTip', $field);
                if ($tooltip->length > 0) {
                    $sections[$num] = $tooltip->item(0)->textContent;
                }
            }
        }

        ksort($sections);

        return $sections;
    }
}
