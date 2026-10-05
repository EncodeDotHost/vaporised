<h1>Your secret</h1>

<label for="secret-value">Secret</label>
<textarea id="secret-value" rows="5" readonly spellcheck="false"><?= e($secret) ?></textarea>
<p class="row">
    <button type="button" class="secondary" data-copy="secret-value">Copy</button>
</p>

<?php if ($viewsRemaining < 1): ?>
    <p class="alert">This secret is now deleted. Save it before you close this page.</p>
<?php else: ?>
    <p class="hint">Views that remain: <?= e($viewsRemaining) ?>.</p>
<?php endif; ?>
