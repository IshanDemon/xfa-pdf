@extends('xfa-pdf::layout')

@section('title', 'Preview: ' . $document->name)

@section('styles')
<style>
  .preview-container { display: flex; gap: 0; min-height: calc(100vh - 130px); margin: -24px -30px; }
  .preview-sidebar { width: 260px; background: #fff; border-right: 1px solid #dde; padding: 16px 0; position: sticky; top: 0; height: calc(100vh - 70px); overflow-y: auto; flex-shrink: 0; }
  .preview-sidebar a { display: block; padding: 8px 20px; color: #003087; text-decoration: none; font-size: 12px; border-left: 3px solid transparent; transition: all 0.15s; }
  .preview-sidebar a:hover, .preview-sidebar a.active { background: #e8f0fe; border-left-color: #003087; }
  .preview-main { flex: 1; padding: 24px 30px; min-width: 0; }
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
  .field-value { flex: 1; font-size: 13px; color: #333; padding: 6px 0; min-width: 0; word-break: break-word; }
  .field-value.empty { color: #ccc; font-style: italic; }
  .nested-group { margin: 0; }
  .nested-group .group-title { font-size: 13px; font-weight: 600; color: #003087; padding: 10px 20px 6px 20px; background: #f7f9fc; border-bottom: 1px solid #eef1f5; }
  .nested-group .field-row { padding-left: 36px; }
  .nested-group .nested-group .field-row { padding-left: 52px; }
  .nested-group .nested-group .group-title { padding-left: 36px; font-size: 12px; }
  .badge { display: inline-block; font-size: 10px; padding: 2px 7px; border-radius: 10px; font-weight: 500; margin-left: 8px; vertical-align: middle; }
  .badge.empty { background: #f0f0f0; color: #999; }
  .badge.list-count { background: #e8f0fe; color: #003087; }
</style>
@endsection

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2 style="font-size:18px;font-weight:600;">{{ $document->name }}</h2>
  <div>
    <a href="{{ route('xfa-pdf.edit', $document->id) }}" class="btn btn-primary">Edit</a>
    <a href="{{ route('xfa-pdf.index') }}" class="btn btn-secondary" style="margin-left:6px;">Back</a>
  </div>
</div>

<div class="preview-container">
  <nav class="preview-sidebar">
    @foreach($sections as $sectionKey)
      <a href="#section-{{ $sectionKey }}" onclick="openSection('section-{{ $sectionKey }}')">
        {{ $sectionLabels[$sectionKey] ?? $sectionKey }}
      </a>
    @endforeach
  </nav>
  <div class="preview-main">
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
          @include('xfa-pdf::partials.fields-readonly', ['fields' => $fields, 'parentPath' => $sectionKey])
        </div>
      </div>
    @endforeach
  </div>
</div>
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
// Open first non-empty section
document.querySelectorAll('.section').forEach(function(s, i) {
  if (i < 2) s.classList.add('open');
});
</script>
@endsection
