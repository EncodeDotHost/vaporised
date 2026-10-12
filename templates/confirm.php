<section id="confirm">
    <h1>A secret is ready for you</h1>
    <p class="lead">
        The number of views is limited. Press the button only when you are ready to read and save the secret.
    </p>

    <p class="alert" id="reveal-error" role="alert" hidden></p>

    <noscript><p class="alert">JavaScript is necessary to read a secret. Your browser decrypts the secret with the key in this link.</p></noscript>

    <form id="reveal-form" autocomplete="off">
        <?php if ($hasPassphrase): ?>
            <label for="passphrase">Passphrase</label>
            <input id="passphrase" name="passphrase" type="password" maxlength="<?= e($maxPassphraseChars) ?>" autocomplete="off" required autofocus>
            <p class="hint">Too many incorrect attempts delete the secret.</p>
        <?php endif; ?>
        <p class="row">
            <button type="submit" id="reveal-submit">Reveal the secret</button>
            <span id="reveal-status" class="hint" role="status"></span>
        </p>
    </form>
</section>

<section id="revealed" hidden>
    <h1>Your secret</h1>

    <label for="secret-value">Secret</label>
    <textarea id="secret-value" rows="5" readonly spellcheck="false"></textarea>
    <p class="row">
        <button type="button" class="secondary" data-copy="secret-value">Copy</button>
    </p>

    <p class="alert" id="revealed-last" hidden>This secret is now deleted. Save it before you close this page.</p>
    <p class="hint" id="revealed-remaining" hidden>Views that remain: <span></span>.</p>
</section>
