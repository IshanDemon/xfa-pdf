@extends('xfa-pdf::layout')

@section('title', 'XFA Documents')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h2 style="font-size:18px;font-weight:600;">Documents</h2>
  <a href="{{ route('xfa-pdf.create') }}" class="btn btn-primary">Upload PDF</a>
</div>

@if($documents->isEmpty())
  <div class="empty-state">
    <h3>No documents yet</h3>
    <p>Upload an XFA PDF to get started.</p>
    <a href="{{ route('xfa-pdf.create') }}" class="btn btn-primary">Upload PDF</a>
  </div>
@else
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Original File</th>
        <th>Sections</th>
        <th>Uploaded</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($documents as $doc)
      <tr>
        <td><strong>{{ $doc->name }}</strong></td>
        <td>{{ $doc->original_filename }}</td>
        <td>{{ $doc->metadata['section_count'] ?? '-' }}</td>
        <td>{{ $doc->created_at->diffForHumans() }}</td>
        <td>
          <a href="{{ route('xfa-pdf.show', $doc->id) }}" class="btn btn-sm btn-secondary">Preview</a>
          <a href="{{ route('xfa-pdf.edit', $doc->id) }}" class="btn btn-sm btn-primary">Edit</a>
          <form method="POST" action="{{ route('xfa-pdf.destroy', $doc->id) }}" style="display:inline" onsubmit="return confirm('Delete this document?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif
@endsection
