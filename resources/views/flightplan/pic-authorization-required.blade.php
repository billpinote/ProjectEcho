<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $noticeHeading ?? 'PIC Authorization Required' }} - Project Echo</title>
    @include('partials.access-gateway-styles')
</head>
<body>
    <main class="signed-out-shell">
        <section class="signed-out-panel" aria-labelledby="pic-authorization-required-heading">
            <span class="signed-out-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M12 3 2.8 20h18.4L12 3zm0 5.2 1 6.1h-2l1-6.1zm-1 8h2v2h-2v-2z"/>
                </svg>
            </span>
            <h1 id="pic-authorization-required-heading">{{ $noticeHeading ?? 'PIC Authorization Required' }}</h1>
            <p>{{ $noticeMessage ?? 'This flight plan can only be reviewed and acted on by a verified PPL, CPL, or ATPL holder authorized to act as PIC.' }}</p>
            <div class="signed-out-actions">
                <button id="close-preview-tab" class="primary-link" type="button" style="border: 0; cursor: pointer; font: inherit;">Close</button>
            </div>
            <p id="close-preview-fallback" role="status" hidden style="margin-top: 1rem;">This tab could not be closed automatically. You can close it from your browser.</p>
        </section>
    </main>
    <script>
        (() => {
            const closeButton = document.getElementById('close-preview-tab');
            const fallback = document.getElementById('close-preview-fallback');

            closeButton?.addEventListener('click', () => {
                try {
                    window.close();
                } catch (error) {
                    // Some browsers refuse to close tabs they did not open by script.
                }

                window.setTimeout(() => {
                    if (fallback && !window.closed) {
                        fallback.hidden = false;
                    }
                }, 150);
            });
        })();
    </script>
</body>
</html>
