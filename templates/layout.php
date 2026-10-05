<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="referrer" content="no-referrer">
    <title><?= e($title) ?> – Vaporised</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <header>
        <a class="brand" href="/">
            <svg class="logo" viewBox="0 0 32 32" width="32" height="32" aria-hidden="true" focusable="false">
                <rect class="logo-bg" width="32" height="32" rx="8"/>
                <path class="logo-mark" d="M10 25c-2.5-2.5 2.5-4.5 0-7s2.5-4.5 0-7"/>
                <path class="logo-mark" d="M16 25c-2.5-2.5 2.5-4.5 0-7s2.5-4.5 0-7s2.5-2.5 0-4"/>
                <path class="logo-mark" d="M22 25c-2.5-2.5 2.5-4.5 0-7s2.5-4.5 0-7"/>
            </svg>
            <span>Vaporised</span>
        </a>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>No cookies. No identifiable information. When a secret expires, we purge it and keep no archives.</p>
        <nav aria-label="Footer">
            <a href="/help">Help</a>
            <a href="/privacy">Privacy policy</a>
        </nav>
        <p class="credit">An <a href="https://www.encodegroup.co.uk/">Encode Group</a> project</p>
    </footer>
    <script src="/assets/app.js" defer></script>
</body>
</html>
