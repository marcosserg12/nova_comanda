@props(['title' => 'Nana Comanda', 'bodyClass' => 'bg-stone-100'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Nana Comanda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-nana-ink antialiased {{ $bodyClass }}">
    {{ $slot }}
</body>
</html>
