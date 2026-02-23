<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Unit\Services;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Xfa\Pdf\Exceptions\FieldNotFoundException;
use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;

class DatasetServiceTest extends TestCase
{
    private DatasetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatasetService(new NamespaceService());
    }

    private function createDom(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        return $dom;
    }

    private function getSampleXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:data>
    <Root xmlns="http://example.com/form/1.0">
      <Section_3_Details>
        <name>John Doe</name>
        <email>john@example.com</email>
        <year>2024</year>
      </Section_3_Details>
      <Section_4_Work>
        <role>Developer</role>
        <items>
          <item>
            <title>Task 1</title>
            <status>Done</status>
          </item>
          <item>
            <title>Task 2</title>
            <status>Pending</status>
          </item>
        </items>
      </Section_4_Work>
    </Root>
  </xfa:data>
</xfa:datasets>
XML;
    }

    /** @test */
    public function it_gets_section_names()
    {
        $dom = $this->createDom($this->getSampleXml());

        $sections = $this->service->getSections($dom);

        $this->assertContains('Section_3_Details', $sections);
        $this->assertContains('Section_4_Work', $sections);
    }

    /** @test */
    public function it_gets_fields_for_a_section()
    {
        $dom = $this->createDom($this->getSampleXml());

        $fields = $this->service->getFields($dom, 'Section_3_Details');

        $this->assertSame('John Doe', $fields['name']);
        $this->assertSame('john@example.com', $fields['email']);
        $this->assertSame('2024', $fields['year']);
    }

    /** @test */
    public function it_gets_field_value_by_path()
    {
        $dom = $this->createDom($this->getSampleXml());

        $value = $this->service->getFieldValue($dom, 'Section_3_Details/name');

        $this->assertSame('John Doe', $value);
    }

    /** @test */
    public function it_returns_null_for_missing_field()
    {
        $dom = $this->createDom($this->getSampleXml());

        $value = $this->service->getFieldValue($dom, 'Section_3_Details/nonexistent');

        $this->assertNull($value);
    }

    /** @test */
    public function it_sets_field_value()
    {
        $dom = $this->createDom($this->getSampleXml());

        $dom = $this->service->setFieldValue($dom, 'Section_3_Details/name', 'Jane Doe');

        $value = $this->service->getFieldValue($dom, 'Section_3_Details/name');
        $this->assertSame('Jane Doe', $value);
    }

    /** @test */
    public function it_throws_when_setting_nonexistent_field()
    {
        $dom = $this->createDom($this->getSampleXml());

        $this->expectException(FieldNotFoundException::class);

        $this->service->setFieldValue($dom, 'Section_3_Details/nonexistent', 'value');
    }

    /** @test */
    public function it_sets_multiple_field_values()
    {
        $dom = $this->createDom($this->getSampleXml());

        $dom = $this->service->setFieldValues($dom, [
            'Section_3_Details/name' => 'Jane Doe',
            'Section_3_Details/year' => '2025',
        ]);

        $this->assertSame('Jane Doe', $this->service->getFieldValue($dom, 'Section_3_Details/name'));
        $this->assertSame('2025', $this->service->getFieldValue($dom, 'Section_3_Details/year'));
    }

    /** @test */
    public function it_converts_dom_to_array()
    {
        $dom = $this->createDom($this->getSampleXml());

        $array = $this->service->toArray($dom);

        $this->assertArrayHasKey('Section_3_Details', $array);
        $this->assertSame('John Doe', $array['Section_3_Details']['name']);
    }

    /** @test */
    public function node_to_array_handles_repeated_elements()
    {
        $dom = $this->createDom($this->getSampleXml());

        $array = $this->service->toArray($dom);
        $items = $array['Section_4_Work']['items']['item'];

        $this->assertCount(2, $items);
        $this->assertSame('Task 1', $items[0]['title']);
        $this->assertSame('Task 2', $items[1]['title']);
    }
}
