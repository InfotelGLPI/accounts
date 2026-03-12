<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2022 by the accounts Development Team.

 https://github.com/InfotelGLPI/accounts
 -------------------------------------------------------------------------

 LICENSE

 This file is part of accounts.

 accounts is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 accounts is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with accounts. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// Bootstrap GLPI
define("GLPI_DIR_ROOT", "../../..");
require_once GLPI_DIR_ROOT . '/src/Glpi/Application/ResourcesChecker.php';
(new \Glpi\Application\ResourcesChecker(GLPI_DIR_ROOT))->checkResources();

include GLPI_DIR_ROOT . '/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel($options['env'] ?? null);
$application = new \Glpi\Console\Application($kernel);

use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesCtr;
use GlpiPlugin\Accounts\AesKey;
use GlpiPlugin\Accounts\Hash;

// ── Auth guard (web only) ─────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    Session::checkLoginUser();
    if (!Session::checkRight("plugin_accounts_hash", UPDATE)) {
        die('Right access error');
    }
}

$dry_run = in_array('--dry-run', $argv ?? []);

// ── Helpers ───────────────────────────────────────────────────────────────────
function log_line(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    if (PHP_SAPI === 'cli') {
        echo "[$ts] $msg\n";
    } else {
        echo "<pre>[$ts] " . htmlspecialchars($msg) . "</pre>\n";
        ob_flush(); flush();
    }
}

// ── Main ──────────────────────────────────────────────────────────────────────
global $DB;

log_line($dry_run ? '=== DRY RUN — no changes will be written ===' : '=== Starting v1 → v2 encryption migration ===');

// 1. Load all fingerprints with their stored AES key
$hashes = getAllDataFromTable('glpi_plugin_accounts_hashes');

if (empty($hashes)) {
    log_line('No fingerprints found — nothing to migrate.');
    exit(0);
}

// Build a map: hash_id → plaintext fingerprint key (from glpi_plugin_accounts_aeskeys)
$fingerprint_map = [];
foreach ($hashes as $hash_row) {
    $hash_id = $hash_row['id'];
    $aeskey  = new AesKey();
    if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $hash_id])
        && !empty($aeskey->fields['name'])) {
        $fingerprint_map[$hash_id] = $aeskey->fields['name'];
    } else {
        log_line("WARNING: fingerprint ID $hash_id ({$hash_row['name']}) has no stored AES key — accounts using this fingerprint will be SKIPPED.");
    }
}

if (empty($fingerprint_map)) {
    log_line('No fingerprints have a stored AES key. Cannot migrate. Enable "Save the encryption key" on each fingerprint first.');
    exit(1);
}

log_line('Fingerprints with stored key: ' . implode(', ', array_keys($fingerprint_map)));

// 2. Fetch all accounts that have a non-empty, non-v2 password
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'encrypted_password', 'plugin_accounts_hashes_id'],
    'FROM'   => 'glpi_plugin_accounts_accounts',
    'WHERE'  => [
        'is_deleted' => 0,
        'NOT'  => ['encrypted_password' => null],
        ['NOT' => ['encrypted_password' => '']],
    ],
]);

$total    = count($iterator);
$migrated = 0;
$skipped  = 0;
$errors   = 0;

log_line("Total accounts with a password: $total");

foreach ($iterator as $row) {
    $id          = $row['id'];
    $name        = $row['name'];
    $ciphertext  = $row['encrypted_password'];
    $hash_id     = $row['plugin_accounts_hashes_id'];

    // Already v2 — skip
    if (str_starts_with($ciphertext, '$v2$')) {
        $skipped++;
        continue;
    }

    // No fingerprint key available — skip
    if (!isset($fingerprint_map[$hash_id])) {
        log_line("SKIP  account #$id \"$name\" — fingerprint #$hash_id has no stored key.");
        $skipped++;
        continue;
    }

    $fingerprint = $fingerprint_map[$hash_id];
    $old_hash    = hash('sha256', $fingerprint); // matches AesCtr::decrypt() expectation

    // Decrypt v1
    $plaintext = AesCtr::decrypt($ciphertext, $old_hash, 256);

    if ($plaintext === '' || $plaintext === false) {
        log_line("ERROR account #$id \"$name\" — decryption produced empty result. Skipping to avoid data loss.");
        $errors++;
        continue;
    }

    // Re-encrypt v2
    try {
        $new_ciphertext = AccountCrypto::encrypt($plaintext, $fingerprint);
    } catch (\Exception $e) {
        log_line("ERROR account #$id \"$name\" — encrypt failed: " . $e->getMessage());
        $errors++;
        continue;
    }

    if (!$dry_run) {
        $DB->update(
            'glpi_plugin_accounts_accounts',
            ['encrypted_password' => $new_ciphertext],
            ['id' => $id]
        );
    }

    $migrated++;
    log_line(($dry_run ? 'DRY   ' : 'OK    ') . "account #$id \"$name\" re-encrypted to v2.");
}

// ── Summary ───────────────────────────────────────────────────────────────────
log_line('');
log_line('=== Migration complete ===');
log_line("  Migrated : $migrated");
log_line("  Skipped  : $skipped (already v2 or no key available)");
log_line("  Errors   : $errors");

if ($errors > 0) {
    log_line('Some records could not be migrated. Check the log above and verify the stored AES keys are correct.');
    exit(1);
}

exit(0);
