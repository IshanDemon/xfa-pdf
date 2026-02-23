<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Unit\Services;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Xfa\Pdf\Services\NamespaceService;

class NamespaceServiceTest extends TestCase
{
    /** @test */
    public function it_detects_xfa_namespace_from_xml()
    {
        $xml = '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/"><xfa:data></xfa:data></xfa:datasets>';

        $service = new NamespaceService();
        $service->detect($xml);

        $this->assertSame('http://www.xfa.org/schema/xfa-data/1.0/', $service->getXfaNamespace());
        $this->assertArrayHasKey('xfa', $service->getAll());
    }

    /** @test */
    public function it_detects_custom_data_namespace()
    {
        $xml = '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">'
            . '<xfa:data><Root xmlns="http://www.aptosolutions.co.uk/NHS/AppraisalForm/1.0"></Root></xfa:data>'
            . '</xfa:datasets>';

        $service = new NamespaceService();
        $service->detect($xml);

        $this->assertSame(
            'http://www.aptosolutions.co.uk/NHS/AppraisalForm/1.0',
            $service->getDataNamespace()
        );
    }

    /** @test */
    public function it_registers_namespaces_on_xpath()
    {
        $xml = '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">'
            . '<xfa:data><Root xmlns="http://www.aptosolutions.co.uk/NHS/AppraisalForm/1.0">'
            . '<Section_3><name>Test</name></Section_3>'
            . '</Root></xfa:data></xfa:datasets>';

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $service = new NamespaceService();
        $service->detect($xml);

        $xpath = new DOMXPath($dom);
        $service->registerOnXPath($xpath);

        $nodes = $xpath->query('//xfa:data/*/*');
        $this->assertGreaterThan(0, $nodes->length);
    }

    /** @test */
    public function it_returns_template_namespace()
    {
        $service = new NamespaceService();

        $this->assertSame(
            'http://www.xfa.org/schema/xfa-template/3.0/',
            $service->getTemplateNamespace()
        );
    }

    /** @test */
    public function it_handles_xml_with_no_custom_namespace()
    {
        $xml = '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">'
            . '<xfa:data><root><field>value</field></root></xfa:data>'
            . '</xfa:datasets>';

        $service = new NamespaceService();
        $service->detect($xml);

        $this->assertNull($service->getDataNamespace());
    }

    /** @test */
    public function it_detects_multiple_namespaces()
    {
        $xml = '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">'
            . '<xfa:data>'
            . '<Root xmlns="http://example.com/form/1.0">'
            . '<CommonSections xmlns="http://example.com/common/2.0">'
            . '</CommonSections></Root></xfa:data></xfa:datasets>';

        $service = new NamespaceService();
        $service->detect($xml);

        $all = $service->getAll();
        $this->assertContains('http://example.com/form/1.0', $all);
        $this->assertContains('http://example.com/common/2.0', $all);
    }
}
