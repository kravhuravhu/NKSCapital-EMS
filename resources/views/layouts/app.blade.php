<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'NKS Capital EMS') }}</title>

        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <x-nks.shell :title="$title ?? null" :breadcrumb="$breadcrumb ?? null">
            {{ $slot }}
        </x-nks.shell>

        @livewireScripts
    </body>
</html>
