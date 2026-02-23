# XFA PDF

A Laravel package for reading, editing, previewing, and writing XFA (XML Forms Architecture) PDF forms. Works with any XFA PDF — auto-detects XML namespaces, supports repeatable sections, and preserves all interactive features through non-destructive incremental PDF updates.

## About

### What is XFA?

XFA (XML Forms Architecture) is an Adobe XML-based PDF format where form structure (template) and field data (datasets) are stored as separate XML packets inside a PDF container. Unlike standard AcroForms, XFA supports dynamic content reflow, growable fields, and repeating sections.

**Important:** XFA is deprecated in PDF 2.0 and only renders fully in Adobe Acrobat/Reader. Browser PDF viewers do not support XFA. This package lets you read, edit, and manage XFA form data through a web UI and PHP API without needing Adobe Acrobat.

### What This Package Does

- **Parse** any XFA PDF and extract all form sections and field values
- **Edit** field values (text, dropdowns, checkboxes, dates, radio buttons) through a web UI or PHP code
- **Add/remove repeatable items** (table rows, list entries) that mirror XFA's `instanceManager` behavior
- **Preview** the full form as an interactive HTML page with a sidebar navigation
- **Save** changes back to the PDF using incremental updates — the original PDF binary is never modified, so all interactive features (navigation, scripts, signatures) are preserved
- **Auto-detect namespaces** — works with any XFA PDF, not just a specific form type
- **CLI tool** for reading, writing, and previewing from the terminal

### Architecture

The package follows the **microservice pattern** with dedicated service classes for each operation:

| Service | Responsibility |
|---------|---------------|
| `PdfBinaryService` | Low-level PDF binary parsing, stream discovery, Flate decompression, incremental writes |
| `DatasetService` | XFA datasets XML: read sections, get/set field values |
| `TemplateService` | XFA template XML: extract field types, options, captions, repeatable metadata |
| `RepeatableService` | Add/remove/update repeatable items (mirrors XFA `instanceManager`) |
| `PreviewService` | Generate HTML preview with correct form controls per field type |
| `NamespaceService` | Auto-detect and register XML namespaces from any XFA PDF |

All services are wired together through `XfaPdfManager`, accessible via the `XfaPdf` facade.

### Requirements

- PHP 7.4+
- Laravel 6.x
- PHP extensions: `ext-dom`, `ext-zlib`
- No paid dependencies — pure PHP

---

## Installation

### Step 1: Add the Repository

Add the GitHub repository to your Laravel app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/IshanDemon/xfa-pdf.git"
        }
    ]
}
```

**For local development** (if you have the package cloned alongside your app), use a path repository instead:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../xfa-pdf"
        }
    ]
}
```

### Step 2: Require the Package

```bash
composer require xfa/pdf
```

Laravel will auto-discover the service provider and facade. You should see `xfa/pdf` in the output of `php artisan package:discover`.

### Step 3: Publish the Config

```bash
php artisan vendor:publish --tag=xfa-pdf-config
```

This creates `config/xfa-pdf.php` where you can customize:

```php
return [
    'route_prefix' => 'xfa-pdf',       // URL prefix for all routes
    'middleware'    => ['web'],          // Middleware for routes
    'disk'         => 'local',          // Storage disk for uploads
    'upload_path'  => 'xfa-pdf',        // Directory within the disk
    'max_upload_size' => 20,            // Max upload size in MB
];
```

### Step 4: Run the Migration

```bash
php artisan migrate
```

This creates the `xfa_documents` table used by the web UI to track uploaded PDFs.

### Step 5: Verify

```bash
php artisan route:list --path=xfa-pdf
```

