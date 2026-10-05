<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= e($title) ?> – Vaporised</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <header>
        <a class="brand" href="/">Vaporised</a>
    </header>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>No cookies. No identifiable information. When a secret expires, we purge it and keep no archives.</p>
    </footer>
    <script src="/assets/app.js" defer></script>
</body>
</html>
