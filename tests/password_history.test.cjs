// Run with: node --test tests/password_history.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');

function browser() {
    const values = new Map();
    const document = {addEventListener() {}};
    const context = vm.createContext({
        crypto: crypto.webcrypto,
        atob: (value) => Buffer.from(value, 'base64').toString('binary'),
        btoa: (value) => Buffer.from(value, 'binary').toString('base64'),
        document,
        console,
        root_accounts_doc: '/plugins/accounts',
        $: (selector) => selector === document ? {on() {}} : {
            val(value) {
                if (arguments.length) {
                    values.set(selector, value);
                    return this;
                }
                return values.get(selector) || '';
            },
            text() {
                return values.get(selector) || '';
            }
        }
    });
    for (const file of ['public/lib/crypto-js.min.js', 'public/lib/lightcrypt.js',
        'public/scripts/crypt.js', 'public/scripts/password_history.js']) {
        vm.runInContext(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), context, {filename: file});
    }
    return {context, values};
}

const key = 'history-browser-key';
// Same format as the PHP verifier; a lower cost keeps isolated VM tests fast.
// Production creation enforces 100000; decrypting accepts the serialized count.
function verifier(key) {
    const salt = Buffer.from('0123456789abcdef');
    return '$pbkdf2$1000$' + salt.toString('base64') + '$'
        + crypto.pbkdf2Sync(key, salt, 1000, 32, 'sha256').toString('hex');
}

test('history decrypts v4 using its saved verifier and key', () => {
    const {context} = browser();
    const hash = verifier(key);
    const password = 'Ancien <mot> "de passe" éè 🔑';
    const ciphertext = context.encrypt_cryptogram(password, key, hash);
    assert.ok(ciphertext.startsWith('$v4$'));
    assert.equal(context.accountsDecryptHistoryEntry(ciphertext, hash, key), password);
});

test('history retains support for v1, v2 and v3 passwords', () => {
    const {context} = browser();
    const legacyVerifier = context.SHA256(context.SHA256(key));
    const v1 = vm.runInContext('AESEncryptCtr("old-password", SHA256("history-browser-key"), 256)', context);
    const v3 = context.encrypt_cryptogram('old-password', key, legacyVerifier);
    const v2 = v3.replace('$v3$', '$v2$');
    for (const ciphertext of [v1, v2, v3]) {
        assert.equal(context.accountsDecryptHistoryEntry(ciphertext, legacyVerifier, key), 'old-password');
    }
});

test('a wrong or empty key does not disclose a historical password', () => {
    const {context} = browser();
    const hash = verifier(key);
    const ciphertext = context.encrypt_cryptogram('old-password', key, hash);
    assert.equal(context.accountsDecryptHistoryEntry(ciphertext, hash, 'wrong-key'), null);
    assert.equal(context.accountsDecryptHistoryEntry(ciphertext, hash, ''), null);
});

test('a historical verifier is independent of the account current key', () => {
    const {context} = browser();
    const hash = verifier(key);
    const ciphertext = context.encrypt_cryptogram('old-password', key, hash);
    assert.equal(context.accountsDecryptHistoryEntry(ciphertext, hash, 'new-account-key'), null);
    assert.equal(context.accountsDecryptHistoryEntry(ciphertext, hash, key), 'old-password');
});

test('a tampered history entry is rejected even with the correct key', () => {
    const {context} = browser();
    const hash = verifier(key);
    const parts = context.encrypt_cryptogram('old-password', key, hash).split('$');
    parts[5] = Buffer.from('tampered').toString('base64');
    assert.equal(context.accountsDecryptHistoryEntry(parts.join('$'), hash, key), null);
    assert.equal(context.accountsDecryptHistoryEntry('$v4$invalid', hash, key), null);
});

test('an unchanged password retains its ciphertext rather than consuming a history slot', () => {
    const {context, values} = browser();
    const hash = verifier(key);
    const ciphertext = context.encrypt_cryptogram('unchanged', key, hash);
    values.set('#aeskey', key);
    values.set('#good_hash', hash);
    values.set('#hidden_password', 'unchanged');
    values.set('#encrypted_password', ciphertext);
    context.encrypt_password();
    assert.equal(values.get('#encrypted_password'), ciphertext);
});

test('a changed password creates a fresh ciphertext in the active key format', () => {
    const {context, values} = browser();
    const hash = verifier(key);
    const ciphertext = context.encrypt_cryptogram('before', key, hash);
    values.set('#aeskey', key);
    values.set('#good_hash', hash);
    values.set('#hidden_password', 'after');
    values.set('#encrypted_password', ciphertext);
    context.encrypt_password();
    const replacement = values.get('#encrypted_password');
    assert.notEqual(replacement, ciphertext);
    assert.equal(context.decrypt_cryptogram(replacement, key), 'after');
});

test('unchanged item-list passwords also retain their ciphertext with a suffix', () => {
    const {context, values} = browser();
    const hash = verifier(key);
    const ciphertext = context.encrypt_cryptogram('unchanged', key, hash);
    values.set('#aeskey_42', key);
    values.set('#good_hash_42', hash);
    values.set('#hidden_password_42', 'unchanged');
    values.set('#encrypted_password_42', ciphertext);
    context.encrypt_password('_42');
    assert.equal(values.get('#encrypted_password_42'), ciphertext);
});
