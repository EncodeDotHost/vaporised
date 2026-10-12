<section id="create">
    <h1>Share a secret</h1>
    <p class="lead">
        Vaporised helps you send sensitive information through email. Send the username by email,
        save the password here, and send only the link. The information is never all in one location.
    </p>

    <p class="alert" id="create-error" role="alert" hidden></p>

    <noscript><p class="alert">JavaScript is necessary to create a secret. Your browser encrypts the secret before it leaves your device.</p></noscript>

    <form id="create-form" autocomplete="off">
        <label for="secret">Secret</label>
        <textarea id="secret" name="secret" rows="5" maxlength="<?= e($maxSecretChars) ?>" required spellcheck="false"></textarea>
        <p class="row">
            <button type="button" class="secondary" id="generate">Generate a password</button>
        </p>

        <div class="grid">
            <div>
                <label for="ttl">Delete after</label>
                <select id="ttl" name="ttl">
                    <?php foreach ($ttlChoices as $seconds => $label): ?>
                        <option value="<?= e($seconds) ?>"<?= $seconds === 86400 ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="views">Maximum views</label>
                <input id="views" name="views" type="number" min="1" max="<?= e($maxViews) ?>" value="1" required>
            </div>
        </div>

        <label for="passphrase">Passphrase (optional)</label>
        <input id="passphrase" name="passphrase" type="password" maxlength="<?= e($maxPassphraseChars) ?>" autocomplete="new-password">
        <p class="hint">The recipient must enter this passphrase to read the secret. Send it by a different channel.</p>

        <p class="row">
            <button type="submit" id="create-submit">Save the secret</button>
            <span id="create-status" class="hint" role="status"></span>
        </p>
    </form>
    <p class="hint">Your browser encrypts the secret. The key is in the share link after <code>#</code>, and your browser never sends it to us.</p>
</section>

<section id="created" hidden>
    <h1>Secret saved</h1>
    <p class="lead">
        The secret is deleted after <span id="created-ttl"></span> or after <span id="created-views"></span>.
        We show these links only one time.
    </p>

    <label for="share-url">Share link (send this to the recipient)</label>
    <div class="copy">
        <input id="share-url" type="text" readonly>
        <button type="button" class="secondary" data-copy="share-url">Copy</button>
    </div>
    <p class="hint">The part after <code>#</code> is the encryption key. Without the full link, nobody can read the secret.</p>
    <p class="hint" id="created-passphrase" hidden>The recipient also needs the passphrase. Send it by a different channel.</p>

    <label for="delete-url">Delete link (keep this private)</label>
    <div class="copy">
        <input id="delete-url" type="text" readonly>
        <button type="button" class="secondary" data-copy="delete-url">Copy</button>
    </div>
    <p class="hint">Use the delete link to delete the secret before it expires.</p>

    <p><a href="/">Share another secret</a></p>
</section>
