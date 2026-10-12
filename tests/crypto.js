'use strict';

// Browser crypto tests. Run: node tests/crypto.js

require('../public/assets/crypto.js');
const assert = require('node:assert/strict');

const C = globalThis.VaporisedCrypto;

async function rejects(promise, name) {
    try {
        await promise;
    } catch (e) {
        return;
    }
    assert.fail(name + ' did not throw');
}

(async () => {
    const key = C.generateLinkKey();
    assert.match(key, /^[A-Za-z0-9_-]{43}$/, 'link key is 43 base64url characters');
    assert.ok(C.isLinkKey(key));
    assert.ok(!C.isLinkKey(key.slice(1)));
    assert.ok(!C.isLinkKey(key + 'A'));
    assert.ok(!C.isLinkKey(''));

    assert.equal(C.toBase64Url(C.fromBase64Url('AQID_-8')), 'AQID_-8', 'base64url round trip');
    assert.throws(() => C.fromBase64Url('a+b/'), 'standard base64 is refused');

    const blob = await C.encrypt('hunter2 ✓ 日本', key, '');
    assert.match(blob, /^[A-Za-z0-9_-]+$/);
    assert.ok(!blob.includes('hunter2'), 'ciphertext does not contain the plaintext');
    assert.equal(await C.decrypt(blob, key, ''), 'hunter2 ✓ 日本', 'round trip without passphrase');
    assert.notEqual(await C.encrypt('same', key, ''), await C.encrypt('same', key, ''), 'fresh salt and iv each time');

    await rejects(C.decrypt(blob, C.generateLinkKey(), ''), 'different link key');
    await rejects(C.decrypt(blob, key, 'x'), 'passphrase on a secret that has none');

    const bytes = C.fromBase64Url(blob);
    bytes[bytes.length - 1] ^= 1;
    await rejects(C.decrypt(C.toBase64Url(bytes), key, ''), 'tampered ciphertext');
    bytes[bytes.length - 1] ^= 1;
    bytes[0] = 2;
    await rejects(C.decrypt(C.toBase64Url(bytes), key, ''), 'unknown version');
    await rejects(C.decrypt('AAAA', key, ''), 'short blob');

    const locked = await C.encrypt('secret', key, 'correct horse');
    assert.equal(await C.decrypt(locked, key, 'correct horse'), 'secret', 'round trip with passphrase');
    await rejects(C.decrypt(locked, key, 'wrong'), 'incorrect passphrase');
    await rejects(C.decrypt(locked, key, ''), 'empty passphrase');

    console.log('OK: crypto.js checks passed');
})().catch((e) => {
    console.error('FAIL:', e.message);
    process.exit(1);
});
