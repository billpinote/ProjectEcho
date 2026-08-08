<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signed Out - Project Echo</title>
    @include('partials.access-gateway-styles')
</head>
<body>
    <main class="signed-out-shell">
        <section class="signed-out-panel" aria-labelledby="signed-out-heading">
            <span class="signed-out-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M9.7 16.6L4.8 11.7l1.4-1.4 3.5 3.5 8.1-8.1 1.4 1.4-9.5 9.5z"/>
                </svg>
            </span>
            <h1 id="signed-out-heading">You have been signed out</h1>
            <p>Your session has ended. Choose another portal or continue to the public flight plan form.</p>
            <div class="signed-out-actions">
                <a class="primary-link" href="{{ route('gateway') }}">Choose another portal</a>
                <a class="secondary-link" href="{{ route('flightplan') }}">Public Flight Plan</a>
            </div>
        </section>
    </main>
</body>
</html>
