<style>
    :root {
        color-scheme: light;
        --echo-primary: #0f5f4a;
        --echo-primary-dark: #0a3f32;
        --echo-accent: #2fae7b;
        --echo-background: #eef3ee;
        --echo-card: #fffdf7;
        --echo-text: #162018;
        --echo-muted: #68726b;
        --echo-border: #d9e2da;
        --echo-panel: #f8fbf7;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-height: 100vh;
        background: var(--echo-background);
        color: var(--echo-text);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    a {
        color: inherit;
    }

    .gateway-shell,
    .signed-out-shell {
        width: min(100% - 2rem, 68rem);
        margin: 0 auto;
        padding: 2rem 0;
    }

    .gateway-header,
    .signed-out-panel {
        border: 1px solid var(--echo-border);
        border-radius: 8px;
        background: var(--echo-card);
        box-shadow: 0 12px 32px rgba(10, 63, 50, 0.06);
    }

    .gateway-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.2rem;
    }

    .brand-mark,
    .signed-out-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 8px;
        background: var(--echo-primary);
        color: #fffdf7;
        font-weight: 800;
    }

    .signed-out-icon svg,
    .portal-icon svg {
        width: 1.35rem;
        height: 1.35rem;
        fill: currentColor;
    }

    .eyebrow,
    .portal-name,
    .section-heading p {
        margin: 0;
        color: var(--echo-muted);
        font-size: 0.8125rem;
        line-height: 1.35;
    }

    .eyebrow {
        font-weight: 700;
        text-transform: uppercase;
    }

    h1,
    h2,
    p {
        margin-top: 0;
    }

    h1 {
        margin-bottom: 0;
        font-size: 1.5rem;
        line-height: 1.2;
    }

    h2 {
        margin-bottom: 0;
        font-size: 1rem;
        line-height: 1.3;
    }

    .gateway-section {
        margin-top: 1.25rem;
    }

    .section-heading {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.6rem;
    }

    .portal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(13.5rem, 1fr));
        gap: 0.75rem;
    }

    .portal-grid--system {
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
    }

    .portal-card {
        display: flex;
        min-height: 7rem;
        gap: 0.85rem;
        padding: 1rem;
        border: 1px solid var(--echo-border);
        border-radius: 8px;
        background: var(--echo-card);
        text-decoration: none;
        box-shadow: 0 10px 24px rgba(10, 63, 50, 0.05);
        transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    .portal-card--system {
        background: var(--echo-panel);
        border-color: color-mix(in srgb, var(--echo-primary) 20%, var(--echo-border));
    }

    .portal-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 8px;
        background: color-mix(in srgb, var(--echo-primary) 10%, white);
        color: var(--echo-primary);
    }

    .portal-copy {
        display: grid;
        gap: 0.2rem;
    }

    .portal-title {
        font-size: 1rem;
        line-height: 1.3;
        font-weight: 700;
    }

    .portal-description,
    .signed-out-panel p {
        margin: 0;
        color: var(--echo-muted);
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .portal-card:hover,
    .portal-card:focus-visible,
    .public-link:hover,
    .public-link:focus-visible,
    .secondary-link:hover,
    .secondary-link:focus-visible {
        border-color: var(--echo-primary);
        box-shadow: 0 12px 26px rgba(10, 63, 50, 0.1);
    }

    .portal-card:hover {
        transform: translateY(-1px);
    }

    .portal-card:focus-visible,
    .public-link:focus-visible,
    .secondary-link:focus-visible {
        outline: 3px solid color-mix(in srgb, var(--echo-accent) 36%, white);
        outline-offset: 3px;
    }

    .gateway-footer,
    .signed-out-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1.25rem;
    }

    .public-link,
    .primary-link,
    .secondary-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.65rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 700;
        text-decoration: none;
    }

    .public-link,
    .primary-link {
        padding: 0 1rem;
        border: 1px solid var(--echo-primary);
        background: var(--echo-primary);
        color: #fffdf7;
    }

    .secondary-link {
        color: var(--echo-primary-dark);
    }

    .signed-out-shell {
        min-height: 100vh;
        display: grid;
        place-items: center;
    }

    .signed-out-panel {
        width: min(100%, 32rem);
        padding: 1.5rem;
    }

    .signed-out-panel h1 {
        margin: 1rem 0 0.35rem;
    }

    @media (max-width: 42rem) {
        .gateway-shell,
        .signed-out-shell {
            width: min(100% - 1rem, 68rem);
            padding: 0.75rem 0;
        }

        .gateway-header {
            align-items: flex-start;
        }

        h1 {
            font-size: 1.25rem;
        }

        .portal-grid,
        .portal-grid--system {
            grid-template-columns: 1fr;
        }
    }
</style>
