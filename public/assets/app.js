(function () {
    'use strict';

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

    var form = document.getElementById('create-form');
    if (form) {
        var submit = document.getElementById('create-submit');
        var status = document.getElementById('create-status');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit.disabled = true;
            status.textContent = 'Working…';

            fetch('/challenge', { cache: 'no-store' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('challenge');
                    }
                    return response.json();
                })
                .then(function (data) {
                    return solve(data.challenge, data.bits).then(function (nonce) {
                        document.getElementById('pow-challenge').value = data.challenge;
                        document.getElementById('pow-nonce').value = nonce;
                        form.submit();
                    });
                })
                .catch(function () {
                    submit.disabled = false;
                    status.textContent = 'The browser check failed. Try again.';
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
