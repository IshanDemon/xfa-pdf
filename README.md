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

### Step 5: Verify

```bash
php artisan route:list --path=xfa-pdf
```

---

## How to Use — PHP API

The API is simple and stateful. Call `setFile()` once, then use `read()`, `update()`, `preview()`, and `save()`.

### Loading a PDF

```php
use Xfa\Pdf\Facades\XfaPdf;

// Load an XFA PDF file
XfaPdf::setFile('path/to/form.pdf');
```

Or with dependency injection:

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
        $this->xfa->setFile('path/to/form.pdf');
        // ...
    }
}
```

### Reading Data

```php
$xfa = app('xfa-pdf');
$xfa->setFile('public/mag42.pdf');

// List all section names
$sections = $xfa->sections();
// ['CommonSections', 'Section_3_AppraisalDetails', 'Section_4_ScopeOfWork', ...]

// Read all fields in a section
$fields = $xfa->read('Section_3_AppraisalDetails');
// ['appraiseeName' => 'Dr John Doe', 'GMCNumber' => '1234567', ...]

// Read a single field
$year = $xfa->readField('Section_3_AppraisalDetails', 'appraisalYear');
// '2024-2025'

// Get everything as a nested array
$all = $xfa->toArray();

// Get raw XML
$xml = $xfa->rawXml();
```

### Updating Data

```php
$xfa = app('xfa-pdf');
$xfa->setFile('public/mag42.pdf');

// Update a single field
$xfa->updateField('Section_3_AppraisalDetails', 'appraisalYear', '2025-2026');

// Update multiple fields in a section at once
$xfa->update('Section_3_AppraisalDetails', [
    'appraiseeName' => 'Dr Jane Smith',
    'GMCNumber' => '7654321',
    'contactEmail' => 'jane@example.com',
]);

// Save changes back to the PDF
$xfa->save();

// Or save to a different file
$xfa->save('path/to/output.pdf');
```

### Generating a Preview

```php
$xfa = app('xfa-pdf');
$xfa->setFile('public/mag42.pdf');

// Get full HTML preview
$html = $xfa->preview();

// Save it
file_put_contents(storage_path('preview.html'), $html);
```

### Section-Specific Methods (Fluent API)

Every section has a dedicated accessor method that returns a `Section` proxy. The proxy has the same `read()`, `readField()`, `update()`, and `updateField()` methods — but scoped to that section, so you don't need to pass the section name.

```php
$xfa = app('xfa-pdf');
$xfa->setFile('public/mag42.pdf');

// Read all fields in Scope of Work
$fields = $xfa->scopeOfWork()->read();

// Read a single field
$changes = $xfa->scopeOfWork()->readField('changesToPractice');

// Update a single field
$xfa->scopeOfWork()->updateField('changesToPractice', 'Started new role at hospital');

// Update multiple fields
$xfa->scopeOfWork()->update([
    'changesToPractice' => 'Started new role',
    'changesToPracticeNextYear' => 'Plan to expand clinic hours',
]);

// Chain updates across sections
$xfa->personalDetails()->updateField('appraiseeName', 'Dr Jane Smith');
$xfa->cpd()->updateField('commentaryOnActivities', 'Completed 50 CPD credits');
$xfa->probity()->updateField('anythingToDeclare', 'No');
$xfa->save();
```

### All Available Section Methods

| Method | XFA Section | Description |
|--------|-------------|-------------|
| `personalDetails()` | `Section_3_AppraisalDetails` | Personal details, GMC number, contact info |
| `scopeOfWork()` | `Section_4_ScopeOfWork` | Scope of practice, roles |
| `previousAppraisals()` | `Section_5_PreviousAppraisals` | Record of annual appraisals |
| `lastYearsPdp()` | `Section_6_LastYearsPDP` | Personal development plan review |
| `cpd()` | `Section_7_CPD` | Continuing professional development |
| `qualityImprovement()` | `Section_8_QualityImprovement` | Quality improvement activity |
| `significantEvents()` | `Section_9_SignificantEvents` | Significant events |
| `feedback()` | `Section_10_Feedback` | Colleague and patient feedback |
| `complaints()` | `Section_11_Complaints` | Complaints and compliments |
| `achievements()` | `Section_12_AchievementsChallanges` | Achievements, challenges, aspirations |
| `probity()` | `Section_13_Probity` | Probity and health |
| `additionalInfo()` | `Section_14_AdditionalInfo` | Additional info requested by RO |
| `supportingInformation()` | `Section_15_SupportingInformation` | Supporting information |
| `preAppraisalPrep()` | `Section_16_preApprisalPrep` | Pre-appraisal preparation |
| `checklist()` | `Section_17_Checklist` | Appraisal checklist |
| `agreedPdp()` | `Section_18_TheAgreedPDP` | The agreed personal development plan |
| `appraisalSummary()` | `Section_19_AppraisalSummary` | Appraisal summary |
| `appraisalOutputs()` | `Section_20_AppraisalOutputs` | Appraisal outputs |
| `commonSections()` | `CommonSections` | Branding, metadata, declarations |
| `formControls()` | `FormControls` | Form status |
| `section($name)` | *(any)* | Access any section by its XFA name |

### Complete Example

```php
use Xfa\Pdf\XfaPdfManager;

