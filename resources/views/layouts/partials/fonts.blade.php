{{--
    Webfonts, included by every layout that renders the compiled stylesheet.

    Inter sets interface and display text; IBM Plex Mono sets anything that is
    data: reference numbers, statuses, dates and column heads. Both families are
    named in `resources/css/app.scss`, so a layout that renders that stylesheet
    without including this partial silently falls back to system faces and looks
    like a different product.

    Kept as one partial so the three shells cannot drift apart. The error pages
    deliberately do not include it: they are self-contained with inline CSS and
    must render with no external request of any kind.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap">
