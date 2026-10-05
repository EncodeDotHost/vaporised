<h1>Share a secret</h1>
<p class="lead">
    Vaporised helps you send sensitive information through email. Send the username by email,
    save the password here, and send only the link. The information is never all in one location.
</p>

<?php if ($error !== null): ?>
    <p class="alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<noscript><p class="alert">JavaScript is necessary to create a secret.</p></noscript>

<form id="create-form" method="post" action="/" autocomplete="off">
    <label for="secret">Secret</label>
    <textarea id="secret" name="secret" rows="5" maxlength="<?= e($maxSecretChars) ?>" required spellcheck="false"><?= e($old['secret']) ?></textarea>
    <p class="row">
        <button type="button" class="secondary" id="generate">Generate a password</button>
    </p>

    <div class="grid">
        <div>
            <label for="ttl">Delete after</label>
            <select id="ttl" name="ttl">
                <?php foreach ($ttlChoices as $seconds => $label): ?>
                    <option value="<?= e($seconds) ?>"<?= $seconds === $old['ttl'] ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="views">Maximum views</label>
            <input id="views" name="views" type="number" min="1" max="<?= e($maxViews) ?>" value="<?= e($old['views']) ?>" required>
        </div>
    </div>

    <label for="passphrase">Passphrase (optional)</label>
    <input id="passphrase" name="passphrase" type="password" maxlength="<?= e($maxPassphraseChars) ?>" autocomplete="new-password">
    <p class="hint">The recipient must enter this passphrase to read the secret. Send it by a different channel.</p>

    <input type="hidden" name="pow_challenge" id="pow-challenge">
    <input type="hidden" name="pow_nonce" id="pow-nonce">

    <p class="row">
        <button type="submit" id="create-submit">Save the secret</button>
        <span id="create-status" class="hint" role="status"></span>
    </p>
</form>
