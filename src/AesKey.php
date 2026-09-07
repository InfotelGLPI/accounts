<?php

/**
 * -------------------------------------------------------------------------
 * accounts plugin for GLPI
 * Copyright (C) 2015-2026 by the accounts Development Team.
 *
 * https://github.com/InfotelGLPI/accounts
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of accounts.
 *
 * accounts is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * accounts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with accounts. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Accounts;

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Session;
use Toolbox;

/**
 * Class AesKey
 */
class AesKey extends CommonDBTM
{
    public static $rightname = "plugin_accounts_hash";

    /**
     * The master AES key is stored encrypted at rest with GLPIKey and must never
     * be disclosed in logs, history or exports.
     * @var string[]
     */
    public static $undisclosedFields = ['name'];

    /**
     * @var hash
     */
    private $h;

    /**
     * AesKey constructor.
     */
    public function __construct()
    {
        $this->h = new Hash();
    }

    public static function getIcon()
    {
        return "ti ti-lock-open";
    }

    protected function computeFriendlyName()
    {
        return _n('Encryption key', 'Encryption key', 1, 'accounts');
    }

    /**
     * @param int $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Encryption key', 'Encryption key', $nb, 'accounts');
    }

    /**
     * @param CommonGLPI $item
     * @param int $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate) {
            switch ($item->getType()) {
                case Hash::class:
                    if (Session::haveRight(static::$rightname, UPDATE)) {
                        return self::createTabEntry(__s('Save the encryption key', 'accounts'));
                    }
                    return "";
                case __CLASS__:
                    return self::createTabEntry(self::getTypeName());
            }
        }
        return '';
    }

    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        $self = new self();

        switch ($item->getType()) {
            case Hash::class:
                // getTabNameForItem() only offers this tab to holders of the UPDATE right, but a tab
                // is not a security boundary: ajax/common.tabs.php validates can($id, READ) on the
                // Hash and nothing else. Replaying the condition here is what Hash itself already
                // does in its own displayTabContentForItem().
                if (!Session::haveRight(static::$rightname, UPDATE)) {
                    return false;
                }
                $key = self::checkIfAesKeyExists($item->getID());
                if ($key) {
                    $self->showAesKey($item->getID());
                }
                if (!$key) {
                    $self->showForm("", ['plugin_accounts_hashes_id' => $item->getID()]);
                }
                break;
            case __CLASS__:
                $item->showForm($item->getID());
        }
        return true;
    }


    /**
     * Return the decrypted master key stored for a fingerprint, or false when none is stored.
     *
     * @param int|string $plugin_accounts_hashes_id
     * @return string|false
     */
    public static function checkIfAesKeyExists($plugin_accounts_hashes_id)
    {

        $aeskey = false;
        if ($plugin_accounts_hashes_id) {
            $dbu = new DbUtils();
            $devices = $dbu->getAllDataFromTable(
                "glpi_plugin_accounts_aeskeys",
                ["plugin_accounts_hashes_id" => $plugin_accounts_hashes_id],
            );
            if (!empty($devices)) {
                foreach ($devices as $device) {
                    // Stored encrypted at rest -> return the decrypted master key
                    return self::decryptStoredKey($device["name"]);
                }
            }
        }

        return $aeskey;
    }

    /**
     * @param array $options
     * @return array
     */
    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    /**
     * @param $ID
     * @param array $options
     */
    public function showForm($ID, $options = [])
    {
        // Reached from front/aeskey.form.php, which gates on UPDATE, and from the Hash tab, which
        // did not until now. Account::showForm() opens the same way, and the entity has to be
        // rebuilt by hand here because the table has no entities_id of its own.
        if (!Session::haveRight(static::$rightname, UPDATE)) {
            return false;
        }
        $aeskeys_id = (int) $ID;
        $hashes_id  = (int) ($options['plugin_accounts_hashes_id'] ?? 0);
        if ($aeskeys_id > 0 && !self::isReachable($aeskeys_id)) {
            return false;
        }
        if ($aeskeys_id <= 0 && $hashes_id > 0 && !self::isHashReachable($hashes_id)) {
            return false;
        }

        $restrict = getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes", '', '', $this->h->maybeRecursive());
        $nbhashes = countElementsInTable("glpi_plugin_accounts_hashes", $restrict);

        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@accounts/aeskey.html.twig', [
            'item' => $this,
            'nbhashes' => $nbhashes,
            'params' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        // Not attached to hash -> not added
        if (!isset($input['plugin_accounts_hashes_id']) || $input['plugin_accounts_hashes_id'] <= 0) {
            return false;
        }
        // An empty record would be indistinguishable from "no key stored": refuse it.
        if (!isset($input['name']) || $input['name'] === '') {
            Session::addMessageAfterRedirect(
                __('The encryption key cannot be empty', 'accounts'),
                false,
                ERROR,
            );
            return false;
        }

        // Encrypt the master key at rest (stored as GLPIKey ciphertext)
        $input['name'] = (new \GLPIKey())->encrypt($input['name']);

        return $input;
    }

