<h1>Help</h1>
<p class="lead">
    Vaporised helps you send sensitive information through email. You send part of the information by email,
    save the other part here, and send only the link. The information is never all in one location.
</p>

<h2>Send a secret</h2>
<ol>
    <li>Send the first part of the information (for example, the username) by email.</li>
    <li>On the <a href="/">home page</a>, type the secret (for example, the password), or click <strong>Generate a password</strong>.</li>
    <li>Select a time limit in <strong>Delete after</strong>.</li>
    <li>Set the <strong>Maximum views</strong>. The maximum is <?= e($maxViews) ?>.</li>
    <li>Optional: type a passphrase. Send the passphrase to the recipient by a different channel, for example by phone.</li>
    <li>Click <strong>Save the secret</strong>.</li>
    <li>Copy the share link and send it to the recipient.</li>
    <li>Keep the delete link private. You can use it to delete the secret before it expires.</li>
</ol>
<p class="hint">We show the share link and the delete link only one time. Copy them before you close the page.</p>

<h2>Read a secret</h2>
<ol>
    <li>Open the share link that you received.</li>
    <li>If the sender set a passphrase, type it.</li>
    <li>Click <strong>Reveal the secret</strong> only when you are ready. Each click uses one view.</li>
    <li>Copy the secret and save it in a safe location, for example a password manager.</li>
</ol>

<h2>When is a secret deleted?</h2>
<p>We delete a secret at the first of these events:</p>
<ul>
    <li>The recipient uses the last view.</li>
    <li>The time limit ends.</li>
    <li>The sender uses the delete link.</li>
    <li>A person types an incorrect passphrase <?= e($maxFailedAttempts) ?> times.</li>
</ul>
<p>We keep no copies and no archives. After we delete a secret, nobody can get it back.</p>

<h2>Time limits</h2>
<p>You can select one of these time limits:</p>
<ul>
    <?php foreach ($ttlChoices as $label): ?>
        <li><?= e($label) ?></li>
    <?php endforeach; ?>
</ul>

<h2>Questions</h2>

<h3>The link says “Secret not available”. Why?</h3>
<p>
    The secret has expired, has no views left, or the sender deleted it.
    Ask the sender to save the secret again and send you a new link.
</p>

<h3>Why is JavaScript necessary?</h3>
<p>
    Before it saves a secret, your browser does a short calculation. This check stops automated misuse of the service.
    You do not need JavaScript to read a secret.
</p>

<h3>How long can a secret be?</h3>
<p>The maximum is <?= e($maxSecretChars) ?> characters.</p>

<h3>Can you see my secret?</h3>
<p>
    We encrypt each secret before we store it. The database keeps no copy of the share link.
    The server decrypts a secret only when a person uses the share link.
    A passphrase adds to the encryption, so without the passphrase the secret cannot be decrypted.
</p>

<h3>Is a passphrase necessary?</h3>
<p>
    No, but it adds protection. If a person gets the link, they also need the passphrase to read the secret.
</p>

<p>For information about the data that we keep, read the <a href="/privacy">privacy policy</a>.</p>
