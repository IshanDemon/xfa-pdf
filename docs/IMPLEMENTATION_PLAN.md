# Plan: Create `xfa-pdf` Laravel Package

## Context

The medsu project has a working but tightly-coupled XFA PDF implementation (`XfaPdfService.php`, ~1200 lines). It works ~30% — can read/write fields and generate HTML preview, but lacks interactive editing, adding/removing repeatable items via UI, and is hardcoded to NHS MAG namespaces. We're extracting this into a universal, modular Laravel package following Spatie conventions.

## Architecture: Microservice Pattern

Each operation is a dedicated service class. Services are small, focused, and composable.

```
src/
├── XfaPdfServiceProvider.php          # Laravel glue
├── Facades/XfaPdf.php                 # Facade
├── XfaPdfManager.php                  # Orchestrator (facade target)
│
├── Services/
│   ├── PdfBinaryService.php           # Low-level PDF binary parsing
│   │   - discoverStreams()            # Find stream/endstream blocks
│   │   - decompressStream()           # Flate decompression
│   │   - extractTrailerInfo()         # PDF trailer parsing
│   │   - writeIncrementalUpdate()     # Append new objects + xref
│   │
│   ├── DatasetService.php             # XFA datasets XML operations
│   │   - extract(binary)             # Find & parse <xfa:datasets> stream
│   │   - getSections()               # List all section names
│   │   - getFields(section)          # Get fields for a section
│   │   - getFieldValue(path)         # Get single field value via XPath
│   │   - setFieldValue(path, value)  # Set single field value
│   │   - setFieldValues(array)       # Batch set fields
│   │   - toArray()                   # Full datasets as nested PHP array
│   │
│   ├── TemplateService.php            # XFA template XML operations
│   │   - extract(binary)             # Find & parse <xfa-template> stream
│   │   - getFieldMetadata()          # Field types, options, captions
│   │   - getRepeatableSubforms()     # Detect repeatable sections
│   │   - getFieldType(name)          # Single field type lookup
│   │
│   ├── RepeatableService.php          # Add/remove repeatable items (mirrors XFA instanceManager)
│   │   - getItems(section, container) # Get existing items from datasets XML
│   │   - addItem(section, container, data) # Add new item (like instanceManager.addInstance)
│   │   - removeItem(section, container, index) # Remove by index (like instanceManager.removeInstance)
│   │   - updateItem(section, container, index, data) # Update specific item
│   │   - setItems(section, container, items) # Replace all items (like instanceManager.setInstances)
│   │   - getRowTemplate(container) # Get field names+types for a new empty row from template XML
│   │
│   ├── PreviewService.php             # HTML preview generation
│   │   - generate(xfaPdf)            # Full HTML page
│   │   - renderSection(name, fields) # Single section HTML
│   │   - renderField(name, value, meta) # Single field control
│   │   Uses sub-renderers:
│   │   ├── InputRenderer.php          # text, number, date inputs
│   │   ├── SelectRenderer.php         # dropdown/choiceList
│   │   ├── RadioRenderer.php          # exclGroup radio buttons
│   │   ├── CheckboxRenderer.php       # checkButton checkboxes
│   │   └── TextareaRenderer.php       # multiLine textEdit
│   │
│   └── NamespaceService.php           # Auto-detect XML namespaces
│       - detect(xml)                  # Parse all xmlns declarations
│       - registerOnXPath(xpath)       # Register detected ns on DOMXPath
│       - getDataNamespace()           # The data root namespace (e.g. NHS/AppraisalForm)
│       - getXfaNamespace()            # Always http://www.xfa.org/schema/xfa-data/1.0/
│
├── XfaPdf.php                         # Value object: loaded PDF state
│   - binary, filePath, dom, template
│   - Immutable container passed between services
│
├── Http/
│   └── Controllers/
│       └── XfaPdfController.php       # Web UI controller
│           - index()                  # List stored documents
│           - show(id)                 # Preview document
│           - edit(id)                 # Edit form (interactive HTML)
│           - update(id, Request)      # Save edits back to PDF
│           - upload()                 # Upload XFA PDF form
│           - addItem(id, Request)     # AJAX: add repeatable item
│           - removeItem(id, Request)  # AJAX: remove repeatable item
│
├── Models/
│   └── XfaDocument.php                # Eloquent model
│       - name, original_filename, file_path, metadata (JSON)
│
├── Exceptions/
│   ├── XfaPdfException.php            # Base exception
│   ├── InvalidPdfException.php        # Not a valid PDF
│   ├── NoXfaDataException.php         # No XFA datasets in PDF
│   └── FieldNotFoundException.php     # Field path not found
│
├── Commands/
│   └── XfaPdfCommand.php             # Artisan CLI (ported from MagWrite)
│
config/xfa-pdf.php
routes/web.php
resources/views/{index,show,edit,upload}.blade.php
database/migrations/create_xfa_documents_table.php.stub
tests/
```

