<h1>Privacy policy</h1>
<p class="lead">Last updated: 12 October 2026</p>

<h2>Who we are</h2>
<p>
    Encode Group Ltd operates Vaporised and is the data controller.
    We host the service through EncodeDotHost, a brand of Encode Group Ltd.
</p>
<p>
    Encode Group Ltd<br>
    The Mansley Business Centre<br>
    Timothy Bridge Road<br>
    Stratford-upon-Avon<br>
    Warwickshire<br>
    CV37 9NQ
</p>
<ul>
    <li>Company number: <a href="https://find-and-update.company-information.service.gov.uk/company/07159623">07159623</a></li>
    <li>ICO registration reference: ZA426898</li>
    <li>Email: <a href="mailto:support@encode.host">support@encode.host</a></li>
</ul>

<h2>Summary</h2>
<ul>
    <li>We do not ask for your name, email address, or an account.</li>
    <li>We set no cookies and use no analytics.</li>
    <li>We send no data to third parties.</li>
    <li>Your browser encrypts each secret before it sends it. We cannot decrypt it.</li>
    <li>We delete each secret permanently. We keep no archives.</li>
</ul>

<h2>The data that we process</h2>

<h3>Secrets</h3>
<p>
    When you save a secret, your browser encrypts it with AES-256-GCM before it sends anything to us.
    The encryption key is the part of the share link after <code>#</code>. Browsers never send that part to a server.
    We store only the encrypted data, and we cannot decrypt it.
</p>
<p>
    If you add a passphrase, the passphrase is part of the encryption key in your browser.
    The server also keeps a one-way Argon2id hash of the passphrase, to count incorrect attempts. We do not store the passphrase.
</p>
<p>
    The database does not store the share link or the delete link. It stores only a one-way hash of each link.
    We do not record who saved a secret or who read it.
</p>
<p>We delete a secret at the first of these events:</p>
<ul>
    <li>The recipient uses the last view.</li>
    <li>The sender uses the delete link.</li>
    <li>A person types too many incorrect passphrases.</li>
    <li>The time limit ends. The secret becomes unavailable immediately, and our database removes it within 5 minutes.</li>
</ul>
<p>The maximum time limit is 7 days.</p>

<h3>Rate limits</h3>
<p>
    To prevent misuse, we count the requests from each IP address. We do not store the IP address.
    We store only a keyed hash of it, which we cannot reverse. We delete each count within 5 minutes after its 1-hour window ends.
</p>

<h3>Browser check</h3>
<p>
    Before it saves a secret, your browser solves a short calculation. We store a hash of each solved calculation
    to prevent reuse, and we delete it after 10 minutes. This data does not identify you.
</p>

<h3>Web server logs</h3>
<p>
    Our web server records each request in an access log. The log includes your IP address, the date and time,
    the requested address, and your browser's user agent (the browser name, version, and operating system).
    The requested address can include the path of a share link or a delete link, but never the encryption key,
    because browsers do not send the part after <code>#</code>. We keep these logs for 3 months, then delete them.
    A path in the log does not give access to a secret after that secret is deleted.
</p>

<h2>Why we process this data</h2>
<p>
    Our lawful basis is legitimate interests under UK GDPR Article 6(1)(f).
    We process the data to operate the service, keep it secure, and prevent misuse.
</p>
<p>
    Do not use Vaporised for personal data that you have no right to share.
    The sender is responsible for the content of each secret.
</p>

<h2>Cookies and storage</h2>
<p>Vaporised sets no cookies and does not use browser storage. The site loads no content from other websites.</p>

<h2>Your rights</h2>
<p>
    Under UK GDPR, you have the right to access, correct, or erase your personal data, to restrict or object to its processing,
    and to complain. To use these rights, email <a href="mailto:support@encode.host">support@encode.host</a>.
</p>
<p>
    We do not record who saved or read a secret. Thus, we usually cannot find a secret or a log entry that relates to you
    unless you give us information, for example your IP address and the time of your request.
</p>
<p>
    You can complain to the Information Commissioner's Office (ICO) at
    <a href="https://ico.org.uk/make-a-complaint/">ico.org.uk/make-a-complaint</a>.
</p>

<h2>Changes to this policy</h2>
<p>If we change this policy, we update this page and the date at the top.</p>
