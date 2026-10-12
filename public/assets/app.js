(function () {
    'use strict';

    var C = window.VaporisedCrypto;

    function leadingZeroBits(bytes) {
        var bits = 0;
        for (var i = 0; i < bytes.length; i++) {
            if (bytes[i] === 0) {
                bits += 8;
                continue;
            }
            for (var mask = 0x80; (bytes[i] & mask) === 0; mask >>= 1) {
                bits++;
            }
            break;
        }
        return bits;
    }

    // Find a nonce so that SHA-256("challenge:nonce") starts with enough zero bits.
    async function solve(challenge, bits) {
        var encoder = new TextEncoder();
        for (var nonce = 0; ; nonce++) {
            var digest = await crypto.subtle.digest('SHA-256', encoder.encode(challenge + ':' + nonce));
            if (leadingZeroBits(new Uint8Array(digest)) >= bits) {
                return String(nonce);
            }
        }
    }

    async function proofOfWork() {
        var response = await fetch('/challenge', { cache: 'no-store' });
        if (!response.ok) {
            throw new Error('challenge');
        }
        var data = await response.json();
        return { challenge: data.challenge, nonce: await solve(data.challenge, data.bits) };
    }

    // POST form fields and read a JSON reply. Rejects with the server's error text.
    async function postJson(url, fields) {
        var body = new URLSearchParams(fields);
        var response = await fetch(url, { method: 'POST', body: body, cache: 'no-store' });
        var data = null;
        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }
        if (!response.ok || data === null) {
            var error = new Error(data && data.error ? data.error : 'The server did not reply. Try again.');
            error.gone = Boolean(data && data.gone);
            throw error;
        }
        return data;
    }

    function showError(element, text) {
        element.textContent = text;
        element.hidden = text === '';
    }

    // --- Create ---------------------------------------------------------------

    var createForm = document.getElementById('create-form');
    if (createForm) {
        var createSubmit = document.getElementById('create-submit');
        var createStatus = document.getElementById('create-status');
        var createError = document.getElementById('create-error');

        createForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var secret = document.getElementById('secret').value.replace(/\r\n/g, '\n');
            var passphrase = document.getElementById('passphrase').value;
            var ttl = document.getElementById('ttl');
            var views = document.getElementById('views').value;
            if (secret === '') {
                showError(createError, 'Enter a secret.');
                return;
            }

            createSubmit.disabled = true;
            showError(createError, '');
            createStatus.textContent = 'Encrypting…';

            var linkKey = C.generateLinkKey();
            Promise.all([C.encrypt(secret, linkKey, passphrase), proofOfWork()])
                .then(function (results) {
                    createStatus.textContent = 'Saving…';
                    return postJson('/secrets', {
                        ciphertext: results[0],
                        passphrase: passphrase,
                        ttl: ttl.value,
                        views: views,
                        pow_challenge: results[1].challenge,
                        pow_nonce: results[1].nonce
                    });
                })
                .then(function (data) {
                    document.getElementById('share-url').value = data.share_url + '#' + linkKey;
                    document.getElementById('delete-url').value = data.delete_url;
                    document.getElementById('created-ttl').textContent = ttl.options[ttl.selectedIndex].textContent;
                    document.getElementById('created-views').textContent = views + (views === '1' ? ' view' : ' views');
                    document.getElementById('created-passphrase').hidden = passphrase === '';
                    createForm.reset();
                    document.getElementById('create').hidden = true;
                    document.getElementById('created').hidden = false;
                    window.scrollTo(0, 0);
                })
                .catch(function (error) {
                    showError(createError, error.message);
                    createSubmit.disabled = false;
                    createStatus.textContent = '';
                });
        });

        document.getElementById('generate').addEventListener('click', function () {
            var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!#$%&*+-=?@';
            var limit = 256 - (256 % alphabet.length);
            var password = '';
            while (password.length < 24) {
                var bytes = crypto.getRandomValues(new Uint8Array(32));
                for (var i = 0; i < bytes.length && password.length < 24; i++) {
                    if (bytes[i] < limit) {
                        password += alphabet[bytes[i] % alphabet.length];
                    }
                }
            }
            document.getElementById('secret').value = password;
        });
    }

    // --- Reveal ---------------------------------------------------------------

    var revealForm = document.getElementById('reveal-form');
    if (revealForm) {
        var revealSubmit = document.getElementById('reveal-submit');
        var revealStatus = document.getElementById('reveal-status');
        var revealError = document.getElementById('reveal-error');
        var linkKey = window.location.hash.slice(1);

        if (!C.isLinkKey(linkKey)) {
            showError(revealError, 'This link is incomplete. The part after "#" is the key, and it is missing or damaged. Ask the sender for the full link.');
            revealSubmit.disabled = true;
        }

        revealForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var passphraseField = document.getElementById('passphrase');
            var passphrase = passphraseField ? passphraseField.value : '';

            revealSubmit.disabled = true;
            showError(revealError, '');
            revealStatus.textContent = 'Working…';

            postJson(window.location.pathname, { passphrase: passphrase })
                .then(function (data) {
                    return C.decrypt(data.ciphertext, linkKey, passphrase).then(function (secret) {
                        document.getElementById('secret-value').value = secret;
                        document.getElementById('revealed-last').hidden = data.views_remaining > 0;
                        var remaining = document.getElementById('revealed-remaining');
                        remaining.hidden = data.views_remaining < 1;
                        remaining.querySelector('span').textContent = String(data.views_remaining);
                        document.getElementById('confirm').hidden = true;
                        document.getElementById('revealed').hidden = false;
                    }, function () {
                        throw new Error('The secret could not be decrypted. The link may be damaged. One view was used.');
                    });
                })
                .catch(function (error) {
                    showError(revealError, error.message);
                    revealStatus.textContent = '';
                    revealSubmit.disabled = Boolean(error.gone);
                });
        });
    }

    // --- Copy buttons ---------------------------------------------------------

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-copy'));
            navigator.clipboard.writeText(field.value).then(function () {
                button.textContent = 'Copied';
            }, function () {
                field.select();
            });
        });
    });
})();