    /**
     * @param  $input
     * @return mixed[]
     */
    public function prepareInputForUpdate($input)
    {
        if (!isset($input['name']) || $input['name'] === '') {
            // The form renders the field empty, so an untouched submission carries no key:
            // leave the stored one alone rather than overwriting it with an empty value.
            unset($input['name']);
            return $input;
        }

        // Belt and braces: should any other caller still hand back the stored ciphertext
        // unchanged, encrypting it again would produce a double GLPIKey ciphertext and make
        // every account of this hash undecryptable, with no way back. Treat an unchanged
        // value as "not modified" instead of re-encrypting it.
        if ($input['name'] === ($this->fields['name'] ?? null)) {
            unset($input['name']);
            return $input;
        }

        // Encrypt the master key at rest (stored as GLPIKey ciphertext)
        $input['name'] = (new \GLPIKey())->encrypt($input['name']);

        return $input;
    }

    /**
     * Return the decrypted master AES key value.
     *
     * @return string
     */
    public function getDecryptedName()
    {
        return self::decryptStoredKey($this->fields['name'] ?? '');
    }

    /**
     * Read back the master key stored in the `name` column.
     *
     * Also recovers vaults hit by the double encryption defect: the form used to resubmit the
     * stored GLPIKey ciphertext, which prepareInputForUpdate() then encrypted a second time.
     * A single decryption of such a record yields another ciphertext instead of the key, and
     * every account attached to the hash reads as unreadable. Peeling the extra layer restores
     * them. Note that decrypt() is never called speculatively: it emits a warning on anything
     * that was not produced by GLPIKey, so the shape is checked first.
     *
     * @param string|null $stored Raw column value
     * @return string             The master key, or an empty string when it cannot be read
     */
    private static function decryptStoredKey(?string $stored): string
    {
        $decrypted = (string) (new \GLPIKey())->decrypt((string) $stored);

        if (self::looksLikeGlpiKeyCiphertext($decrypted)) {
            $unwrapped = (string) (new \GLPIKey())->decrypt($decrypted);
            if ($unwrapped !== '') {
                return $unwrapped;
            }
        }

        return $decrypted;
    }

    /**
     * Tell whether a value has the shape of a GLPIKey ciphertext, i.e. strict base64 holding
     * at least a nonce and an authentication tag. A master key typed by a human virtually
     * never matches, which is what makes the extra decryption above safe to attempt.
     */
    private static function looksLikeGlpiKeyCiphertext(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $raw = base64_decode($value, true);
        if ($raw === false) {
            return false;
        }

        return strlen($raw) > SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
    }

