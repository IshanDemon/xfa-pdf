<?php

declare(strict_types=1);

namespace Xfa\Pdf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Xfa\Pdf\Models\XfaDocument;
use Xfa\Pdf\Services\PreviewService;
use Xfa\Pdf\XfaPdfManager;

class XfaPdfController extends Controller
{
    private XfaPdfManager $xfa;

    public function __construct(XfaPdfManager $xfa)
    {
        $this->xfa = $xfa;
    }

    /**
     * List all stored XFA documents.
     */
    public function index()
    {
        $documents = XfaDocument::orderBy('created_at', 'desc')->get();

        return view('xfa-pdf::index', compact('documents'));
    }

    /**
     * Show upload form.
     */
    public function create()
    {
        return view('xfa-pdf::upload');
    }

    /**
     * Handle PDF upload.
     */
    public function store(Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:' . (config('xfa-pdf.max_upload_size', 20) * 1024),
            'name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('pdf_file');
        $originalName = $file->getClientOriginalName();
        $name = $request->input('name', pathinfo($originalName, PATHINFO_FILENAME));

        $disk = config('xfa-pdf.disk', 'local');
        $uploadPath = config('xfa-pdf.upload_path', 'xfa-pdf');
        $storedPath = $file->store($uploadPath, $disk);

        $fullPath = Storage::disk($disk)->path($storedPath);

        try {
            $this->xfa->setFile($fullPath);
            $sections = $this->xfa->sections();

            $metadata = [
                'sections' => $sections,
                'section_count' => count($sections),
            ];
        } catch (\Exception $e) {
            Storage::disk($disk)->delete($storedPath);

            return redirect()->route('xfa-pdf.create')
                ->withErrors(['pdf_file' => 'Failed to parse XFA data: ' . $e->getMessage()]);
        }

        $document = XfaDocument::create([
            'name' => $name,
            'original_filename' => $originalName,
            'file_path' => $storedPath,
            'metadata' => $metadata,
        ]);

        return redirect()->route('xfa-pdf.show', $document->id)
            ->with('success', 'Document uploaded successfully.');
    }

    /**
     * Preview document (read-only).
     */
    public function show($id)
    {
        $document = XfaDocument::findOrFail($id);
        $fullPath = $this->getFullPath($document);
        $this->xfa->setFile($fullPath);

        $sections = $this->xfa->sections();
        $allData = [];
        foreach ($sections as $name) {
            $allData[$name] = $this->xfa->read($name);
        }

        $fieldMeta = $this->xfa->fieldMetadata();
        $repeatables = $this->xfa->repeatableSubforms();
        $navSections = $this->xfa->navigationSections();

        $sectionLabels = $this->buildSectionLabels($sections, $navSections);

        return view('xfa-pdf::show', compact(
            'document',
            'sections',
            'allData',
            'fieldMeta',
            'repeatables',
            'sectionLabels',
        ));
    }

    /**
     * Edit document (interactive form).
     */
    public function edit($id)
    {
        $document = XfaDocument::findOrFail($id);
        $fullPath = $this->getFullPath($document);
        $this->xfa->setFile($fullPath);

        $sections = $this->xfa->sections();
        $allData = [];
        foreach ($sections as $name) {
            $allData[$name] = $this->xfa->read($name);
        }

        $fieldMeta = $this->xfa->fieldMetadata();
        $repeatables = $this->xfa->repeatableSubforms();
        $navSections = $this->xfa->navigationSections();

        $sectionLabels = $this->buildSectionLabels($sections, $navSections);

        return view('xfa-pdf::edit', compact(
            'document',
            'sections',
            'allData',
            'fieldMeta',
            'repeatables',
            'sectionLabels',
        ));
    }

