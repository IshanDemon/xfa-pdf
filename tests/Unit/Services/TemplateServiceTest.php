<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Xfa\Pdf\Services\TemplateService;

class TemplateServiceTest extends TestCase
{
    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    private function getSampleTemplate(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<template xmlns="http://www.xfa.org/schema/xfa-template/3.0/">
  <subform name="form1" layout="tb">
    <subform name="page1" layout="tb">
      <field name="firstName">
        <ui><textEdit/></ui>
        <bind match="dataRef" ref="$.Section_3.firstName"/>
        <caption><value><text>First Name</text></value></caption>
      </field>
      <field name="country">
        <ui><choiceList open="onEntry"/></ui>
        <bind match="dataRef" ref="$.Section_3.country"/>
        <items><text>United States</text><text>Canada</text><text>UK</text></items>
        <caption><value><text>Country</text></value></caption>
      </field>
      <field name="birthDate">
        <ui><dateTimeEdit/></ui>
        <bind match="dataRef" ref="$.Section_3.birthDate"/>
      </field>
      <field name="agreeTerms">
        <ui><checkButton shape="square"/></ui>
        <bind match="dataRef" ref="$.Section_3.agreeTerms"/>
        <caption><value><text>I agree to terms</text></value></caption>
      </field>
      <field name="quantity">
        <ui><numericEdit/></ui>
        <bind match="dataRef" ref="$.Section_3.quantity"/>
      </field>
      <field name="comments">
        <ui><textEdit multiLine="1"/></ui>
        <bind match="dataRef" ref="$.Section_3.comments"/>
      </field>
      <exclGroup name="gender">
        <bind match="dataRef" ref="$.Section_3.gender"/>
        <field name="male"><items><text>male</text></items></field>
        <field name="female"><items><text>female</text></items></field>
      </exclGroup>
    </subform>
    <subform name="cpdRow" layout="lr-tb">
      <occur min="0" max="-1" initial="1"/>
      <bind match="dataRef" ref="$.Section_7.cpdList.cpd[*]"/>
      <field name="role">
        <ui><textEdit/></ui>
        <bind match="dataRef" ref="$.role"/>
      </field>
      <field name="credits">
        <ui><numericEdit/></ui>
        <bind match="dataRef" ref="$.credits"/>
      </field>
    </subform>
  </subform>
</template>
XML;
    }

    /** @test */
    public function it_extracts_field_metadata()
    {
        $meta = $this->service->getFieldMetadata($this->getSampleTemplate());

        $this->assertArrayHasKey('Section_3.firstName', $meta);
        $this->assertSame('text', $meta['Section_3.firstName']['type']);
        $this->assertSame('First Name', $meta['Section_3.firstName']['caption']);

        $this->assertSame('select', $meta['Section_3.country']['type']);
        $this->assertContains('United States', $meta['Section_3.country']['options']);

        $this->assertSame('date', $meta['Section_3.birthDate']['type']);
        $this->assertSame('checkbox', $meta['Section_3.agreeTerms']['type']);
        $this->assertSame('number', $meta['Section_3.quantity']['type']);
        $this->assertSame('textarea', $meta['Section_3.comments']['type']);
    }

    /** @test */
    public function it_extracts_radio_groups()
    {
        $meta = $this->service->getFieldMetadata($this->getSampleTemplate());

        $this->assertArrayHasKey('Section_3.gender', $meta);
        $this->assertSame('radio', $meta['Section_3.gender']['type']);
        $this->assertContains('male', $meta['Section_3.gender']['options']);
        $this->assertContains('female', $meta['Section_3.gender']['options']);
    }

    /** @test */
    public function it_extracts_repeatable_subforms()
    {
        $repeatables = $this->service->getRepeatableSubforms($this->getSampleTemplate());

        $this->assertArrayHasKey('cpd', $repeatables);
        $this->assertSame(0, $repeatables['cpd']['min']);
        $this->assertSame(-1, $repeatables['cpd']['max']);
        $this->assertNotEmpty($repeatables['cpd']['fields']);
    }

    /** @test */
    public function it_gets_field_type_by_name()
    {
        $type = $this->service->getFieldType($this->getSampleTemplate(), 'firstName');

        $this->assertSame('text', $type);
    }

    /** @test */
    public function it_returns_null_for_unknown_field_type()
    {
        $type = $this->service->getFieldType($this->getSampleTemplate(), 'nonExistent');

        $this->assertNull($type);
    }
}
