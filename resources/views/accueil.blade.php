<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    {{-- Page provisoire : remplacée par le tableau de bord lors des prompts suivants --}}
    <main class="mx-auto max-w-xl p-6">
        <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-2">Installation réussie. Exemple de montant : <span class="chiffres">{{ format_ar(35000) }}</span></p>
    </main>
</body>
</html>
