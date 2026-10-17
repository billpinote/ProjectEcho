@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Project Echo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/flightplan.css') }}">
</head>
<body class="min-h-screen bg-echo-background text-echo-text-primary">
    @include('flightplan.partials.navbar', ['activeNav' => 'manifest'])
    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>
</body>
</html>
