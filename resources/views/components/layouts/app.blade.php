@props(['title' => 'Nana Comanda', 'bodyClass' => 'bg-nana-cream'])
@php(\Livewire\Livewire::forceAssetInjection())
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#D62828">
    <title>{{ $title }} · Nana Comanda</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh font-sans font-medium text-nana-ink antialiased {{ $bodyClass }}">
    {{ $slot }}
    <x-toasts />
</body>
</html>