You should see 9 routes registered:

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/xfa-pdf` | `xfa-pdf.index` | List all documents |
| GET | `/xfa-pdf/upload` | `xfa-pdf.create` | Upload form |
| POST | `/xfa-pdf/upload` | `xfa-pdf.store` | Handle upload |
| GET | `/xfa-pdf/{id}` | `xfa-pdf.show` | Preview (read-only) |
| GET | `/xfa-pdf/{id}/edit` | `xfa-pdf.edit` | Edit form |
| PUT | `/xfa-pdf/{id}` | `xfa-pdf.update` | Save edits |
| DELETE | `/xfa-pdf/{id}` | `xfa-pdf.destroy` | Delete document |
| POST | `/xfa-pdf/{id}/add-item` | `xfa-pdf.add-item` | AJAX: add repeatable item |
| POST | `/xfa-pdf/{id}/remove-item` | `xfa-pdf.remove-item` | AJAX: remove repeatable item |

### Optional: Publish Views

If you want to customize the Blade templates:

```bash
php artisan vendor:publish --tag=xfa-pdf-views
```

This copies the views to `resources/views/vendor/xfa-pdf/`.

---

## How to Use

There are three ways to use the package:

1. **Web UI** — Upload, preview, edit, and save through the browser
2. **PHP API** — Use the `XfaPdf` facade or `XfaPdfManager` in your own code
3. **Artisan CLI** — Read, write, and preview from the terminal

---

## Web UI Guide

### Uploading an XFA PDF

1. Start your Laravel app:
   ```bash
   php artisan serve
   ```

2. Open your browser and go to:
   ```
   http://localhost:8000/xfa-pdf
   ```

3. You will see the **Documents** page (empty at first). Click the **Upload PDF** button.

4. On the upload page:
   - Click **Choose File** and select your XFA PDF file (e.g., `mag42.pdf`)
   - Optionally enter a **Document Name** (if left blank, the filename is used)
   - Click **Upload**

5. The package will:
   - Store the file in `storage/app/xfa-pdf/`
   - Parse the PDF to verify it contains valid XFA data
   - Extract the list of sections and store metadata in the database
   - Redirect you to the **Preview** page

   If the PDF does not contain XFA data, you will see an error message and the file will be deleted.

### Previewing a Document (Read-Only)

After uploading (or by clicking **Preview** on the Documents list), you will see the **Preview** page:

1. The page has two panels:
   - **Left sidebar** — Lists all form sections with human-readable labels (e.g., "3. Personal Details", "7. Continuing Professional Development"). These labels are auto-extracted from the XFA template's navigation button tooltips when available.
   - **Main area** — Shows collapsible section cards. The first two sections are open by default.

2. Click any **section name** in the sidebar to scroll to that section and expand it.

3. Click a **section header** in the main area to toggle it open/closed.

4. Each section shows its fields as label-value pairs:
   - Text fields show their current value
   - Empty fields show "(empty)" in grey italic
   - Nested groups (like address blocks) are indented under a blue header
   - Repeatable items (like CPD records) show an item count badge (e.g., "3 items") and each item is listed with its sub-fields

5. To switch to editing, click the **Edit** button in the top-right corner.

### Editing a Document

From the Preview page, click **Edit** to open the interactive edit form:

1. The layout is the same (sidebar + sections), but every field is now an editable form control:

   | Field Type | Control |
   |-----------|---------|
   | Text | Text input |
   | Textarea | Multi-line textarea |
   | Number | Number input |
   | Date | Date picker (auto-converts DD/MM/YYYY to YYYY-MM-DD) |
   | Select/Dropdown | `<select>` with all options from the XFA template |
   | Radio | Radio button group |
   | Checkbox | Checkbox with caption |

2. Edit any fields you want to change.

3. For **repeatable items** (like CPD records, scope of work rows):
   - Each group shows a **"+ Add Item"** button to add a new row
   - Each item has a **"Remove"** button to delete it
   - New items are cloned from the last existing item with empty values
   - At least one item must remain (removing the last one shows an alert)

4. When done, click the **Save Changes** button at the bottom of the page.

5. The package will:
   - Collect all modified field values from the form
   - Apply them to the XFA datasets XML using namespace-aware XPath
   - Write the changes back to the PDF using an **incremental update** — this appends new data at the end of the file without modifying any existing bytes
   - Redirect you to the Preview page with a success message

6. The saved PDF is fully functional in Adobe Acrobat Reader — all navigation, scripts, and interactive features remain intact.

### Deleting a Document

On the Documents list page, click **Delete** next to any document. You will be asked to confirm. This deletes both the database record and the stored PDF file.

---

## PHP API Guide

### Loading an XFA PDF

```php
use Xfa\Pdf\Facades\XfaPdf;