    /**
     * Save edits back to PDF.
     */
    public function update(Request $request, $id)
    {
        $document = XfaDocument::findOrFail($id);
        $fullPath = $this->getFullPath($document);
        $xfaPdf = $this->xfa->load($fullPath);

        $fields = $request->input('fields', []);

        // Flatten nested field paths: "Section/fieldName" => "value"
        $flatFields = $this->flattenFields($fields);

        if (!empty($flatFields)) {
            $this->xfa->setFieldValues($xfaPdf, $flatFields);
        }

        // Handle repeatable items
        $repeatableData = $request->input('repeatables', []);
        foreach ($repeatableData as $sectionName => $containers) {
            foreach ($containers as $containerName => $containerData) {
                $elementName = $containerData['element'] ?? 'item';
                $items = $containerData['items'] ?? [];

                $this->xfa->setRepeatableItems(
                    $xfaPdf,
                    $sectionName,
                    $containerName,
                    $elementName,
                    $items,
                );
            }
        }

        $this->xfa->saveXfaPdf($xfaPdf);

        return redirect()->route('xfa-pdf.show', $document->id)
            ->with('success', 'Document updated successfully.');
    }

    /**
     * Delete a document.
     */
    public function destroy($id)
    {
        $document = XfaDocument::findOrFail($id);
        $disk = config('xfa-pdf.disk', 'local');

        Storage::disk($disk)->delete($document->file_path);
        $document->delete();

        return redirect()->route('xfa-pdf.index')
            ->with('success', 'Document deleted.');
    }

    /**
     * AJAX: Add a repeatable item.
     */
    public function addItem(Request $request, $id)
    {
        $document = XfaDocument::findOrFail($id);
        $fullPath = $this->getFullPath($document);
        $xfaPdf = $this->xfa->load($fullPath);

        $sectionName = $request->input('section');
        $container = $request->input('container');
        $data = $request->input('data', []);

        $this->xfa->addRepeatableItem($xfaPdf, $sectionName, $container, $data);
        $this->xfa->saveXfaPdf($xfaPdf);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: Remove a repeatable item.
     */
    public function removeItem(Request $request, $id)
    {
        $document = XfaDocument::findOrFail($id);
        $fullPath = $this->getFullPath($document);
        $xfaPdf = $this->xfa->load($fullPath);

        $sectionName = $request->input('section');
        $container = $request->input('container');
        $index = (int) $request->input('index', 0);

        $this->xfa->removeRepeatableItem($xfaPdf, $sectionName, $container, $index);
        $this->xfa->saveXfaPdf($xfaPdf);

        return response()->json(['success' => true]);
    }

    /**
     * Get the full filesystem path for a document.
     */
    private function getFullPath(XfaDocument $document): string
    {
        $disk = config('xfa-pdf.disk', 'local');

        return Storage::disk($disk)->path($document->file_path);
    }

    /**
     * Build human-readable section labels using nav tooltips when available.
     *
     * @return array<string, string>
     */
    private function buildSectionLabels(array $sections, array $navSections): array
    {
        $labels = [];

        foreach ($sections as $name) {
            if (preg_match('/Section_(\d+)_/', $name, $m)) {
                $num = (int) $m[1];
                if (isset($navSections[$num])) {
                    $labels[$name] = $num . '. ' . $navSections[$num];
                    continue;
                }
            }

            $labels[$name] = PreviewService::humanize($name);
        }

        return $labels;
    }

    /**
     * Flatten nested input arrays to slash-separated field paths.
     * Skips indexed arrays (repeatable items) — those are handled separately.
     *
     * @return array<string, string>
     */
    private function flattenFields(array $fields, string $prefix = ''): array
    {
        $flat = [];

        foreach ($fields as $key => $value) {
            // Skip numeric keys and keys containing "[" — these are repeatable items
            // e.g. numeric index 0, 1, 2 or bracket keys like "Error[0", "cpd[1"
            if (is_int($key) || strpos((string) $key, '[') !== false) {
                continue;
            }

            $path = $prefix ? $prefix . '/' . $key : $key;

            if (is_array($value)) {
                // Skip indexed arrays (repeatable items)
                $keys = array_keys($value);
                $hasNumericKey = false;
                foreach ($keys as $k) {
                    if (is_int($k) || strpos((string) $k, '[') !== false) {
                        $hasNumericKey = true;
                        break;
                    }
                }
                if ($hasNumericKey) {
                    continue;
                }
                $flat = array_merge($flat, $this->flattenFields($value, $path));
            } else {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }
}
