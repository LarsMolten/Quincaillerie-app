{{--
    Squelette commun à toutes les pages : <head>, thème sans flash, ressources Vite,
    conteneur de toasts et messages flash de Laravel.
    Utilisation : @extends('layouts.base') puis @section('titre') et @section('contenu').
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="preference-theme-url" content="{{ route('preferences.theme') }}">
    @endauth
    {{-- PWA : application installable (manifest + service worker limité aux ressources statiques) --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icones/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    <meta name="theme-color" content="#f9fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0c1016" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ trim($__env->yieldContent('titre')) ? trim($__env->yieldContent('titre')).' · ' : '' }}{{ config('app.name') }}</title>

    @include('partials.script-theme')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh bg-fond font-sans text-texte antialiased">
    @yield('contenu')

    <x-toast />

    @php
        // Messages flash (contrôleurs) et erreurs de validation, affichés en toasts
        $toasts = collect(['succes', 'erreur', 'info', 'alerte'])
            ->filter(fn (string $type) => session()->has($type))
            ->map(fn (string $type) => ['type' => $type, 'message' => session($type)])
            ->values();

        if (session('status')) {
            $toasts->push(['type' => 'info', 'message' => session('status')]);
        }

        if (isset($errors) && $errors->any()) {
            $toasts->push(['type' => 'erreur', 'message' => 'Le formulaire contient des erreurs : vérifiez les champs signalés.']);
        }
    @endphp
    {{-- JSON brut (sans HEX_QUOT, invalide hors chaîne) ; HEX_TAG empêche de fermer la balise script --}}
    <script type="application/json" id="toasts-flash">{!! json_encode($toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

    @stack('scripts')
</body>
</html>