// Load a PDF file — returns an XfaPdf value object
$xfaPdf = XfaPdf::load('/path/to/form.pdf');
```

The `load()` method:
- Reads the PDF binary
- Discovers and decompresses all XFA dataset streams
- Parses the datasets XML into a `DOMDocument`
- Extracts the template XML (for field type metadata)
- Returns an `XfaPdf` value object that you pass to all subsequent methods

### Reading Sections and Fields

```php
// Get all section names
$sections = XfaPdf::getSections($xfaPdf);
// Returns: ['CommonSections', 'Section_3_AppraisalDetails', 'Section_4_ScopeOfWork', ...]

// Get all fields in a section as a nested array
$fields = XfaPdf::getFields($xfaPdf, 'Section_3_AppraisalDetails');
// Returns: ['appraiseeName' => 'John Doe', 'GMCNumber' => '1234567', ...]

// Get a single field value by slash-separated path
$value = XfaPdf::getFieldValue($xfaPdf, 'Section_3_AppraisalDetails/appraisalYear');
// Returns: '2024-2025'

// Get everything as a nested PHP array
$all = XfaPdf::toArray($xfaPdf);

// Get the raw XML string
$xml = XfaPdf::getRawXml($xfaPdf);
```

### Setting Field Values

```php
// Set a single field
$xfaPdf = XfaPdf::setFieldValue($xfaPdf, 'Section_3_AppraisalDetails/appraisalYear', '2025-2026');

// Set multiple fields at once (more efficient — one DOM pass)
$xfaPdf = XfaPdf::setFieldValues($xfaPdf, [
    'Section_3_AppraisalDetails/appraiseeName' => 'Dr Jane Smith',
    'Section_3_AppraisalDetails/GMCNumber' => '7654321',
    'Section_3_AppraisalDetails/contactEmail' => 'jane@example.com',
]);

// Save changes back to the original PDF file
XfaPdf::save($xfaPdf);

// Or save to a different file
XfaPdf::save($xfaPdf, '/path/to/output.pdf');
```

**How `save()` works:** It uses an incremental PDF update — the modified datasets XML is compressed with Flate encoding, wrapped in a new PDF object, and appended to the end of the file along with a new cross-reference table. The original file content is never touched. This means all interactive features (navigation scripts, digital signatures, bookmarks) are preserved.

### Working with Field Metadata

```php
// Get field types, options, and captions from the template
$meta = XfaPdf::getFieldMetadata($xfaPdf);
// Returns:
// [
//     'Section_3.appraisalYear' => ['type' => 'select', 'options' => ['2023-2024', '2024-2025', ...], 'caption' => 'Appraisal Year'],
//     'Section_3.birthDate'     => ['type' => 'date', 'options' => [], 'caption' => 'Date of Birth'],
//     'Section_13.probityChk'   => ['type' => 'checkbox', 'options' => [], 'caption' => 'I confirm...'],
//     'Section_20.statement1'   => ['type' => 'radio', 'options' => ['yes', 'no'], 'caption' => ''],
//     ...
// ]

// Get repeatable subform metadata
$repeatables = XfaPdf::getRepeatableSubforms($xfaPdf);
// Returns:
// [
//     'cpd' => ['fields' => ['role', 'description', 'credits'], 'min' => 0, 'max' => -1],
//     ...
// ]

