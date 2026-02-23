@extends('xfa-pdf::layout')

@section('title', 'Edit: ' . $document->name)

@section('styles')
<style>
  .edit-container { display: flex; gap: 0; min-height: calc(100vh - 130px); margin: -24px -30px; }
  .edit-sidebar { width: 260px; background: #fff; border-right: 1px solid #dde; padding: 16px 0; position: sticky; top: 0; height: calc(100vh - 70px); overflow-y: auto; flex-shrink: 0; }
  .edit-sidebar a { display: block; padding: 8px 20px; color: #003087; text-decoration: none; font-size: 12px; border-left: 3px solid transparent; transition: all 0.15s; }
  .edit-sidebar a:hover, .edit-sidebar a.active { background: #e8f0fe; border-left-color: #003087; }
  .edit-main { flex: 1; padding: 24px 30px; min-width: 0; }
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
  .repeatable-group { border-left: 3px solid #003087; }
  .repeatable-item { position: relative; border-bottom: 2px solid #e8f0fe; padding-bottom: 4px; margin-bottom: 2px; }
  .repeatable-item:last-of-type { border-bottom: none; margin-bottom: 0; }
  .btn-add-item { background: #003087; color: #fff; border: none; border-radius: 4px; padding: 4px 14px; font-size: 12px; cursor: pointer; margin-left: 10px; transition: background 0.15s; vertical-align: middle; }
  .btn-add-item:hover { background: #004db3; }
  .btn-remove-item { background: none; color: #dc3545; border: 1px solid #dc3545; border-radius: 4px; padding: 2px 10px; font-size: 11px; cursor: pointer; margin-left: 10px; transition: all 0.15s; vertical-align: middle; }
  .btn-remove-item:hover { background: #dc3545; color: #fff; }
  .save-bar { position: sticky; bottom: 0; background: #fff; padding: 16px 30px; border-top: 2px solid #003087; display: flex; justify-content: flex-end; gap: 10px; box-shadow: 0 -2px 8px rgba(0,0,0,0.1); z-index: 10; }
  [data-cond-target].cond-hidden { display: none; }
</style>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2 style="font-size:18px;font-weight:600;">Editing: {{ $document->name }}</h2>
  <div>
    <a href="{{ route('xfa-pdf.show', $document->id) }}" class="btn btn-secondary">Preview</a>
    <a href="{{ route('xfa-pdf.index') }}" class="btn btn-secondary" style="margin-left:6px;">Back</a>
  </div>
</div>

<form method="POST" action="{{ route('xfa-pdf.update', $document->id) }}">
  @csrf
  @method('PUT')

  <div class="edit-container">
    <nav class="edit-sidebar">
      @foreach($sections as $sectionKey)
        <a href="#section-{{ $sectionKey }}" onclick="openSection('section-{{ $sectionKey }}')">
          {{ $sectionLabels[$sectionKey] ?? $sectionKey }}
        </a>
      @endforeach
    </nav>
    <div class="edit-main">
      @foreach($allData as $sectionKey => $fields)
        @php
          $label = $sectionLabels[$sectionKey] ?? $sectionKey;
          $isEmpty = empty($fields);
        @endphp
        <div class="section" id="section-{{ $sectionKey }}">
          <div class="section-header" onclick="this.parentElement.classList.toggle('open')">
            <span class="arrow">&#9654;</span> {{ $label }}
            @if($isEmpty)<span class="badge empty">Empty</span>@endif
          </div>
          <div class="section-body">
            @include('xfa-pdf::partials.fields-editable', [
              'fields' => $fields,
              'parentPath' => $sectionKey,
              'fieldMeta' => $fieldMeta,
              'repeatables' => $repeatables,
              'conditionalRules' => $conditionalRules ?? [],
            ])
          </div>
        </div>
      @endforeach

      <div class="save-bar">
        <a href="{{ route('xfa-pdf.show', $document->id) }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" style="background:#28a745;border-color:#28a745;" onclick="this.form.action='{{ route('xfa-pdf.download', $document->id) }}';this.form.querySelector('input[name=_method]').value='POST';" >Generate &amp; Download PDF</button>
        <button type="submit" class="btn btn-primary" onclick="this.form.action='{{ route('xfa-pdf.update', $document->id) }}';this.form.querySelector('input[name=_method]').value='PUT';">Save Changes</button>
      </div>
    </div>
  </div>
</form>
@endsection

@section('scripts')
<script>
function openSection(id) {
  var section = document.getElementById(id);
  if (section) {
    section.classList.add('open');
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

document.querySelectorAll('.section').forEach(function(s, i) {
  if (i < 2) s.classList.add('open');
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

// Conditional field visibility
var condRules = @json($conditionalRules ?? []);

function applyConditionalVisibility(triggerKey, value) {
  var rule = condRules[triggerKey];
  if (!rule) return;
  var targets = rule.targets || [];
  var visibleKeys = rule.visibleWhen[value] || rule.visibleWhen['_default'] || [];

  targets.forEach(function(targetKey) {
    var els = document.querySelectorAll('[data-cond-target="' + targetKey + '"]');
    els.forEach(function(el) {
      if (visibleKeys.indexOf(targetKey) !== -1) {
        el.classList.remove('cond-hidden');
      } else {
        el.classList.add('cond-hidden');
      }
    });
  });
}

// Attach listeners to trigger elements
document.querySelectorAll('[data-cond-trigger]').forEach(function(el) {
  var triggerKey = el.getAttribute('data-cond-trigger');

  if (el.tagName === 'SELECT') {
    el.addEventListener('change', function() {
      applyConditionalVisibility(triggerKey, this.value);
    });
    // Apply initial state
    applyConditionalVisibility(triggerKey, el.value);
  } else {
    // Radio group container
    var radios = el.querySelectorAll('input[type="radio"]');
    radios.forEach(function(radio) {
      radio.addEventListener('change', function() {
        applyConditionalVisibility(triggerKey, this.value);
      });
    });
    // Apply initial state from checked radio
    var checked = el.querySelector('input[type="radio"]:checked');
    applyConditionalVisibility(triggerKey, checked ? checked.value : '');
  }
});
</script>
@endsection