## Implementation Order (Files to Create)

### Phase 1: Core Services (no Laravel dependencies)

1. **`src/Exceptions/XfaPdfException.php`** — Base exception
2. **`src/Exceptions/InvalidPdfException.php`** — PDF validation errors
3. **`src/Exceptions/NoXfaDataException.php`** — Missing XFA data
4. **`src/Exceptions/FieldNotFoundException.php`** — Bad field path
5. **`src/Services/NamespaceService.php`** — Auto-detect XML namespaces (NEW - medsu hardcodes these)
6. **`src/Services/PdfBinaryService.php`** — Port from medsu `XfaPdfService`: `discoverStreams()`, `decompressStream()`, `extractTrailerInfo()`, `groupConsecutiveKeys()`, `writeIncrementalUpdate()`
7. **`src/Services/DatasetService.php`** — Port from medsu: `readXfaDatasets()`, `getSectionNames()`, `getSectionFields()`, `getFieldValue()`, `setFieldValue()`, `setFieldValues()`, `nodeToArray()`. Use NamespaceService instead of hardcoded NS.
8. **`src/Services/TemplateService.php`** — Port from medsu: `extractTemplateXml()`, `extractFieldMetadata()`, `extractRepeatableSubforms()`
9. **`src/Services/RepeatableService.php`** — NEW: Port `applyRepeatableToDOM()` from MagExportService + add `addItem()`, `removeItem()`, `updateItem()`, `getItems()`
10. **`src/XfaPdf.php`** — Value object holding loaded PDF state

### Phase 2: Preview Microservices

11. **`src/Services/Preview/InputRenderer.php`** — Render text/number/date inputs
12. **`src/Services/Preview/SelectRenderer.php`** — Render dropdowns
13. **`src/Services/Preview/RadioRenderer.php`** — Render radio groups
14. **`src/Services/Preview/CheckboxRenderer.php`** — Render checkboxes
15. **`src/Services/Preview/TextareaRenderer.php`** — Render textareas
16. **`src/Services/PreviewService.php`** — Orchestrate renderers, generate full HTML (port from medsu `generatePreviewHtml()` + `renderFieldsHtml()` + `renderControl()`)

### Phase 3: Laravel Integration

17. **`composer.json`** — Package definition
18. **`config/xfa-pdf.php`** — Config (route prefix, middleware, storage disk, upload path)
19. **`src/XfaPdfManager.php`** — Facade target, wires all services together
20. **`src/Facades/XfaPdf.php`** — Laravel facade
21. **`src/XfaPdfServiceProvider.php`** — Register bindings, load routes/views/config/migrations
22. **`routes/web.php`** — Package routes
23. **`src/Models/XfaDocument.php`** — Eloquent model
24. **`database/migrations/create_xfa_documents_table.php.stub`** — Migration stub
25. **`src/Http/Controllers/XfaPdfController.php`** — Web controller

### Phase 4: Views

26. **`resources/views/layout.blade.php`** — Base layout (NHS blue theme from medsu preview)
27. **`resources/views/index.blade.php`** — List documents
28. **`resources/views/upload.blade.php`** — Upload XFA PDF
29. **`resources/views/show.blade.php`** — Preview (read-only)
30. **`resources/views/edit.blade.php`** — Interactive edit form with add/remove repeatable items

### Phase 5: CLI + Tests

31. **`src/Commands/XfaPdfCommand.php`** — Port from MagWrite (--read, --sections, --get, --set, --preview)
32. **`tests/TestCase.php`** — Base test class
33. **`tests/Unit/Services/NamespaceServiceTest.php`**
34. **`tests/Unit/Services/DatasetServiceTest.php`**
35. **`tests/Unit/Services/TemplateServiceTest.php`**
36. **`tests/Unit/Services/RepeatableServiceTest.php`**
37. **`phpunit.xml.dist`** + **`.gitignore`** + **`LICENSE`**

## Key Code to Port from Medsu (with modifications)

