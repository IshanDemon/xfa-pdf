<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use DOMXPath;

class NamespaceService
{
    /** @var array<string, string> */
    private array $namespaces = [];

    private const XFA_DATA_NS = 'http://www.xfa.org/schema/xfa-data/1.0/';
    private const XFA_TEMPLATE_NS = 'http://www.xfa.org/schema/xfa-template/3.0/';

    /**
     * Auto-detect all XML namespaces from an XML string.
     */
    public function detect(string $xml): self
    {
        $this->namespaces = [];

        // Match all xmlns declarations: xmlns:prefix="uri" and xmlns="uri"
        preg_match_all('/xmlns(?::([a-zA-Z0-9_-]+))?\s*=\s*"([^"]+)"/', $xml, $matches, PREG_SET_ORDER);

        $defaultCounter = 0;
        foreach ($matches as $match) {
            $prefix = $match[1] ?: '_default';
            $uri = $match[2];

            // Skip duplicate URIs already registered
            if (in_array($uri, $this->namespaces, true)) {
                continue;
            }

            // Handle multiple default namespaces by auto-generating prefixes
            if ($prefix === '_default' && isset($this->namespaces['_default'])) {
                $defaultCounter++;
                $prefix = 'ns' . $defaultCounter;
            }

            if (!isset($this->namespaces[$prefix])) {
                $this->namespaces[$prefix] = $uri;
            }
        }

        // Ensure standard XFA namespaces are registered
        $this->ensureXfaNamespace();

        return $this;
    }

    /**
     * Register all detected namespaces on a DOMXPath instance.
     */
    public function registerOnXPath(DOMXPath $xpath): void
    {
        foreach ($this->namespaces as $prefix => $uri) {
            if ($prefix === '_default') {
                continue;
            }
            $xpath->registerNamespace($prefix, $uri);
        }

        // Always register 'xfa' for dataset queries
        if (!isset($this->namespaces['xfa'])) {
            $xpath->registerNamespace('xfa', self::XFA_DATA_NS);
        }
    }

    /**
     * Get the data root namespace (the namespace of the Root element inside xfa:data).
     * This varies per form (e.g., NHS/AppraisalForm for MAG forms).
     */
    public function getDataNamespace(): ?string
    {
        // Look for the first non-XFA, non-standard namespace
        foreach ($this->namespaces as $prefix => $uri) {
            if ($uri === self::XFA_DATA_NS) {
                continue;
            }
            if (strpos($uri, 'http://www.xfa.org/') === 0) {
                continue;
            }
            if (strpos($uri, 'http://ns.adobe.com/') === 0) {
                continue;
            }
            if ($uri === 'http://www.w3.org/1999/xhtml') {
                continue;
            }

            return $uri;
        }

        return null;
    }

    /**
     * Get the prefix used for the data root namespace.
     */
    public function getDataPrefix(): string
    {
        $dataUri = $this->getDataNamespace();

        if (!$dataUri) {
            return 'ns';
        }

        foreach ($this->namespaces as $prefix => $uri) {
            if ($uri === $dataUri && $prefix !== '_default') {
                return $prefix;
            }
        }

        // If it was the default namespace, register it as 'ns'
        if (isset($this->namespaces['_default']) && $this->namespaces['_default'] === $dataUri) {
            $this->namespaces['ns'] = $dataUri;

            return 'ns';
        }

        return 'ns';
    }

    public function getXfaNamespace(): string
    {
        return self::XFA_DATA_NS;
    }

    public function getTemplateNamespace(): string
    {
        return self::XFA_TEMPLATE_NS;
    }

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return $this->namespaces;
    }

    /**
     * Ensure xfa namespace is properly registered, detecting prefix if needed.
     */
    private function ensureXfaNamespace(): void
    {
        $hasXfa = false;
        foreach ($this->namespaces as $prefix => $uri) {
            if ($uri === self::XFA_DATA_NS) {
                $hasXfa = true;
                // If the XFA namespace was detected under a non-standard prefix, also register it as 'xfa'
                if ($prefix !== 'xfa' && !isset($this->namespaces['xfa'])) {
                    $this->namespaces['xfa'] = $uri;
                }
                break;
            }
        }

        if (!$hasXfa) {
            $this->namespaces['xfa'] = self::XFA_DATA_NS;
        }
    }
}
