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
                $caption = trim($captionNode->item(0)->textContent);
            }

            // Fall back to tooltip if no caption
            if ($caption === '') {
                $tooltipNode = $xpath->query('t:assist/t:toolTip', $field);
                if ($tooltipNode->length > 0) {
                    $caption = trim($tooltipNode->item(0)->textContent);
                }
            }

            // Fall back to speak text if still empty
            if ($caption === '') {
                $speakNode = $xpath->query('t:assist/t:speak', $field);
                if ($speakNode->length > 0) {
                    $caption = trim($speakNode->item(0)->textContent);
                }
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

            $caption = '';
            $captionNode = $xpath->query('t:caption/t:value/t:text', $group);
            if ($captionNode->length > 0) {
                $caption = trim($captionNode->item(0)->textContent);
            }
            if ($caption === '') {
                $tooltipNode = $xpath->query('t:assist/t:toolTip', $group);
                if ($tooltipNode->length > 0) {
                    $caption = trim($tooltipNode->item(0)->textContent);
                }
            }

            $meta[$dataPath] = [
                'type' => 'radio',
                'options' => $options,
                'caption' => $caption,
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
     * Extract conditional visibility rules from XFA template event scripts.
     *
     * Parses <event activity="change"> scripts that toggle .presence to
     * build a map of trigger fields → value-based visibility rules.
     *
     * @return array<string, array{targets: string[], visibleWhen: array<string, string[]>}>
     *   Keyed by trigger data field name (e.g. 'requireSecondAppraiser').
     *   Each entry has 'targets' (all toggleable data keys) and 'visibleWhen'
     *   mapping each trigger value to the list of data keys that should be visible.
     */
    public function getConditionalRules(string $templateXml): array
    {
        $dom = new DOMDocument();
        @$dom->loadXML($templateXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('t', 'http://www.xfa.org/schema/xfa-template/3.0/');

        $rules = [];
        $scripts = $xpath->query('//t:event[@activity="change"]/t:script');

        foreach ($scripts as $script) {
            $code = $script->textContent;
            if (strpos($code, '.presence') === false) {
                continue;
            }
            // Skip help text toggles (only helpText.presence, nothing else)
            if (strpos($code, 'helpText.presence') !== false) {
                $otherPresence = preg_replace('/helpText\.presence/', '', $code);
                if (strpos($otherPresence, '.presence') === false) {
                    continue;
                }
            }

            $event = $script->parentNode;
            $triggerElement = $event->parentNode;

            // Get trigger field's data binding
            $bindNode = $xpath->query('t:bind[@ref]', $triggerElement);
            if ($bindNode->length === 0) {
                continue;
            }
            $bindRef = $bindNode->item(0)->getAttribute('ref');
            if (strpos($bindRef, '$.') !== 0) {
                continue;
            }
            $triggerPath = substr($bindRef, 2);
            $triggerParts = explode('.', $triggerPath);
            $triggerField = end($triggerParts);
            $triggerSection = count($triggerParts) > 1 ? $triggerParts[0] : '';

            // Find the trigger's ancestor section subform in the DOM
            $sectionNode = $this->findAncestorSection($triggerElement);

            // Parse the script to extract value → {targetName: visibility} mappings
            $valueMap = $this->parseVisibilityScript($code);
            if (empty($valueMap)) {
                continue;
            }

            // Resolve target subform names to data keys
            $allTargetKeys = [];
            $visibleWhen = [];

            foreach ($valueMap as $value => $targetVisMap) {
                $visibleKeys = [];
                foreach ($targetVisMap as $targetName => $visibility) {
                    $dataKeys = $this->resolveTargetToDataKeys(
                        $xpath,
                        $targetName,
                        $triggerSection,
                        $sectionNode
                    );
                    foreach ($dataKeys as $dk) {
                        if (!in_array($dk, $allTargetKeys)) {
                            $allTargetKeys[] = $dk;
                        }
                        if ($visibility === 'visible' && !in_array($dk, $visibleKeys)) {
                            $visibleKeys[] = $dk;
                        }
                    }
                }
                $visibleWhen[$value] = $visibleKeys;
            }

            // Filter out cross-section references (e.g. Section_20_AppraisalOutputs)
            $allTargetKeys = array_values(array_filter($allTargetKeys, function (string $key) {
                return !preg_match('/^Section_\d+/', $key);
            }));
            foreach ($visibleWhen as $val => $keys) {
                $visibleWhen[$val] = array_values(array_filter($keys, function (string $key) {
                    return !preg_match('/^Section_\d+/', $key);
                }));
            }

            if (empty($allTargetKeys)) {
                continue;
            }

            $rules[$triggerField] = [
                'targets' => $allTargetKeys,
                'visibleWhen' => $visibleWhen,
            ];
        }

        return $rules;
    }

    /**
     * Find the ancestor section-level subform for a given element.
     *
     * Walks up the DOM tree looking for a subform whose name starts with "section_".
     *
     * @return \DOMNode|null
     */
    private function findAncestorSection(\DOMNode $node): ?\DOMNode
    {
        $current = $node->parentNode;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            if ($current->localName === 'subform') {
                $name = $current->getAttribute('name');
                if ($name && preg_match('/^section_\d+/i', $name)) {
                    return $current;
                }
            }
            $current = $current->parentNode;
        }

        return null;
    }

    /**
     * Parse a JavaScript event script to extract value → target visibility mappings.
     *
     * @return array<string, array<string, string>> value => [targetName => 'visible'|'hidden']
     */
    private function parseVisibilityScript(string $code): array
    {
        $valueMap = [];

        // Remove block comments
        $code = preg_replace('#/\*.*?\*/#s', '', $code);
        // Remove line comments
        $code = preg_replace('#//[^\n]*#', '', $code);

        // Pattern 1: switch(this.rawValue) { case "value": target.presence = "vis"; ... }
        if (preg_match('/switch\s*\(\s*this\.rawValue\s*\)/s', $code)) {
            preg_match_all('/case\s+["\']([^"\']+)["\']\s*:(.*?)(?=case\s|default\s*:|}\s*$)/s', $code, $cases);
            for ($i = 0; $i < count($cases[1]); $i++) {
                $valueMap[$cases[1][$i]] = $this->extractPresenceAssignments($cases[2][$i]);
            }
            if (preg_match('/default\s*:(.*?)(?:}\s*$|$)/s', $code, $dm)) {
                $valueMap['_default'] = $this->extractPresenceAssignments($dm[1]);
            }
        }

        // Pattern 2: if(this.rawValue == "value") { ... } else if(...) { ... } else { ... }
        if (empty($valueMap) && preg_match('/this\.rawValue\s*==/', $code)) {
            preg_match_all(
                '/(?:if|else\s+if)\s*\(\s*this\.rawValue\s*==\s*["\']([^"\']+)["\']\s*\)\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}/s',
                $code,
                $blocks
            );
            for ($i = 0; $i < count($blocks[1]); $i++) {
                $valueMap[$blocks[1][$i]] = $this->extractPresenceAssignments($blocks[2][$i]);
            }
            if (preg_match('/\}\s*else\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)}\s*$/s', $code, $em)) {
                $valueMap['_default'] = $this->extractPresenceAssignments($em[1]);
            }
        }

        return $valueMap;
    }

    /**
     * Extract target.presence = "visible"|"hidden" assignments from a code block.
     *
     * @return array<string, string> targetName => 'visible'|'hidden'
     */
    private function extractPresenceAssignments(string $block): array
    {
        $assignments = [];
        preg_match_all(
            '/([\w.]+)\.presence\s*=\s*["\'](\w+)["\']/',
            $block,
            $matches
        );
        for ($i = 0; $i < count($matches[1]); $i++) {
            $targetPath = $matches[1][$i];
            $visibility = $matches[2][$i];
            $parts = explode('.', $targetPath);
            $targetName = end($parts);
            if (in_array($visibility, ['visible', 'hidden'])) {
                $assignments[$targetName] = $visibility;
            }
        }

        return $assignments;
    }

    /**
     * Resolve a template subform name to data field keys.
     *
     * Searches within the trigger's section context to find the correct target subform,
     * then extracts the section-relative data keys from bound fields.
     *
     * @param \DOMNode|null $sectionNode The trigger's ancestor section subform
     * @return string[] Data field keys (as they appear in getFields() output)
     */
    private function resolveTargetToDataKeys(
        DOMXPath $xpath,
        string $targetName,
        string $triggerSection,
        ?\DOMNode $sectionNode
    ): array {
        // Search for target subform within the section DOM subtree first
        $targetSubform = null;
        if ($sectionNode) {
            $found = $xpath->query('.//t:subform[@name="' . $targetName . '"]', $sectionNode);
            if ($found->length > 0) {
                $targetSubform = $found->item(0);
            }
        }

        // Fall back to global search
        if (!$targetSubform) {
            $found = $xpath->query('//t:subform[@name="' . $targetName . '"]');
            if ($found->length > 0) {
                $targetSubform = $found->item(0);
            }
        }

        if (!$targetSubform) {
            return [];
        }

        // Get all data-bound fields/exclGroups within the target subform
        $boundElements = $xpath->query(
            './/t:field[t:bind[@ref]]|.//t:exclGroup[t:bind[@ref]]',
            $targetSubform
        );

        $keys = [];
        foreach ($boundElements as $element) {
            $bind = $xpath->query('t:bind', $element)->item(0);
            $ref = $bind ? $bind->getAttribute('ref') : '';
            if (!$ref || strpos($ref, '$.') !== 0) {
                continue;
            }
            $dataPath = substr($ref, 2);
            $parts = explode('.', $dataPath);

            if (count($parts) >= 2 && $parts[0] === $triggerSection) {
                // Absolute binding: $.SectionName.fieldKey → take first key after section
                $key = $parts[1];
            } else {
                // Relative binding (inside repeatable) or no section prefix
                // Try to find the data context from ancestor subform bindings
                $key = $this->resolveRelativeDataKey($xpath, $element, $triggerSection);
                if (!$key) {
                    $key = $parts[0];
                }
            }

            if ($key && !in_array($key, $keys)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Resolve a relative data binding to its top-level section data key.
     *
     * Walks up from the element through ancestor subforms to find one with
     * a data binding that establishes the data context.
     */
    private function resolveRelativeDataKey(
        DOMXPath $xpath,
        \DOMNode $element,
        string $sectionName
    ): ?string {
        $current = $element->parentNode;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            if ($current->localName === 'subform') {
                $bindNode = $xpath->query('t:bind[@ref]', $current);
                if ($bindNode->length > 0) {
                    $ref = $bindNode->item(0)->getAttribute('ref');
                    if ($ref && strpos($ref, '$.') === 0) {
                        $path = substr($ref, 2);
                        $path = preg_replace('/\[\*\]/', '', $path);
                        $parts = explode('.', $path);
                        // Find the first key after the section prefix
                        if (count($parts) >= 2 && $parts[0] === $sectionName) {
                            return $parts[1];
                        }
                        // If the binding has a section-like path, take the relevant part
                        foreach ($parts as $part) {
                            if ($part !== $sectionName && !preg_match('/^Section_\d+/', $part)) {
                                return $part;
                            }
                        }
                    }
                }
            }
            $current = $current->parentNode;
        }

        return null;
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
