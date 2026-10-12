<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="referrer" content="no-referrer">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
<?php if ($canonicalUrl !== null): ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Vaporised">
    <meta property="og:locale" content="en_GB">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <meta property="og:image" content="<?= e($baseUrl) ?>/assets/og-image.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Two people look at a web page with a form, a tick, and a cross.">
    <meta name="twitter:card" content="summary_large_image">
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
        <div class="footer-bar">
            <nav aria-label="Footer">
                <a href="/help">Help</a>
                <a href="/privacy">Privacy policy</a>
            </nav>
            <p class="credit">An <a href="https://www.encodegroup.co.uk/">Encode Group</a> project</p>
        </div>
    </footer>
    <script src="/assets/crypto.js" defer></script>
    <script src="/assets/app.js" defer></script>
</body>
</html>
