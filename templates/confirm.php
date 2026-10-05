<h1>A secret is ready for you</h1>
<p class="lead">
    The number of views is limited. Press the button only when you are ready to read and save the secret.
</p>

<?php if ($error !== null): ?>
    <p class="alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?php if ($hasPassphrase): ?>
        <label for="passphrase">Passphrase</label>
        <input id="passphrase" name="passphrase" type="password" maxlength="<?= e($maxPassphraseChars) ?>" autocomplete="off" required autofocus>
        <p class="hint">Too many incorrect attempts delete the secret.</p>
    <?php endif; ?>
    <p class="row">
        <button type="submit">Reveal the secret</button>
    </p>
</form>