// Get navigation section labels from the template
$navLabels = XfaPdf::getNavigationSections($xfaPdf);
// Returns: [1 => 'Contents', 2 => 'Instructions', 3 => 'Personal details', ...]
```

### Working with Repeatable Items

Repeatable items are table rows or list entries that can be added/removed. In XFA, these are controlled by `instanceManager`. This package provides an equivalent PHP API.

```php
// Get existing items from a repeatable container
$items = XfaPdf::getRepeatableItems($xfaPdf, 'Section_7_CPD', 'cpdList');
// Returns:
// [
//     ['role' => 'GP', 'description' => 'Clinical work', 'credits' => '5'],
//     ['role' => 'Hospital', 'description' => 'Ward rounds', 'credits' => '3'],
// ]

// Add a new item
$xfaPdf = XfaPdf::addRepeatableItem($xfaPdf, 'Section_7_CPD', 'cpdList', [
    'role' => 'Research',
    'description' => 'Published paper on...',
    'credits' => '10',
]);

// Remove an item by index (0-based)
$xfaPdf = XfaPdf::removeRepeatableItem($xfaPdf, 'Section_7_CPD', 'cpdList', 0);

// Replace all items at once
$xfaPdf = XfaPdf::setRepeatableItems($xfaPdf, 'Section_7_CPD', 'cpdList', 'cpd', [
    ['role' => 'A', 'description' => 'First', 'credits' => '1'],
    ['role' => 'B', 'description' => 'Second', 'credits' => '2'],
    ['role' => 'C', 'description' => 'Third', 'credits' => '3'],
]);

// Don't forget to save
XfaPdf::save($xfaPdf);
```

The parameters for repeatable operations:
- **`$sectionName`** — The XFA section containing the repeatable (e.g., `'Section_7_CPD'`)
- **`$container`** — The parent XML element name (e.g., `'cpdList'`)
- **`$element`** — The child element name for each row (e.g., `'cpd'`) — only needed for `setRepeatableItems()`
- **`$data`** / **`$items`** — Associative array(s) of field name => value

### Generating an HTML Preview

```php
// Generate a full HTML page with all sections and form controls
$html = XfaPdf::generatePreview($xfaPdf);

// Save it to a file
file_put_contents(storage_path('preview.html'), $html);

// Or with custom section labels
$html = XfaPdf::generatePreview($xfaPdf, [
    'Section_3_AppraisalDetails' => '3. Personal Details',
    'Section_7_CPD' => '7. CPD Activities',
]);
```

The generated HTML includes:
- NHS blue theme with sidebar navigation
- Collapsible sections
- Correct form controls for each field type (text, select, radio, checkbox, date, number, textarea)
- Repeatable item groups with add/remove buttons
- Responsive layout

### Complete Example: Read, Modify, and Save

```php
use Xfa\Pdf\Facades\XfaPdf;

// 1. Load the PDF
$xfaPdf = XfaPdf::load(storage_path('documents/appraisal.pdf'));

// 2. Read current values
$name = XfaPdf::getFieldValue($xfaPdf, 'Section_3_AppraisalDetails/appraiseeName');
$sections = XfaPdf::getSections($xfaPdf);

// 3. Update fields
$xfaPdf = XfaPdf::setFieldValues($xfaPdf, [
    'Section_3_AppraisalDetails/appraiseeName' => 'Dr Jane Smith',
    'Section_3_AppraisalDetails/GMCNumber' => '7654321',
    'Section_3_AppraisalDetails/appraisalYear' => '2025-2026',
]);

// 4. Add a CPD record
$xfaPdf = XfaPdf::addRepeatableItem($xfaPdf, 'Section_7_CPD', 'cpdList', [
    'role' => 'GP',
    'description' => 'Completed advanced clinical course',
    'credits' => '15',
]);

// 5. Save to a new file (preserves the original)
XfaPdf::save($xfaPdf, storage_path('documents/appraisal_updated.pdf'));

