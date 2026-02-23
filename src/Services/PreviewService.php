<?php

declare(strict_types=1);

namespace Xfa\Pdf\Services;

use Xfa\Pdf\Services\Preview\CheckboxRenderer;
use Xfa\Pdf\Services\Preview\FieldRendererInterface;
use Xfa\Pdf\Services\Preview\InputRenderer;
use Xfa\Pdf\Services\Preview\RadioRenderer;
use Xfa\Pdf\Services\Preview\SelectRenderer;
use Xfa\Pdf\Services\Preview\TextareaRenderer;

class PreviewService
{
    /** @var array<string, FieldRendererInterface> */
    private array $renderers;

    public function __construct()
    {
        $this->renderers = [
            'text' => new InputRenderer(),
            'number' => new InputRenderer(),
            'date' => new InputRenderer(),
            'select' => new SelectRenderer(),
            'radio' => new RadioRenderer(),
            'checkbox' => new CheckboxRenderer(),
            'textarea' => new TextareaRenderer(),
        ];
    }

    /**
     * Generate a full HTML preview page.
     *
     * @param array<string, mixed> $sections Section data keyed by section name
     * @param array<string, array> $fieldMeta Field metadata from template
     * @param array<string, array> $repeatables Repeatable subform metadata
     * @param array<string, string> $sectionLabels Human-readable section labels
     * @param array<string, array> $conditionalRules Conditional visibility rules
     */
    public function generate(
        array $sections,
        array $fieldMeta,
        array $repeatables,
        array $sectionLabels,
        array $conditionalRules = []
    ): string {
        $sectionsHtml = '';
        $tocItems = '';

        foreach ($sections as $sectionKey => $fields) {
            $label = $sectionLabels[$sectionKey] ?? self::humanize($sectionKey);
            $sectionId = 'section-' . $sectionKey;
            $fieldsHtml = $this->renderFieldsHtml($fields, $sectionKey, $fieldMeta, $repeatables, $conditionalRules);
            $isEmpty = empty($fields);
            $badge = $isEmpty ? '<span class="badge empty">Empty</span>' : '';

            $tocItems .= '<a href="#' . $sectionId . '">' . e($label) . $badge . '</a>';
            $sectionsHtml .= $this->renderSection($sectionKey, $label, $fieldsHtml, $isEmpty);
        }

        return $this->wrapInLayout($sectionsHtml, $tocItems, $conditionalRules);
    }

