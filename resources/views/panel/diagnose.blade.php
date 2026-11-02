<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'LinkStack') }} - Diagnose</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; color: #222; }
        h1 { font-size: 1.5rem; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 8px 16px; }
        dt { font-weight: 600; }
        dd { margin: 0; }
    </style>
</head>
<body>
    <h1>{{ config('app.name', 'LinkStack') }} diagnose</h1>
    <p>The application is running. No further diagnostics are available on this deployment.</p>
    <dl>
        <dt>Environment</dt><dd>{{ config('app.env') }}</dd>
        <dt>PHP version</dt><dd>{{ PHP_VERSION }}</dd>
        <dt>Laravel version</dt><dd>{{ app()->version() }}</dd>
        <dt>Database driver</dt><dd>{{ config('database.default') }}</dd>
    </dl>
</body>
</html>