    /**
     * @param $ID
     */
    public function showAesKey($ID)
    {
        global $DB;

        if (!self::isHashReachable((int) $ID)) {
            return;
        }
        $this->h->getFromDB($ID);

        // The itemtype key of the navigation list must be the real class name: a bare "AesKey"
        // matches nothing once the plugin is namespaced, and the previous/next arrows of the
        // key form silently do nothing.
        Session::initNavigateListItems(self::class, _n('Fingerprint', 'Fingerprints', 1, 'accounts') . " = " . $this->h->fields["name"]);

        $candelete = Session::haveRight(self::$rightname, DELETE);

        $iterator = $DB->request([
            'FROM'      => 'glpi_plugin_accounts_aeskeys',
            'WHERE'     => [
                'plugin_accounts_hashes_id'  => $ID,
            ],
        ]);

        $rand = mt_rand();
        echo "<div class='left'>";

        echo Html::hidden('plugin_accounts_hashes_id', ['value' => $ID]);

        if ($candelete && count($iterator) > 0) {
            Html::openMassiveActionsForm('massaeskey' . $rand);
            $massiveactionparams = ['item' => __CLASS__, 'container' => 'massaeskey' . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='" . ($candelete ? 2 : 1) . "'>" . __s('Encryption key', 'accounts') . "</th></tr>";
        echo "<tr>";
        if ($candelete && count($iterator) > 0) {
            echo "<th width='10'>" . Html::getCheckAllAsCheckbox('massaeskey' . $rand) . "</th>";
        }
        echo "<th class='left'>" . __s('Name') . "</th>";
        echo "</tr>";

        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                // Same key as the initNavigateListItems() call above -- see the comment there.
                Session::addToNavigateListItems(self::class, $data['id']);
                $name = "item[" . $data["id"] . "]";
                echo Html::hidden($name, ['value' => $ID]);
                echo "<tr class='tab_bg_1 center'>";
                if ($candelete) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
                    echo "</td>";
                }
                $link = Toolbox::getItemTypeFormURL(AesKey::class);
                echo "<td class='left'><a href='" . $link . "?id=" . $data["id"] . "&plugin_accounts_hashes_id=" . $ID . "'>";
                echo __s('Encryption key', 'accounts') . "</a></td>";
                echo "</tr>";
            }

            echo "<tr>";
            if ($candelete && count($iterator) > 0) {
                echo "<th width='10'>" . Html::getCheckAllAsCheckbox('massaeskey' . $rand) . "</th>";
            }
            echo "<th class='left'>" . __s('Name') . "</th>";
            echo "</tr>";
            echo "</table>";

            if ($candelete) {
                $massiveactionparams['ontop'] = false;
                Html::showMassiveActions($massiveactionparams);
                Html::closeForm();
            }
        } else {
            echo "</table>";
        }
        echo "</div>";
    }

    /**
     * Tell whether the caller may reach the entity a fingerprint belongs to.
     *
     * glpi_plugin_accounts_aeskeys carries no entities_id: a stored master key is attached to an
     * entity only through its parent Hash. CommonDBTM::checkEntity() therefore does nothing
     * behind can(), and every access boils down to the global plugin_accounts_hash right, which
     * may be granted recursively. This is the missing half of the check, and it lives on the
     * class rather than in front/aeskey.form.php so that every path -- form, tab, massive
     * action -- inherits it.
     *
     * @param int $hashes_id The fingerprint ID
     * @return bool          True when the fingerprint exists in a reachable entity
     */
    public static function isHashReachable(int $hashes_id): bool
    {
        $hash = new Hash();

        return $hashes_id > 0
            && $hash->getFromDB($hashes_id)
            && Session::haveAccessToEntity(
                $hash->fields['entities_id'],
                (bool) $hash->fields['is_recursive'],
            );
    }

    /**
     * Same check for a stored key, resolved through its parent fingerprint.
     *
     * @param int $aeskeys_id The stored key ID
     * @return bool           True when the key hangs off a reachable fingerprint
     */
    public static function isReachable(int $aeskeys_id): bool
    {
        $target = new self();

        return $aeskeys_id > 0
            && $target->getFromDB($aeskeys_id)
            && self::isHashReachable((int) ($target->fields['plugin_accounts_hashes_id'] ?? 0));
    }

    /**
     * Entity of the loaded row, or of the fingerprint an unsaved row is being bound to.
     */
    private function isCurrentRowReachable(): bool
    {
        return self::isHashReachable((int) ($this->fields['plugin_accounts_hashes_id'] ?? 0));
    }

    public function canViewItem(): bool
    {
        return parent::canViewItem() && $this->isCurrentRowReachable();
    }

    public function canCreateItem(): bool
    {
        return parent::canCreateItem() && $this->isCurrentRowReachable();
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && $this->isCurrentRowReachable();
    }

    public function canDeleteItem(): bool
    {
        return parent::canDeleteItem() && $this->isCurrentRowReachable();
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem() && $this->isCurrentRowReachable();
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'add_note';
        // Deleting a master key destroys every account of its fingerprint: the verifier only
        // proves a key, it cannot rebuild one. That belongs on the single guarded path of
        // front/aeskey.form.php, not on front/massiveaction.php, which iterates over whatever
        // ids the request carries. The can*Item() overrides above already close the entity
        // boundary; forbidding the action keeps the destructive route out of reach entirely.
        $forbidden[] = 'delete';
        $forbidden[] = 'purge';
        $forbidden[] = 'restore';
        return $forbidden;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `name` text collate utf8mb4_unicode_ci default NULL,
                         `plugin_accounts_hashes_id` int unsigned NOT NULL default '0',
                         PRIMARY KEY  (`id`),
                         KEY `plugin_accounts_hashes_id` (`plugin_accounts_hashes_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
