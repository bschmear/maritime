<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.pwa-head')

        
        <!-- Material Design Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        <!-- Scripts -->
        {{-- {{ dd($page['component']) }} --}}
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        @if(isset($page['props']['meta']))

            {{-- Title --}}
            @if(!empty($page['props']['meta']['title']))
                <title>{{ $page['props']['meta']['title'] }}</title>
            @endif

            {{-- Basic Meta --}}
            @if(!empty($page['props']['meta']['description']))
                <meta name="description" content="{{ $page['props']['meta']['description'] }}">
            @endif

            @if(!empty($page['props']['meta']['author']))
                <meta name="author" content="{{ $page['props']['meta']['author'] }}">
            @endif

            @if(!empty($page['props']['meta']['robots']))
                <meta name="robots" content="{{ $page['props']['meta']['robots'] }}">
            @endif

            {{-- Canonical --}}
            @if(!empty($page['props']['meta']['canonical']))
                <link rel="canonical" href="{{ $page['props']['meta']['canonical'] }}">
            @endif

            {{-- Open Graph --}}
            @if(!empty($page['props']['meta']['type']))
                <meta property="og:type" content="{{ $page['props']['meta']['type'] }}">
            @endif

            @if(!empty($page['props']['meta']['site_name']))
                <meta property="og:site_name" content="{{ $page['props']['meta']['site_name'] }}">
            @endif

            @if(!empty($page['props']['meta']['title']))
                <meta property="og:title" content="{{ $page['props']['meta']['title'] }}">
            @endif

            @if(!empty($page['props']['meta']['description']))
                <meta property="og:description" content="{{ $page['props']['meta']['description'] }}">
            @endif

            @if(!empty($page['props']['meta']['url']))
                <meta property="og:url" content="{{ $page['props']['meta']['url'] }}">
            @endif

            @if(!empty($page['props']['meta']['image']))
                <meta property="og:image" content="{{ $page['props']['meta']['image'] }}">
            @endif

            {{-- Twitter --}}
            @if(!empty($page['props']['meta']['image']))
                <meta name="twitter:card" content="summary_large_image">
            @else
                <meta name="twitter:card" content="summary">
            @endif

            @if(!empty($page['props']['meta']['title']))
                <meta name="twitter:title" content="{{ $page['props']['meta']['title'] }}">
            @endif

            @if(!empty($page['props']['meta']['description']))
                <meta name="twitter:description" content="{{ $page['props']['meta']['description'] }}">
            @endif

            @if(!empty($page['props']['meta']['image']))
                <meta name="twitter:image" content="{{ $page['props']['meta']['image'] }}">
            @endif

            {{-- Structured Data / Schema --}}


        @endif
        @if(!empty($page['props']['meta']['schema']))
            <script type="application/ld+json">
                {!! json_encode($page['props']['meta']['schema'], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}
            </script>
        @endif

        @if(isset($page['props']['schemaData']))
        <script type="application/ld+json">{!! json_encode($page['props']['schemaData']) !!}</script>
        @endif


    </head>
    <body class="font-sans antialiased app dark:bg-gray-700">
        @inertia
    </body>
</html>