$xfa = app(XfaPdfManager::class);
$xfa->setFile(storage_path('documents/appraisal.pdf'));

// Read current data
$name = $xfa->personalDetails()->readField('appraiseeName');
$year = $xfa->personalDetails()->readField('appraisalYear');
$allSections = $xfa->sections();

// Update personal details
$xfa->personalDetails()->update([
    'appraiseeName' => 'Dr Jane Smith',
    'GMCNumber' => '7654321',
    'appraisalYear' => '2025-2026',
]);

// Update scope of work
$xfa->scopeOfWork()->updateField('changesToPractice', 'Started new hospital role');

// Update probity
$xfa->probity()->update([
    'probityChk' => '1',
    'healthChk' => '1',
]);

// Generate preview
$html = $xfa->preview();
file_put_contents(storage_path('preview.html'), $html);

// Save to a new file
$xfa->save(storage_path('documents/appraisal_updated.pdf'));
```

### Field Metadata

```php
$xfa->setFile('form.pdf');

// Get field types, options, and captions from the template
$meta = $xfa->fieldMetadata();
// [
//     'Section_3.appraisalYear' => ['type' => 'select', 'options' => ['2023-2024', ...], 'caption' => 'Appraisal Year'],
//     'Section_13.probityChk'   => ['type' => 'checkbox', 'options' => [], 'caption' => 'I confirm...'],
//     ...
// ]

// Get repeatable subform info
$repeatables = $xfa->repeatableSubforms();

// Get navigation labels
$nav = $xfa->navigationSections();
// [1 => 'Contents', 2 => 'Instructions', 3 => 'Personal details', ...]
```

---

## How to Use — Web UI

### Upload

1. Start your Laravel app: `php artisan serve`
2. Visit `http://localhost:8000/xfa-pdf`
3. Click **Upload PDF** and select your XFA PDF file
4. The package validates it contains XFA data and stores it

### Preview (Read-Only)

- Left sidebar lists all sections with human-readable labels
- Click a section to scroll to it
- Fields show their current values with correct formatting

### Edit

- Click **Edit** from the preview page
- All fields become editable form controls (text, select, radio, checkbox, date, textarea)
- Repeatable items have **+ Add** and **Remove** buttons
- Click **Save Changes** to write back to the PDF via incremental update

### Delete

Click **Delete** on the documents list to remove both the database record and stored PDF.

---

## How to Use — Artisan CLI

```bash
# List sections
php artisan xfa-pdf:manage form.pdf --sections

# Read all data
php artisan xfa-pdf:manage form.pdf --read

# Read a specific section
php artisan xfa-pdf:manage form.pdf --section=Section_7_CPD

# Get a single field
php artisan xfa-pdf:manage form.pdf --get="Section_3_AppraisalDetails/appraisalYear"

# Set a field
php artisan xfa-pdf:manage form.pdf --set="Section_3_AppraisalDetails/appraisalYear=2025-2026"

# Set a field and save to a different file
php artisan xfa-pdf:manage form.pdf --set="Section_3_AppraisalDetails/appraisalYear=2025-2026" --output=updated.pdf

# Get raw XML
php artisan xfa-pdf:manage form.pdf --read --raw

# Generate HTML preview (opens in browser)
php artisan xfa-pdf:manage form.pdf --preview
```

---

## Error Handling

```php
use Xfa\Pdf\Exceptions\InvalidPdfException;
use Xfa\Pdf\Exceptions\NoXfaDataException;
use Xfa\Pdf\Exceptions\FieldNotFoundException;

try {
    $xfa->setFile('file.pdf');
} catch (InvalidPdfException $e) {
    // File not found, not readable, or not a valid PDF
} catch (NoXfaDataException $e) {
    // PDF exists but contains no XFA datasets
}

try {
    $xfa->updateField('Section_99', 'nonExistent', 'value');
} catch (FieldNotFoundException $e) {
    // Field path does not exist
}
```

---

## Testing

```bash
cd xfa-pdf
vendor/bin/phpunit
```

---

## License

MIT