| Medsu Method | Source File | Target | Change Required |
|---|---|---|---|
| `discoverStreams()` | XfaPdfService:382 | PdfBinaryService | None - direct port |
| `decompressStream()` | XfaPdfService:466 | PdfBinaryService | None - direct port |
| `extractTrailerInfo()` | XfaPdfService:295 | PdfBinaryService | None - direct port |
| `writeXfaDatasets()` | XfaPdfService:210 | PdfBinaryService | Rename to `writeIncrementalUpdate()` |
| `groupConsecutiveKeys()` | XfaPdfService:362 | PdfBinaryService | None - direct port |
| `readXfaDatasets()` | XfaPdfService:45 | DatasetService | Use NamespaceService |
| `getSectionNames()` | XfaPdfService:501 | DatasetService | Use NamespaceService |
| `getSectionFields()` | XfaPdfService:524 | DatasetService | Use NamespaceService |
| `getFieldValue()` | XfaPdfService:91 | DatasetService | Use NamespaceService |
| `setFieldValue()` | XfaPdfService:127 | DatasetService | Use NamespaceService |
| `setFieldValues()` | XfaPdfService:166 | DatasetService | Use NamespaceService |
| `nodeToArray()` | XfaPdfService:1175 | DatasetService | None - direct port |
| `extractTemplateXml()` | XfaPdfService:719 | TemplateService | None - direct port |
| `extractFieldMetadata()` | XfaPdfService:547 | TemplateService | None - direct port |
| `extractRepeatableSubforms()` | XfaPdfService:641 | TemplateService | None - direct port |
| `applyRepeatableToDOM()` | MagExportService:241 | RepeatableService | Generalize (remove medsu-specific) |
| `createElementLikeParent()` | MagExportService:295 | RepeatableService | None - direct port |
| `generatePreviewHtml()` | XfaPdfService:747 | PreviewService | Split into renderers |
| `renderFieldsHtml()` | XfaPdfService:960 | PreviewService | Split into renderers |
| `renderControl()` | XfaPdfService:1097 | Individual renderers | Split by type |
| `humanize()` | XfaPdfService:1157 | PreviewService | None - direct port |

## MAG42 Add/Remove Button Logic (instanceManager Pattern)

The MAG42 uses XFA's `instanceManager` for repeatable items. Our package replicates this in the web UI:

### Add Button (footer subform)
```xml
<field name="add">
  <ui><button highlight="push"/></ui>
  <caption><value><text>+</text></value></caption>
  <event activity="click">
    <script>_cpd.addInstance(true);</script>
  </event>
</field>
```

### Remove Button (inside each row)
```xml
<field name="remove">
  <event activity="click">
    <script>this.parent.instanceManager.removeInstance(this.parent.index);</script>
  </event>
</field>
```

### Dual-purpose +/- Button (Scope of Work)
```javascript
var instanceCount = this.parent.instanceManager.count;
var instanceIndex = this.parent.index + 1;
if (instanceCount == instanceIndex) {
    this.parent.instanceManager.addInstance(true);  // Last row: adds
} else {
    // Not last row: removes with confirmation
    this.parent.instanceManager.removeInstance(instanceIndex - 1);
}
```

### All Repeatable Subforms in MAG42
| Instance Manager | Subform | Section | Bind Ref |
|---|---|---|---|
| `_qualRpt` | `qualRpt` | Section 3 | `(none)` |
| `(dual +/-)` | `(row)` | Section 4 | `(none)` |
| `_activRpt` | `activRpt` | Section 6 | `(none)` |
| `_cpd` | `cpd` | Section 7 | `$.Section_7_CPD.cpdList.cpd[*]` |
| `_activity` | `activity` | Section 8 | `$.activity[*]` |
| `_event` | `event` | Section 9 | `$.attach[*]` |
| `_colleagueFeedbackRow` | `colleagueFeedbackRow` | Section 10 | `(none)` |
| `_patientFeedbackRow` | `patientFeedbackRow` | Section 10 | `(none)` |
| `_event` | `event` | Section 11 | `$.complimentDetals[*]` |
| `_addInfo` | `addInfo` | Section 14 | `$.attach[*]` |
| `_evidence` | `evidence` | Section 15 | `$.InfoRepeat[*]` |
| `_goal` | `goal` | Section 18 | `(section bind)` |

### How `<occur>` Controls Repetition
```xml
<occur max="-1" min="0" initial="1"/>
<!-- max="-1" = unlimited instances -->
<!-- min="0" = can remove all -->
<!-- initial="1" = start with 1 row -->
```

### Bind Wildcard Pattern
```xml
<bind match="dataRef" ref="$.Section_7_CPD.cpdList.cpd[*]"/>
<!-- [*] binds to ALL instances of cpd element in datasets -->
```

## Host App Integration

After creating the package at `../xfa-pdf/`:

```bash
composer config repositories.xfa-pdf path ../xfa-pdf
composer require xfa/pdf
php artisan vendor:publish --tag=xfa-pdf-config
php artisan vendor:publish --tag=xfa-pdf-migrations
php artisan migrate
```

## Verification

1. `cd ../xfa-pdf && composer validate` — Valid composer.json
2. `cd ../xfa-package && composer require xfa/pdf` — Installs successfully
3. `php artisan migrate` — Creates xfa_documents table
4. `php artisan serve --port=8787` then visit `/xfa-pdf` — Shows document list
5. Upload `public/mag42.pdf` via UI — Parses and stores correctly
6. Preview uploaded document — Shows all 20 sections with correct field types
7. Edit document — Can modify text fields, select dropdowns, toggle checkboxes
8. Add/remove repeatable items — CPD rows, scope of work rows work correctly
9. Save edits — Writes back to PDF via incremental update, preserves interactive features
10. CLI: `php artisan xfa-pdf:manage storage/xfa-pdf/mag42.pdf --read` — Shows all data
11. `phpunit` — All tests pass
