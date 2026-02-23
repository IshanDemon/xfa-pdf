<?php

declare(strict_types=1);

namespace Xfa\Pdf\Commands;

use Illuminate\Console\Command;
use Xfa\Pdf\Services\DatasetService;
use Xfa\Pdf\Services\NamespaceService;
use Xfa\Pdf\Services\PdfBinaryService;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\Services\TemplateService;
use Xfa\Pdf\XfaPdfManager;

class XfaPdfCommand extends Command
{
    protected $signature = 'xfa-pdf:manage
        {filename : Path to the XFA PDF file}
        {--read : Read and display all XFA datasets}
        {--sections : List all section names}
        {--section= : Display fields for a specific section}
        {--get= : Get a field value (e.g. "Section_3_AppraisalDetails/appraisalYear")}
        {--set= : Set a field value in format "path=value"}
        {--output= : Output file path (default: overwrites original)}
        {--raw : Output raw XML when using --read}
        {--preview : Generate an HTML preview and open in browser}';

    protected $description = 'Read, write, and preview XFA PDF form data';

    private XfaPdfManager $manager;

    public function __construct(XfaPdfManager $manager)
    {
        parent::__construct();
        $this->manager = $manager;
    }

    public function handle(): int
    {
        $filename = $this->argument('filename');

        if (!file_exists($filename)) {
            $this->error("File not found: {$filename}");

            return 1;
        }

        try {
            $xfaPdf = $this->manager->load($filename);
        } catch (\Exception $e) {
            $this->error("Failed to load PDF: " . $e->getMessage());

            return 1;
        }

        if ($this->option('preview')) {
            return $this->handlePreview($xfaPdf);
        }

        if ($this->option('raw') && $this->option('read')) {
            return $this->handleReadRaw($xfaPdf);
        }

        if ($this->option('read')) {
            return $this->handleRead($xfaPdf);
        }

        if ($this->option('sections')) {
            return $this->handleSections($xfaPdf);
        }

        if ($this->option('section')) {
            return $this->handleSection($xfaPdf, $this->option('section'));
        }

        if ($this->option('get')) {
            return $this->handleGet($xfaPdf, $this->option('get'));
        }

        if ($this->option('set')) {
            return $this->handleSet($xfaPdf, $this->option('set'));
        }

        $this->info('Usage examples:');
        $this->line('  php artisan xfa-pdf:manage file.pdf --read');
        $this->line('  php artisan xfa-pdf:manage file.pdf --read --raw');
        $this->line('  php artisan xfa-pdf:manage file.pdf --sections');
        $this->line('  php artisan xfa-pdf:manage file.pdf --section=Section_3_AppraisalDetails');
        $this->line('  php artisan xfa-pdf:manage file.pdf --get="Section_3_AppraisalDetails/appraisalYear"');
        $this->line('  php artisan xfa-pdf:manage file.pdf --set="Section_3_AppraisalDetails/appraisalYear=2024"');
        $this->line('  php artisan xfa-pdf:manage file.pdf --preview');

        return 0;
    }

    private function handlePreview($xfaPdf): int
    {
        try {
            $html = $this->manager->generatePreview($xfaPdf);

            $outputPath = $this->option('output') ?: storage_path('xfa-pdf/preview.html');
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($outputPath, $html);
            $this->info("Preview generated: {$outputPath}");

            if (PHP_OS_FAMILY === 'Darwin') {
                exec('open ' . escapeshellarg($outputPath));
            } elseif (PHP_OS_FAMILY === 'Linux') {
                exec('xdg-open ' . escapeshellarg($outputPath));
            }

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleReadRaw($xfaPdf): int
    {
        try {
            $xml = $this->manager->getRawXml($xfaPdf);
            $this->line($xml);

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleRead($xfaPdf): int
    {
        try {
            $sections = $this->manager->getSections($xfaPdf);

            foreach ($sections as $sectionName) {
                $this->info("=== {$sectionName} ===");
                $fields = $this->manager->getFields($xfaPdf, $sectionName);
                $this->printFields($fields, 1);
                $this->line('');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleSections($xfaPdf): int
    {
        try {
            $sections = $this->manager->getSections($xfaPdf);
            $this->info('Sections in XFA PDF:');

            foreach ($sections as $index => $name) {
                $this->line("  [{$index}] {$name}");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleSection($xfaPdf, string $sectionName): int
    {
        try {
            $fields = $this->manager->getFields($xfaPdf, $sectionName);

            if (empty($fields)) {
                $this->warn("Section '{$sectionName}' is empty or not found.");

                return 0;
            }

            $this->info("=== {$sectionName} ===");
            $this->printFields($fields, 1);

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleGet($xfaPdf, string $fieldPath): int
    {
        try {
            $value = $this->manager->getFieldValue($xfaPdf, $fieldPath);

            if ($value === null) {
                $this->warn("Field not found: {$fieldPath}");

                return 1;
            }

            $this->info("{$fieldPath} = {$value}");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleSet($xfaPdf, string $setExpression): int
    {
        $eqPos = strpos($setExpression, '=');
        if ($eqPos === false) {
            $this->error('Invalid --set format. Use: --set="field/path=value"');

            return 1;
        }

        $fieldPath = substr($setExpression, 0, $eqPos);
        $value = substr($setExpression, $eqPos + 1);
        $outputPath = $this->option('output');

        try {
            $oldValue = $this->manager->getFieldValue($xfaPdf, $fieldPath);
            $this->line("Current value: {$oldValue}");
            $this->line("New value: {$value}");

            $this->manager->setFieldValue($xfaPdf, $fieldPath, $value);
            $this->manager->save($xfaPdf, $outputPath);

            $target = $outputPath ?? $this->argument('filename');
            $this->info("Successfully updated '{$fieldPath}' in {$target}");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    /**
     * @param mixed $data
     */
    private function printFields($data, int $indent = 0): void
    {
        $prefix = str_repeat('  ', $indent);

        if (is_string($data)) {
            $this->line("{$prefix}{$data}");

            return;
        }

        if (!is_array($data)) {
            $this->line("{$prefix}" . print_r($data, true));

            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $display = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
                $this->line("{$prefix}<comment>{$key}</comment>: {$display}");
            } elseif (is_array($value)) {
                $this->line("{$prefix}<comment>{$key}</comment>:");
                $this->printFields($value, $indent + 1);
            } else {
                $this->line("{$prefix}<comment>{$key}</comment>: " . print_r($value, true));
            }
        }
    }
}
