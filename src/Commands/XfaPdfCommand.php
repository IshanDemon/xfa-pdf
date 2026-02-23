<?php

declare(strict_types=1);

namespace Xfa\Pdf\Commands;

use Illuminate\Console\Command;
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

    private XfaPdfManager $xfa;

    public function __construct(XfaPdfManager $xfa)
    {
        parent::__construct();
        $this->xfa = $xfa;
    }

    public function handle(): int
    {
        $filename = $this->argument('filename');

        if (!file_exists($filename)) {
            $this->error("File not found: {$filename}");

            return 1;
        }

        try {
            $this->xfa->setFile($filename);
        } catch (\Exception $e) {
            $this->error("Failed to load PDF: " . $e->getMessage());

            return 1;
        }

        if ($this->option('preview')) {
            return $this->handlePreview();
        }

        if ($this->option('raw') && $this->option('read')) {
            return $this->handleReadRaw();
        }

        if ($this->option('read')) {
            return $this->handleRead();
        }

        if ($this->option('sections')) {
            return $this->handleSections();
        }

        if ($this->option('section')) {
            return $this->handleSection($this->option('section'));
        }

        if ($this->option('get')) {
            return $this->handleGet($this->option('get'));
        }

        if ($this->option('set')) {
            return $this->handleSet($this->option('set'));
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

    private function handlePreview(): int
    {
        try {
            $html = $this->xfa->preview();

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

    private function handleReadRaw(): int
    {
        try {
            $xml = $this->xfa->rawXml();
            $this->line($xml);

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleRead(): int
    {
        try {
            $sections = $this->xfa->sections();

            foreach ($sections as $sectionName) {
                $this->info("=== {$sectionName} ===");
                $fields = $this->xfa->read($sectionName);
                $this->printFields($fields, 1);
                $this->line('');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    private function handleSections(): int
    {
        try {
            $sections = $this->xfa->sections();
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

    private function handleSection(string $sectionName): int
    {
        try {
            $fields = $this->xfa->read($sectionName);

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

    private function handleGet(string $fieldPath): int
    {
        try {
            // Split "Section/field" into section + field
            $parts = explode('/', $fieldPath, 2);

            if (count($parts) === 2) {
                $value = $this->xfa->readField($parts[0], $parts[1]);
            } else {
                // Single part — try all sections
                $value = null;
                foreach ($this->xfa->sections() as $section) {
                    $value = $this->xfa->readField($section, $fieldPath);
                    if ($value !== null) {
                        break;
                    }
                }
            }

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

    private function handleSet(string $setExpression): int
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
            // Split "Section/field" into section + field
            $parts = explode('/', $fieldPath, 2);

            if (count($parts) !== 2) {
                $this->error('Field path must be "SectionName/fieldName"');

                return 1;
            }

            $oldValue = $this->xfa->readField($parts[0], $parts[1]);
            $this->line("Current value: {$oldValue}");
            $this->line("New value: {$value}");

            $this->xfa->updateField($parts[0], $parts[1], $value);
            $this->xfa->save($outputPath);

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
