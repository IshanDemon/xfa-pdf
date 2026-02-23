<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Unit\Services;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Xfa\Pdf\Exceptions\FieldNotFoundException;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\RepeatableService;

class RepeatableServiceTest extends TestCase
{
    private RepeatableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RepeatableService(new NamespaceService());
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
      <Section_7_CPD>
        <cpdList>
          <cpd>
            <role>GP</role>
            <credits>5</credits>
          </cpd>
          <cpd>
            <role>Hospital</role>
            <credits>3</credits>
          </cpd>
        </cpdList>
      </Section_7_CPD>
    </Root>
  </xfa:data>
</xfa:datasets>
XML;
    }

    /** @test */
    public function it_gets_items_from_container()
    {
        $dom = $this->createDom($this->getSampleXml());

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'cpdList');

        $this->assertCount(2, $items);
        $this->assertSame('GP', $items[0]['role']);
        $this->assertSame('5', $items[0]['credits']);
        $this->assertSame('Hospital', $items[1]['role']);
    }

    /** @test */
    public function it_adds_an_item()
    {
        $dom = $this->createDom($this->getSampleXml());

        $dom = $this->service->addItem($dom, 'Section_7_CPD', 'cpdList', [
            'role' => 'Research',
            'credits' => '10',
        ]);

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'cpdList');
        $this->assertCount(3, $items);
        $this->assertSame('Research', $items[2]['role']);
    }

    /** @test */
    public function it_removes_an_item()
    {
        $dom = $this->createDom($this->getSampleXml());

        $dom = $this->service->removeItem($dom, 'Section_7_CPD', 'cpdList', 0);

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'cpdList');
        $this->assertCount(1, $items);
        $this->assertSame('Hospital', $items[0]['role']);
    }

    /** @test */
    public function it_throws_when_removing_invalid_index()
    {
        $dom = $this->createDom($this->getSampleXml());

        $this->expectException(FieldNotFoundException::class);

        $this->service->removeItem($dom, 'Section_7_CPD', 'cpdList', 99);
    }

    /** @test */
    public function it_updates_an_item()
    {
        $dom = $this->createDom($this->getSampleXml());

        $dom = $this->service->updateItem($dom, 'Section_7_CPD', 'cpdList', 0, [
            'role' => 'Updated Role',
            'credits' => '15',
        ]);

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'cpdList');
        $this->assertSame('Updated Role', $items[0]['role']);
        $this->assertSame('15', $items[0]['credits']);
    }

    /** @test */
    public function it_replaces_all_items()
    {
        $dom = $this->createDom($this->getSampleXml());

        $newItems = [
            ['role' => 'A', 'credits' => '1'],
            ['role' => 'B', 'credits' => '2'],
            ['role' => 'C', 'credits' => '3'],
        ];

        $dom = $this->service->setItems($dom, 'Section_7_CPD', 'cpdList', 'cpd', $newItems);

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'cpdList');
        $this->assertCount(3, $items);
        $this->assertSame('A', $items[0]['role']);
        $this->assertSame('C', $items[2]['role']);
    }

    /** @test */
    public function it_returns_empty_for_missing_container()
    {
        $dom = $this->createDom($this->getSampleXml());

        $items = $this->service->getItems($dom, 'Section_7_CPD', 'nonExistent');

        $this->assertEmpty($items);
    }

    /** @test */
    public function it_throws_when_adding_to_missing_container()
    {
        $dom = $this->createDom($this->getSampleXml());

        $this->expectException(FieldNotFoundException::class);

        $this->service->addItem($dom, 'Section_7_CPD', 'nonExistent', ['role' => 'Test']);
    }
}
