<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'XFA PDF Manager')</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; background: #f0f2f5; color: #333; min-height: 100vh; }
  .header { background: #003087; color: #fff; padding: 16px 30px; display: flex; align-items: center; justify-content: space-between; }
  .header h1 { font-size: 18px; font-weight: 600; }
  .header h1 a { color: #fff; text-decoration: none; }
  .header .subtitle { font-size: 12px; opacity: 0.8; margin-top: 2px; }
  .header-nav { display: flex; gap: 16px; }
  .header-nav a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; padding: 4px 10px; border-radius: 4px; transition: all 0.15s; }
  .header-nav a:hover { background: rgba(255,255,255,0.15); color: #fff; }
  .content { max-width: 1200px; margin: 0 auto; padding: 24px 30px; }
  .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
  .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
  .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  .btn { display: inline-block; padding: 8px 18px; border-radius: 4px; font-size: 13px; text-decoration: none; cursor: pointer; border: none; transition: all 0.15s; font-family: Arial, sans-serif; }
  .btn-primary { background: #003087; color: #fff; }
  .btn-primary:hover { background: #004db3; }
  .btn-secondary { background: #6c757d; color: #fff; }
  .btn-secondary:hover { background: #5a6268; }
  .btn-danger { background: #dc3545; color: #fff; }
  .btn-danger:hover { background: #c82333; }
  .btn-sm { padding: 4px 12px; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
  table th { text-align: left; padding: 12px 16px; background: #fafbfc; border-bottom: 2px solid #eee; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
  table td { padding: 12px 16px; border-bottom: 1px solid #f2f2f2; font-size: 13px; }
  table tr:hover td { background: #fafbfc; }
  .card { background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 24px; margin-bottom: 16px; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
  .form-group input[type="text"],
  .form-group input[type="file"] { width: 100%; padding: 8px 12px; border: 1px solid #d0d5dd; border-radius: 4px; font-size: 13px; }
  .form-group input:focus { outline: none; border-color: #003087; box-shadow: 0 0 0 2px rgba(0,48,135,0.12); }
  .empty-state { text-align: center; padding: 60px 20px; color: #999; }
  .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #666; }
  .empty-state p { font-size: 13px; margin-bottom: 20px; }
</style>
@yield('styles')
</head>
<body>
<div class="header">
  <div>
    <h1><a href="{{ route('xfa-pdf.index') }}">XFA PDF Manager</a></h1>
    <div class="subtitle">xfa/pdf package</div>
  </div>
  <nav class="header-nav">
    <a href="{{ route('xfa-pdf.index') }}">Documents</a>
    <a href="{{ route('xfa-pdf.create') }}">Upload</a>
  </nav>
</div>
<div class="content">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif
  @yield('content')
</div>
@yield('scripts')
</body>
</html>
