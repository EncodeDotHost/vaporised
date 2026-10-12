/*
 * Browser-side encryption for Vaporised. The server never sees the link key.
 *
 * Link key:  32 random bytes, base64url (43 characters), in the URL after "#".
 *            Browsers never send the part after "#" to the server.
 * AES key:   HKDF-SHA256(ikm = linkKey [+ PBKDF2-SHA256(passphrase)], salt, "vaporised-v1")
 * Blob:      version (1 byte) | salt (16 bytes) | iv (12 bytes) | AES-256-GCM ciphertext and tag
 *            The version byte is also the additional authenticated data.
 *
 * This file runs in the browser and in Node (tests/crypto.js).
 */
(function (root) {
    'use strict';

    var VERSION = 1;
    var KEY_BYTES = 32;
    var SALT_BYTES = 16;
    var IV_BYTES = 12;
    var TAG_BYTES = 16;
    var PBKDF2_ITERATIONS = 600000;
    var INFO = 'vaporised-v1';

    var subtle = root.crypto.subtle;
    var encoder = new TextEncoder();
    var decoder = new TextDecoder('utf-8', { fatal: true });

    function toBase64Url(bytes) {
        var binary = '';
        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function fromBase64Url(text) {
        if (typeof text !== 'string' || !/^[A-Za-z0-9_-]*$/.test(text)) {
            throw new Error('Not base64url');
        }
        var binary = atob(text.replace(/-/g, '+').replace(/_/g, '/'));
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }

    function concat(a, b) {
        var out = new Uint8Array(a.length + b.length);
        out.set(a, 0);
        out.set(b, a.length);
        return out;
    }

    function isLinkKey(text) {
        return typeof text === 'string' && /^[A-Za-z0-9_-]{43}$/.test(text) && fromBase64Url(text).length === KEY_BYTES;
    }

    function generateLinkKey() {
        return toBase64Url(root.crypto.getRandomValues(new Uint8Array(KEY_BYTES)));
    }

    async function deriveAesKey(linkKeyText, passphrase, salt) {
        var ikm = fromBase64Url(linkKeyText);
        if (ikm.length !== KEY_BYTES) {
            throw new Error('Bad link key');
        }
        if (passphrase !== '') {
            var passKey = await subtle.importKey('raw', encoder.encode(passphrase), 'PBKDF2', false, ['deriveBits']);
            var passBits = await subtle.deriveBits(
                { name: 'PBKDF2', hash: 'SHA-256', salt: salt, iterations: PBKDF2_ITERATIONS },
                passKey,
                256
            );
            ikm = concat(ikm, new Uint8Array(passBits));
        }
        var hkdfKey = await subtle.importKey('raw', ikm, 'HKDF', false, ['deriveKey']);
        return subtle.deriveKey(
            { name: 'HKDF', hash: 'SHA-256', salt: salt, info: encoder.encode(INFO) },
            hkdfKey,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    }

    /** Encrypt text. Returns the base64url blob for the server. */
    async function encrypt(plaintext, linkKeyText, passphrase) {
        var salt = root.crypto.getRandomValues(new Uint8Array(SALT_BYTES));
        var iv = root.crypto.getRandomValues(new Uint8Array(IV_BYTES));
        var header = concat(concat(new Uint8Array([VERSION]), salt), iv);
        var key = await deriveAesKey(linkKeyText, passphrase, salt);
        var ciphertext = await subtle.encrypt(
            { name: 'AES-GCM', iv: iv, additionalData: new Uint8Array([VERSION]) },
            key,
            encoder.encode(plaintext)
        );
        return toBase64Url(concat(header, new Uint8Array(ciphertext)));
    }

    /** Decrypt a blob from the server. Throws if the key, passphrase, or data is wrong. */
    async function decrypt(blobText, linkKeyText, passphrase) {
        var blob = fromBase64Url(blobText);
        if (blob.length < 1 + SALT_BYTES + IV_BYTES + TAG_BYTES || blob[0] !== VERSION) {
            throw new Error('Bad blob');
        }
        var salt = blob.subarray(1, 1 + SALT_BYTES);
        var iv = blob.subarray(1 + SALT_BYTES, 1 + SALT_BYTES + IV_BYTES);
        var ciphertext = blob.subarray(1 + SALT_BYTES + IV_BYTES);
        var key = await deriveAesKey(linkKeyText, passphrase, salt);
        var plaintext = await subtle.decrypt(
            { name: 'AES-GCM', iv: iv, additionalData: new Uint8Array([VERSION]) },
            key,
            ciphertext
        );
        return decoder.decode(plaintext);
    }

    root.VaporisedCrypto = {
        generateLinkKey: generateLinkKey,
        isLinkKey: isLinkKey,
        encrypt: encrypt,
        decrypt: decrypt,
        toBase64Url: toBase64Url,
        fromBase64Url: fromBase64Url
    };
})(typeof globalThis !== 'undefined' ? globalThis : this);
