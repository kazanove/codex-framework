<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CodeX Framework')</title>
    <style>
        :root { --primary: #007bff; --success: #28a745; --danger: #dc3545; --warning: #ffc107; --info: #17a2b8; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .navbar { background: #343a40; padding: 1rem 0; }
        .navbar-nav { display: flex; list-style: none; margin: 0; padding: 0; }
        .navbar-nav li { margin-right: 1.5rem; }
        .navbar-nav a { color: white; text-decoration: none; }
        .navbar-nav a:hover { text-decoration: underline; }
        .main { margin: 2rem 0; }
        .alert { padding: 1rem; margin: 1rem 0; border-radius: 0.375rem; color: white; }
        .alert-info { background: var(--info); }
        .alert-success { background: var(--success); }
        .alert-danger { background: var(--danger); }
        .card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin: 1rem 0; }
        .card-header { background: #f8f9fa; padding: 0.75rem 1.25rem; border-bottom: 1px solid #dee2e6; font-weight: bold; }
        .card-body { padding: 1.25rem; }
        .card-footer { background: #f8f9fa; padding: 0.75rem 1.25rem; border-top: 1px solid #dee2e6; }
        .breadcrumb { background: #e9ecef; padding: 0.75rem 1rem; border-radius: 0.375rem; margin: 1rem 0; }
        .breadcrumb-item + .breadcrumb-item::before { content: "»"; color: #6c757d; }
        .post { margin: 2rem 0; padding: 2rem; border-left: 4px solid var(--primary); }
        .post-title { color: var(--primary); margin-top: 0; }
        .post-meta { color: #6c757d; font-size: 0.9rem; margin-bottom: 1rem; }
        .btn { display: inline-block; background: var(--primary); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 0.25rem; }
        .btn:hover { background: #0056b3; }
        .text-center { text-align: center; }
        footer { text-align: center; padding: 2rem 0; color: #6c757d; border-top: 1px solid #dee2e6; margin-top: 3rem; }
    </style>
</head>
<body>

<div class="container">
    @if (flash('success'))
        @component('components.alert', ['type' => 'success', 'title' => 'Успех!'])
            {{ flash('success') }}
        @endcomponent
    @endif

    @if (flash('error'))
        @component('alert', ['type' => 'danger', 'title' => 'Ошибка!'])
            {{ flash('error') }}
        @endcomponent
    @endif

    @yield('content')
</div>

<footer class="container">
    <p>&copy; {{ date('Y') }} CodeX Framework. Все права защищены.</p>
</footer>

@yield('scripts')
</body>
</html>