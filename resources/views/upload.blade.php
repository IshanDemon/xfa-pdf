@extends('xfa-pdf::layout')

@section('title', 'Upload XFA PDF')

@section('content')
<h2 style="font-size:18px;font-weight:600;margin-bottom:20px;">Upload XFA PDF</h2>

<div class="card" style="max-width:600px;">
  <form method="POST" action="{{ route('xfa-pdf.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
      <label for="pdf_file">PDF File</label>
      <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required>
    </div>
    <div class="form-group">
      <label for="name">Document Name (optional)</label>
      <input type="text" name="name" id="name" placeholder="Auto-detected from filename">
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
    <a href="{{ route('xfa-pdf.index') }}" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
  </form>
</div>
@endsection
