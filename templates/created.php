<h1>Secret saved</h1>
<p class="lead">
    The secret is deleted after <?= e($ttlLabel) ?> or after <?= e($views) ?> <?= $views === 1 ? 'view' : 'views' ?>.
    We show these links only one time.
</p>

<label for="share-url">Share link (send this to the recipient)</label>
<div class="copy">
    <input id="share-url" type="text" readonly value="<?= e($shareUrl) ?>">
    <button type="button" class="secondary" data-copy="share-url">Copy</button>
</div>
<?php if ($hasPassphrase): ?>
    <p class="hint">The recipient also needs the passphrase. Send it by a different channel.</p>
<?php endif; ?>

<label for="delete-url">Delete link (keep this private)</label>
<div class="copy">
    <input id="delete-url" type="text" readonly value="<?= e($deleteUrl) ?>">
    <button type="button" class="secondary" data-copy="delete-url">Copy</button>
</div>
<p class="hint">Use the delete link to delete the secret before it expires.</p>

<p><a href="/">Share another secret</a></p>