// 6. Generate a preview
$html = XfaPdf::generatePreview($xfaPdf);
file_put_contents(storage_path('documents/preview.html'), $html);
```

### Using the Manager Directly (Without Facade)

If you prefer dependency injection over the facade:

```php
use Xfa\Pdf\XfaPdfManager;

class MyController
{
    private XfaPdfManager $xfa;

    public function __construct(XfaPdfManager $xfa)
    {
        $this->xfa = $xfa;
    }

    public function process()
    {
        $xfaPdf = $this->xfa->load('/path/to/file.pdf');
        $sections = $this->xfa->getSections($xfaPdf);
        // ...
    }
}
```

### Accessing Individual Services

For advanced use cases, you can access the underlying services directly:

```php
$manager = app(XfaPdfManager::class);

$manager->pdf();         // PdfBinaryService — low-level binary operations
$manager->datasets();    // DatasetService — XML read/write
$manager->templates();   // TemplateService — field metadata
$manager->repeatables(); // RepeatableService — repeatable item operations
$manager->previews();    // PreviewService — HTML generation
$manager->namespaces();  // NamespaceService — XML namespace detection
```

---

## Artisan CLI Guide

The package provides an `xfa-pdf:manage` artisan command for working with XFA PDFs from the terminal.

### List All Sections

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --sections
```

Output:
```
Sections in XFA PDF:
  [0] CommonSections
  [1] Section_3_AppraisalDetails
  [2] Section_4_ScopeOfWork
  [3] Section_7_CPD
  ...
```

### Read All Data

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --read
```

Output:
```
=== CommonSections ===
  Branding:
    formTitle: Medical Appraisal Form
    ...

=== Section_3_AppraisalDetails ===
  appraiseeName: Dr John Doe
  GMCNumber: 1234567
  contactEmail: john@example.com
  ...
```

### Read a Specific Section

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --section=Section_7_CPD
```

### Get a Single Field Value

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --get="Section_3_AppraisalDetails/appraisalYear"
```

Output:
```
Section_3_AppraisalDetails/appraisalYear = 2024-2025
```

### Set a Field Value

```bash
# Overwrite the original file
php artisan xfa-pdf:manage storage/documents/form.pdf --set="Section_3_AppraisalDetails/appraisalYear=2025-2026"

# Save to a different file
php artisan xfa-pdf:manage storage/documents/form.pdf --set="Section_3_AppraisalDetails/appraisalYear=2025-2026" --output=storage/documents/form_updated.pdf
```

Output:
```
Current value: 2024-2025
New value: 2025-2026
Successfully updated 'Section_3_AppraisalDetails/appraisalYear' in storage/documents/form_updated.pdf
```

### Get Raw XML

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --read --raw
```

Outputs the full XFA datasets XML. Useful for piping to a file or `xmllint`:

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --read --raw > datasets.xml
```

### Generate HTML Preview

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --preview
```

This generates an HTML file and opens it in your default browser (macOS/Linux). You can specify a custom output path:

```bash
php artisan xfa-pdf:manage storage/documents/form.pdf --preview --output=public/preview.html
```

---

## Error Handling

The package throws specific exceptions you can catch:

```php
use Xfa\Pdf\Exceptions\InvalidPdfException;
use Xfa\Pdf\Exceptions\NoXfaDataException;
use Xfa\Pdf\Exceptions\FieldNotFoundException;

try {
    $xfaPdf = XfaPdf::load('/path/to/file.pdf');
} catch (InvalidPdfException $e) {
    // File not found, not readable, or not a valid PDF
} catch (NoXfaDataException $e) {
    // PDF exists but contains no XFA datasets or template
}

try {
    XfaPdf::setFieldValue($xfaPdf, 'Section_99/nonExistent', 'value');
} catch (FieldNotFoundException $e) {
    // The field path does not exist in the datasets XML
}
```

---

## Configuration Reference

After publishing with `php artisan vendor:publish --tag=xfa-pdf-config`, the config file at `config/xfa-pdf.php` has these options:

| Key | Default | Description |
|-----|---------|-------------|
| `route_prefix` | `'xfa-pdf'` | URL prefix for all package routes |
| `middleware` | `['web']` | Middleware applied to routes |
| `disk` | `'local'` | Laravel filesystem disk for file storage |
| `upload_path` | `'xfa-pdf'` | Directory within the disk for uploaded PDFs |
| `max_upload_size` | `20` | Maximum upload file size in MB |

---

## Testing

The package includes 28 unit tests covering all core services:

```bash
cd xfa-pdf
vendor/bin/phpunit
```

```
OK (28 tests, 59 assertions)
```

Tests cover:
- `NamespaceServiceTest` — Namespace detection, registration, multiple default namespaces
- `DatasetServiceTest` — Section listing, field read/write, batch updates, DOM-to-array conversion
- `TemplateServiceTest` — Field metadata extraction (all types), repeatable subform detection
- `RepeatableServiceTest` — Get, add, remove, update, and replace items; error handling

---

## Package Structure

```
xfa-pdf/
├── composer.json
├── config/
│   └── xfa-pdf.php                              # Package configuration
├── database/
│   └── migrations/
│       └── create_xfa_documents_table.php        # Database migration
├── resources/
│   └── views/
│       ├── layout.blade.php                      # Base layout (NHS blue theme)
│       ├── index.blade.php                       # Document list page
│       ├── upload.blade.php                      # Upload form page
│       ├── show.blade.php                        # Read-only preview page
│       ├── edit.blade.php                        # Interactive edit page
│       └── partials/
│           ├── fields-readonly.blade.php          # Recursive read-only field rendering
│           └── fields-editable.blade.php          # Recursive editable field rendering
├── routes/
│   └── web.php                                   # Package routes
├── src/
│   ├── XfaPdfServiceProvider.php                 # Laravel service provider
│   ├── XfaPdfManager.php                         # Facade target / orchestrator
│   ├── XfaPdf.php                                # Value object (loaded PDF state)
│   ├── Facades/
│   │   └── XfaPdf.php                            # Laravel facade
│   ├── Commands/
│   │   └── XfaPdfCommand.php                     # Artisan CLI command
│   ├── Exceptions/
│   │   ├── XfaPdfException.php                   # Base exception
│   │   ├── InvalidPdfException.php               # Invalid PDF errors
│   │   ├── NoXfaDataException.php                # Missing XFA data
│   │   └── FieldNotFoundException.php            # Bad field path
│   ├── Http/
│   │   └── Controllers/
│   │       └── XfaPdfController.php              # Web UI controller
│   ├── Models/
│   │   └── XfaDocument.php                       # Eloquent model
│   ├── Services/
│   │   ├── PdfBinaryService.php                  # PDF binary parsing & writing
│   │   ├── DatasetService.php                    # XFA datasets XML operations
│   │   ├── TemplateService.php                   # XFA template XML operations
│   │   ├── RepeatableService.php                 # Repeatable item management
│   │   ├── PreviewService.php                    # HTML preview generation
│   │   ├── NamespaceService.php                  # XML namespace auto-detection
│   │   └── Preview/
│   │       ├── FieldRendererInterface.php         # Renderer contract
│   │       ├── InputRenderer.php                  # Text, number, date inputs
│   │       ├── SelectRenderer.php                 # Dropdown selects
│   │       ├── RadioRenderer.php                  # Radio button groups
│   │       ├── CheckboxRenderer.php               # Checkboxes
│   │       └── TextareaRenderer.php               # Multi-line text areas
├── tests/
│   ├── TestCase.php                              # Base test (Orchestra Testbench)
│   └── Unit/
│       └── Services/
│           ├── NamespaceServiceTest.php
│           ├── DatasetServiceTest.php
│           ├── TemplateServiceTest.php
│           └── RepeatableServiceTest.php
├── phpunit.xml.dist
├── LICENSE
└── .gitignore
```

---

## License

MIT