    /**
     * Render a single section container.
     */
    public function renderSection(string $key, string $label, string $fieldsHtml, bool $isEmpty = false): string
    {
        $sectionId = 'section-' . $key;
        $badge = $isEmpty ? '<span class="badge empty">Empty</span>' : '';

        $html = '<div class="section" id="' . $sectionId . '">';
        $html .= '<div class="section-header" onclick="toggleSection(this)">';
        $html .= '<span class="arrow">&#9654;</span> ' . e($label) . $badge;
        $html .= '</div>';
        $html .= '<div class="section-body">' . $fieldsHtml . '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render fields as HTML form controls, handling nested arrays recursively.
     *
     * @param mixed $fields
     */
    public function renderFieldsHtml($fields, string $parentPath, array $fieldMeta, array $repeatables, array $conditionalRules = []): string
    {
        if (is_string($fields)) {
            return '';
        }

        if (!is_array($fields) || empty($fields)) {
            return '<div class="field-row"><span class="field-control" style="color:#aaa;font-style:italic;padding:6px 0;">No data</span></div>';
        }

        $html = '';
        foreach ($fields as $key => $value) {
            $dataPath = $parentPath ? $parentPath . '.' . $key : (string) $key;
            $inputName = str_replace('.', '/', $dataPath);
            $meta = self::resolveFieldMeta($dataPath, (string) $key, $fieldMeta);
            $fieldLabel = !empty($meta['caption']) ? $meta['caption'] : self::humanize((string) $key);

            // Check if this field is a conditional target
            $condTargetAttr = '';
            foreach ($conditionalRules as $trigger => $rule) {
                if (in_array((string) $key, $rule['targets'] ?? [])) {
                    $condTargetAttr = ' data-cond-target="' . e((string) $key) . '"';
                    break;
                }
            }

            // Check if this field is a conditional trigger
            $isTrigger = isset($conditionalRules[(string) $key]);
            $triggerAttr = $isTrigger ? ' data-cond-trigger="' . e((string) $key) . '"' : '';

            if (is_string($value)) {
                $html .= '<div class="field-row" data-field-key="' . e((string) $key) . '"' . $condTargetAttr . '>';
                $html .= '<div class="field-name">' . e($fieldLabel) . '</div>';
                $html .= '<div class="field-control"' . $triggerAttr . '>';
                $html .= $this->renderControl($inputName, $value, $meta);
                $html .= '</div></div>';
            } elseif (is_array($value)) {
                $isRepeatable = isset($repeatables[$key]);

                if (isset($value[0])) {
                    // Indexed array (multiple items)
                    $count = count($value);
                    $html .= '<div class="nested-group' . ($isRepeatable ? ' repeatable-group' : '') . '" data-field-key="' . e((string) $key) . '"' . $condTargetAttr . '>';
                    $html .= '<div class="group-title">' . e($fieldLabel);
                    $html .= ' <span class="badge list-count">' . $count . ' items</span>';
                    if ($isRepeatable) {
                        $html .= ' <button type="button" class="btn-add-item" onclick="addItem(this)">+ Add Item</button>';
                    }
                    $html .= '</div>';

                    foreach ($value as $i => $item) {
                        if (is_array($item)) {
                            $itemPath = $dataPath . '[' . $i . ']';
                            if ($isRepeatable) {
                                $html .= '<div class="repeatable-item">';
                            }
                            $html .= '<div class="nested-group">';
                            $html .= '<div class="group-title">';
                            if ($isRepeatable) {
                                $html .= '<span class="item-label">Item ' . ($i + 1) . '</span>';
                                $html .= ' <button type="button" class="btn-remove-item" onclick="removeItem(this)">Remove</button>';
                            } else {
                                $html .= 'Item ' . ($i + 1);
                            }
                            $html .= '</div>';
                            $html .= $this->renderFieldsHtml($item, $itemPath, $fieldMeta, $repeatables, $conditionalRules);
                            $html .= '</div>';
                            if ($isRepeatable) {
                                $html .= '</div>';
                            }
                        } else {
                            $itemMeta = self::resolveFieldMeta($dataPath, (string) $key, $fieldMeta);
                            $html .= '<div class="field-row" data-field-key="' . e((string) $key) . '">';
                            $html .= '<div class="field-name">' . e($fieldLabel) . ' [' . ($i + 1) . ']</div>';
                            $html .= '<div class="field-control">';
                            $html .= $this->renderControl($inputName . '[' . $i . ']', (string) $item, $itemMeta);
                            $html .= '</div></div>';
                        }
                    }
                    $html .= '</div>';
                } else {
                    // Single associative array (nested group)
                    if ($isRepeatable) {
                        $html .= '<div class="nested-group repeatable-group" data-field-key="' . e((string) $key) . '"' . $condTargetAttr . '>';
                        $html .= '<div class="group-title">' . e($fieldLabel);
                        $html .= ' <span class="badge list-count">1 items</span>';
                        $html .= ' <button type="button" class="btn-add-item" onclick="addItem(this)">+ Add Item</button>';
                        $html .= '</div>';
                        $html .= '<div class="repeatable-item">';
                        $html .= '<div class="nested-group">';
                        $html .= '<div class="group-title"><span class="item-label">Item 1</span>';
                        $html .= ' <button type="button" class="btn-remove-item" onclick="removeItem(this)">Remove</button>';
                        $html .= '</div>';
                        $html .= $this->renderFieldsHtml($value, $dataPath . '[0]', $fieldMeta, $repeatables, $conditionalRules);
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="nested-group" data-field-key="' . e((string) $key) . '"' . $condTargetAttr . '>';
                        $html .= '<div class="group-title">' . e($fieldLabel) . '</div>';
                        $html .= $this->renderFieldsHtml($value, $dataPath, $fieldMeta, $repeatables, $conditionalRules);
                        $html .= '</div>';
                    }
                }
            }
        }

        return $html;
    }

    /**
     * Convert a camelCase or snake_case key to a human-readable label.
     */
    public static function humanize(string $key): string
    {
        $label = preg_replace('/^Section_\d+_/', '', $key);
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
        $label = str_replace('_', ' ', $label);

        return ucfirst(trim($label));
    }

    /**
     * Resolve field metadata with fallback chain.
     *
     * @return array{type: string, options: string[], caption: string}
     */
    public static function resolveFieldMeta(string $dataPath, string $fieldName, array $fieldMeta): array
    {
        // Try full path
        if (isset($fieldMeta[$dataPath])) {
            return $fieldMeta[$dataPath];
        }

        // Try without top-level section prefix
        $parts = explode('.', $dataPath);
        if (count($parts) > 1) {
            $withoutFirst = implode('.', array_slice($parts, 1));
            if (isset($fieldMeta[$withoutFirst])) {
                return $fieldMeta[$withoutFirst];
            }
        }

        // Try just the field name
        if (isset($fieldMeta[$fieldName])) {
            return $fieldMeta[$fieldName];
        }

        return ['type' => 'text', 'options' => [], 'caption' => ''];
    }

    /**
     * Render a single form control based on field metadata.
     */
    private function renderControl(string $name, string $value, array $meta): string
    {
        $type = $meta['type'] ?? 'text';
        $renderer = $this->renderers[$type] ?? $this->renderers['text'];

        return $renderer->render($name, $value, $meta);
    }

    /**
     * Wrap sections HTML in a full page layout.
     */
    private function wrapInLayout(string $sectionsHtml, string $tocItems, array $conditionalRules = []): string
    {
        $css = $this->getStylesheet();
        $js = $this->getJavaScript();
        $condRulesJson = json_encode($conditionalRules);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XFA PDF Preview</title>
<style>{$css}</style>
</head>
<body>
<div class="header">
  <div>
    <h1>XFA Form Preview</h1>
    <div class="subtitle">Generated by xfa/pdf</div>
  </div>
</div>
<div class="container">
  <nav class="sidebar">{$tocItems}</nav>
  <div class="main">{$sectionsHtml}</div>
</div>
<script>{$js}</script>
<script>
var condRules = {$condRulesJson};
function applyCondVis(k,v){var r=condRules[k];if(!r)return;var vis=r.visibleWhen[v]||r.visibleWhen['_default']||[];
(r.targets||[]).forEach(function(t){var els=document.querySelectorAll('[data-cond-target="'+t+'"]');
els.forEach(function(el){el.style.display=vis.indexOf(t)!==-1?'':'none';});});}
document.querySelectorAll('[data-cond-trigger]').forEach(function(el){var k=el.getAttribute('data-cond-trigger');
if(el.tagName==='SELECT'){el.addEventListener('change',function(){applyCondVis(k,this.value);});applyCondVis(k,el.value);}
else{var radios=el.querySelectorAll('input[type="radio"]');
radios.forEach(function(r){r.addEventListener('change',function(){applyCondVis(k,this.value);});});
var c=el.querySelector('input[type="radio"]:checked');applyCondVis(k,c?c.value:'');}});
</script>
</body>
</html>
HTML;
    }

    private function getStylesheet(): string
    {
        return <<<'CSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #f0f2f5; color: #333; }
.header { background: #003087; color: #fff; padding: 20px 30px; display: flex; align-items: center; justify-content: space-between; }
.header h1 { font-size: 20px; font-weight: 600; }
.header .subtitle { font-size: 13px; opacity: 0.8; margin-top: 4px; }
.container { display: flex; max-width: 1400px; margin: 0 auto; min-height: calc(100vh - 70px); }
.sidebar { width: 280px; background: #fff; border-right: 1px solid #dde; padding: 16px 0; position: sticky; top: 0; height: 100vh; overflow-y: auto; flex-shrink: 0; }
.sidebar a { display: block; padding: 9px 20px; color: #003087; text-decoration: none; font-size: 13px; border-left: 3px solid transparent; transition: all 0.15s; }
.sidebar a:hover, .sidebar a.active { background: #e8f0fe; border-left-color: #003087; }
.main { flex: 1; padding: 24px 30px; min-width: 0; }
.section { background: #fff; border-radius: 6px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden; }
.section-header { padding: 14px 20px; font-size: 15px; font-weight: 600; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 10px; background: #fafbfc; border-bottom: 1px solid #eee; transition: background 0.15s; }
.section-header:hover { background: #f0f4f8; }
.section-header .arrow { display: inline-block; font-size: 11px; transition: transform 0.2s; color: #666; }
.section.open .section-header .arrow { transform: rotate(90deg); }
.section-body { display: none; padding: 0; }
.section.open .section-body { display: block; }
.field-row { display: flex; align-items: flex-start; padding: 10px 20px; border-bottom: 1px solid #f2f2f2; }
.field-row:last-child { border-bottom: none; }
.field-row:hover { background: #fafbfc; }
.field-name { width: 260px; flex-shrink: 0; font-size: 13px; color: #555; font-weight: 500; padding-right: 16px; padding-top: 6px; word-break: break-word; }
.field-control { flex: 1; min-width: 0; }
.field-control input[type="text"],
.field-control input[type="number"],
.field-control input[type="date"] { width: 100%; padding: 7px 10px; border: 1px solid #d0d5dd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; color: #333; background: #fff; transition: border-color 0.15s; }
.field-control input:focus,
.field-control textarea:focus,
.field-control select:focus { outline: none; border-color: #003087; box-shadow: 0 0 0 2px rgba(0,48,135,0.12); }
.field-control textarea { width: 100%; padding: 7px 10px; border: 1px solid #d0d5dd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; color: #333; background: #fff; resize: vertical; min-height: 60px; transition: border-color 0.15s; }
.field-control select { width: 100%; padding: 7px 10px; border: 1px solid #d0d5dd; border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif; color: #333; background: #fff; cursor: pointer; transition: border-color 0.15s; }
.field-control .checkbox-wrap { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.field-control .checkbox-wrap input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #003087; }
.field-control .checkbox-wrap label { font-size: 13px; color: #555; cursor: pointer; }
.field-control .radio-group { display: flex; gap: 16px; padding: 4px 0; flex-wrap: wrap; }
.field-control .radio-group label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #555; cursor: pointer; }
.field-control .radio-group input[type="radio"] { width: 16px; height: 16px; cursor: pointer; accent-color: #003087; }
.nested-group { margin: 0; }
.nested-group .group-title { font-size: 13px; font-weight: 600; color: #003087; padding: 10px 20px 6px 20px; background: #f7f9fc; border-bottom: 1px solid #eef1f5; }
.nested-group .field-row { padding-left: 36px; }
.nested-group .nested-group .field-row { padding-left: 52px; }
.nested-group .nested-group .group-title { padding-left: 36px; font-size: 12px; }
.badge { display: inline-block; font-size: 10px; padding: 2px 7px; border-radius: 10px; font-weight: 500; margin-left: 8px; vertical-align: middle; }
.badge.empty { background: #f0f0f0; color: #999; }
.badge.list-count { background: #e8f0fe; color: #003087; }
[data-cond-target].cond-hidden { display: none; }
.repeatable-group { border-left: 3px solid #003087; }
.repeatable-item { position: relative; border-bottom: 2px solid #e8f0fe; padding-bottom: 4px; margin-bottom: 2px; }
.repeatable-item:last-of-type { border-bottom: none; margin-bottom: 0; }
.btn-add-item { background: #003087; color: #fff; border: none; border-radius: 4px; padding: 4px 14px; font-size: 12px; cursor: pointer; margin-left: 10px; transition: background 0.15s; vertical-align: middle; }
.btn-add-item:hover { background: #004db3; }
.btn-remove-item { background: none; color: #dc3545; border: 1px solid #dc3545; border-radius: 4px; padding: 2px 10px; font-size: 11px; cursor: pointer; margin-left: 10px; transition: all 0.15s; vertical-align: middle; }
.btn-remove-item:hover { background: #dc3545; color: #fff; }
@media (max-width: 900px) {
  .container { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid #dde; display: flex; flex-wrap: wrap; padding: 8px; }
  .sidebar a { padding: 6px 12px; font-size: 12px; }
  .field-name { width: 160px; }
}
CSS;
    }

    private function getJavaScript(): string
    {
        return <<<'JS'
function toggleSection(header) {
  header.parentElement.classList.toggle('open');
}
document.querySelectorAll('.sidebar a').forEach(function(a) {
  a.addEventListener('click', function(e) {
    e.preventDefault();
    var id = this.getAttribute('href').substring(1);
    var section = document.getElementById(id);
    if (section) {
      section.classList.add('open');
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});
document.querySelectorAll('.section').forEach(function(s, i) {
  if (i === 0 || (i === 1 && s.querySelector('.field-row'))) s.classList.add('open');
});

var repeatableCounter = 0;
function addItem(btn) {
  repeatableCounter++;
  var group = btn.closest('.repeatable-group');
  var items = group.querySelectorAll(':scope > .repeatable-item');
  var lastItem = items[items.length - 1];
  if (!lastItem) return;

  var clone = lastItem.cloneNode(true);
  clone.querySelectorAll('input, textarea, select').forEach(function(el) {
    if (el.name) {
      el.name = el.name.replace(/\[\d+\]/, '[new_' + repeatableCounter + ']');
    }
    if (el.type === 'checkbox' || el.type === 'radio') {
      el.checked = false;
    } else if (el.tagName === 'SELECT') {
      el.selectedIndex = 0;
    } else {
      el.value = '';
    }
  });

  group.appendChild(clone);
  updateRepeatableGroup(group);
}

function removeItem(btn) {
  var item = btn.closest('.repeatable-item');
  var group = item.closest('.repeatable-group');
  var items = group.querySelectorAll(':scope > .repeatable-item');
  if (items.length <= 1) {
    alert('At least one item must remain.');
    return;
  }
  item.remove();
  updateRepeatableGroup(group);
}

function updateRepeatableGroup(group) {
  var items = group.querySelectorAll(':scope > .repeatable-item');
  items.forEach(function(item, i) {
    var label = item.querySelector('.item-label');
    if (label) label.textContent = 'Item ' + (i + 1);
  });
  var badge = group.querySelector(':scope > .group-title > .list-count');
  if (badge) badge.textContent = items.length + ' items';
}
JS;
    }
}
