<?php

declare(strict_types=1);

namespace Xfa\Pdf\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\PdfBinaryService;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\Services\RepeatableService;
use Xfa\Pdf\Services\TemplateService;
use Xfa\Pdf\XfaPdfManager;

class PdfEditDownloadTest extends TestCase
{
    private string $pdfPath;
    private XfaPdfManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $candidates = [
            __DIR__ . '/../../public/mag42.pdf',
            '/Users/apple/Projects/xfa-package/public/mag42.pdf',
        ];

        $this->pdfPath = '';
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $this->pdfPath = realpath($path);
                break;
            }
        }

        if (!$this->pdfPath) {
            $this->markTestSkipped('mag42.pdf not found.');
        }

        $ns = new NamespaceService();
        $this->manager = new XfaPdfManager(
            new PdfBinaryService(),
            new DatasetService($ns),
            new TemplateService($ns),
            new RepeatableService($ns),
            new PreviewService(),
            $ns
        );
    }

    /** @test */
    public function it_loads_and_reads_fields_from_real_pdf()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $sections = $this->manager->getSections($xfaPdf);
        $this->assertNotEmpty($sections);
        $this->assertContains('Section_3_AppraisalDetails', $sections);
    }

    /** @test */
    public function it_edits_a_field_and_verifies_in_memory()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $uniqueValue = 'TestEdit_' . uniqid();

        $this->manager->setFieldValues($xfaPdf, [
            'Section_3_AppraisalDetails/appraisalYear' => $uniqueValue,
        ]);

        $fields = $this->manager->getFields($xfaPdf, 'Section_3_AppraisalDetails');
        $this->assertSame($uniqueValue, $fields['appraisalYear']);
    }

    /** @test */
    public function it_writes_edited_pdf_to_new_file_and_changes_persist()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $uniqueValue = 'Downloaded_' . uniqid();

        $this->manager->setFieldValues($xfaPdf, [
            'Section_3_AppraisalDetails/appraisalYear' => $uniqueValue,
        ]);

        $tempPath = sys_get_temp_dir() . '/xfa_test_download_' . uniqid() . '.pdf';
        $result = $this->manager->saveXfaPdf($xfaPdf, $tempPath);

        $this->assertTrue($result);
        $this->assertFileExists($tempPath);
        $this->assertGreaterThan(
            filesize($this->pdfPath),
            filesize($tempPath),
            'Output PDF should be larger than original due to incremental update'
        );

        // Reload the written PDF and verify the edited value persists
        $reloaded = $this->manager->load($tempPath);
        $fields = $this->manager->getFields($reloaded, 'Section_3_AppraisalDetails');

        $this->assertSame(
            $uniqueValue,
            $fields['appraisalYear'],
            'Edited field value should persist in the downloaded PDF'
        );

        unlink($tempPath);
    }

    /** @test */
    public function it_writes_multiple_field_edits_to_new_file()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $nameValue = 'Dr Test ' . uniqid();
        $yearValue = '2099';

        $this->manager->setFieldValues($xfaPdf, [
            'Section_3_AppraisalDetails/appraisalYear' => $yearValue,
            'Section_3_AppraisalDetails/appraiseeName' => $nameValue,
        ]);

        $tempPath = sys_get_temp_dir() . '/xfa_test_multi_' . uniqid() . '.pdf';
        $this->manager->saveXfaPdf($xfaPdf, $tempPath);

        $reloaded = $this->manager->load($tempPath);
        $fields = $this->manager->getFields($reloaded, 'Section_3_AppraisalDetails');

        $this->assertSame($yearValue, $fields['appraisalYear']);
        $this->assertSame($nameValue, $fields['appraiseeName']);

        unlink($tempPath);
    }

    /** @test */
    public function original_pdf_is_not_modified_when_writing_to_different_path()
    {
        $originalHash = md5_file($this->pdfPath);

        $xfaPdf = $this->manager->load($this->pdfPath);

        $this->manager->setFieldValues($xfaPdf, [
            'Section_3_AppraisalDetails/appraisalYear' => 'MODIFIED_' . uniqid(),
        ]);

        $tempPath = sys_get_temp_dir() . '/xfa_test_nomodify_' . uniqid() . '.pdf';
        $this->manager->saveXfaPdf($xfaPdf, $tempPath);

        $this->assertSame($originalHash, md5_file($this->pdfPath), 'Original PDF must not be modified');

        unlink($tempPath);
    }

    /** @test */
    public function it_adds_repeatable_item_and_persists_in_download()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $ns = new NamespaceService();
        $repeatable = new RepeatableService($ns);
        $itemsBefore = $repeatable->getItems($xfaPdf->getDatasetsDom(), 'Section_7_CPD', 'cpdList');
        $countBefore = count($itemsBefore);

        $newItemData = ['role' => 'IntegrationTest', 'credits' => '42'];
        $dom = $repeatable->addItem($xfaPdf->getDatasetsDom(), 'Section_7_CPD', 'cpdList', $newItemData);
        $xfaPdf->setDatasetsDom($dom);

        // Verify in memory
        $itemsAfter = $repeatable->getItems($xfaPdf->getDatasetsDom(), 'Section_7_CPD', 'cpdList');
        $this->assertCount($countBefore + 1, $itemsAfter);

        // Write to file and reload
        $tempPath = sys_get_temp_dir() . '/xfa_test_repeatable_' . uniqid() . '.pdf';
        $this->manager->saveXfaPdf($xfaPdf, $tempPath);

        $reloaded = $this->manager->load($tempPath);
        $reloadedItems = $repeatable->getItems($reloaded->getDatasetsDom(), 'Section_7_CPD', 'cpdList');

        $this->assertCount($countBefore + 1, $reloadedItems, 'Added repeatable item should persist in downloaded PDF');
        $lastItem = end($reloadedItems);
        $this->assertSame('IntegrationTest', $lastItem['role']);
        $this->assertSame('42', $lastItem['credits']);

        unlink($tempPath);
    }

    /** @test */
    public function downloaded_pdf_contains_edited_text_in_datasets_xml()
    {
        $xfaPdf = $this->manager->load($this->pdfPath);

        $uniqueMarker = 'XFATEST_' . uniqid();

        $this->manager->setFieldValues($xfaPdf, [
            'Section_3_AppraisalDetails/appraisalYear' => $uniqueMarker,
        ]);

        $tempPath = sys_get_temp_dir() . '/xfa_test_binary_' . uniqid() . '.pdf';
        $this->manager->saveXfaPdf($xfaPdf, $tempPath);

        $reloaded = $this->manager->load($tempPath);
        $xml = $reloaded->getDatasetsDom()->saveXML();

        $this->assertStringContainsString(
            $uniqueMarker,
            $xml,
            'Unique marker should be present in the datasets XML of the downloaded PDF'
        );

        unlink($tempPath);
    }
}
