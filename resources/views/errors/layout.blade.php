{{--
    Error page shell.

    Intentionally self-contained: no @vite, no session reads, no queries. A 500
    or 503 can occur when the asset manifest is missing or the database is
    unreachable, and an error page that depends on those would fail to render
    and mask the real fault. Styles are inline for the same reason.

    Never echo exception messages, stack traces or SQL here. The message shown
    is written per status code, not taken from the exception.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --fg: #1a1d21;
            --muted: #5b6b7c;
            --bg: #f6f7f9;
            --card: #ffffff;
            --border: #dfe3e8;
            --accent: #1f5fa9;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --fg: #e8eaed;
                --muted: #9aa5b1;
                --bg: #15171a;
                --card: #1e2125;
                --border: #32363b;
                --accent: #6ea8e8;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg);
            color: var(--fg);
            font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                  "Helvetica Neue", Arial, sans-serif;
        }

        .panel {
            width: 100%;
            max-width: 29rem;
            padding: 2.25rem 1.75rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            text-align: center;
        }

        .code {
            margin: 0 0 0.5rem;
            font-size: 2.75rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
        }

        p {
            margin: 0 0 1.5rem;
            color: var(--muted);
        }

        a {
            display: inline-block;
            padding: 0.5rem 1rem;
            color: #fff;
            background: var(--accent);
            border-radius: 0.375rem;
            font-weight: 500;
            text-decoration: none;
        }

        a:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <main class="panel">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        <a href="{{ url('/') }}">{{ __('Back to safety') }}</a>
    </main>
</body>
</html>
