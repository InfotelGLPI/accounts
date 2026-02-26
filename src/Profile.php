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

namespace GlpiPlugin\Accounts;

use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use ProfileRight;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Profile
 */
class Profile extends \Profile
{
    public static $rightname = "profile";

    /**
     * @param CommonGLPI $item
     * @param int        $withtemplate
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile') {
            return self::createTabEntry(Account::getTypeName(2));
        }
        return '';
    }


    /**
     * @return string
     */
    public static function getIcon()
    {
        return "ti ti-lock";
    }

    /**
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (!$item instanceof \Profile || !self::canView()) {
            return false;
        }

        $profile = new \Profile();
        $profile->getFromDB($item->getID());

        $rights = self::getAllRights($profile->getField('interface'));

        $twig = TemplateRenderer::getInstance();
        $twig->display('@accounts/profile.html.twig', [
            'id'      => $item->getID(),
            'profile' => $profile,
            'title'   => self::getTypeName(Session::getPluralNumber()),
            'rights'  => $rights,
        ]);

        return true;
    }

    /**
     * @param $ID
     */
    public static function createFirstAccess($ID)
    {
        //85
        self::addDefaultProfileInfos(
            $ID,
            ['plugin_accounts'               => 127,
                'plugin_accounts_hash'          => 127,
                'plugin_accounts_my_groups'     => 1,
                'plugin_accounts_open_ticket'   => 1,
                'plugin_accounts_see_all_users' => 1],
            true
        );
    }


    /**
     * @param bool $all
     *
     * @return array
     */
    public static function getAllRights($helpdesk = "central")
    {
        global $DB;


        if (!$DB->tableExists('glpi_plugin_accounts_accounts')) {
            return [];
        }

        $rights = [
            [
                'itemtype' => Account::class,
                'label'  => Account::getTypeName(Session::getPluralNumber()),
                'field'    => Account::$rightname,
                'rights' => \Profile::getRightsFor(Account::class),
            ],
        ];

        if ($helpdesk == "central") {
            $rights[] = [
                'itemtype' => Hash::class,
                'label'  => Hash::getTypeName(Session::getPluralNumber()),
                'field'    => Hash::$rightname,
                'rights' => \Profile::getRightsFor(Hash::class),
            ];
        }

        $rights[] = ['itemtype' => Account::class,
            'label'    => __s('See accounts of my groups', 'accounts'),
            'field'    => 'plugin_accounts_my_groups',
            'rights' => [
                READ  => __s('Read'),
            ],];

        $rights[] = ['itemtype' => Account::class,
            'label'    => __s('See all accounts', 'accounts'),
            'field'    => 'plugin_accounts_see_all_users',
            'rights' => [
                READ  => __s('Read'),
            ],];

        $rights[] = ['itemtype' => Account::class,
            'label'    => __s('Associable items to a ticket'),
            'field'    => 'plugin_accounts_open_ticket',
            'rights' => [
                READ  => __s('Read'),
            ],];

        return $rights;
    }

    /**
     * Init profiles
     *
     * @param $old_right
     *
     * @return int
     */

    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;
            case '0':
            case '1':
                return $old_right;

            default:
                return 0;
        }
    }

    /**
     * @since 0.85
     * Migration rights from old system to the new one for one profile
     *
     * @param $profiles_id
     *
     * @return bool
     */
    public static function migrateOneProfile($profiles_id)
    {
        global $DB;
        //Cannot launch migration if there's nothing to migrate...
        if (!$DB->tableExists('glpi_plugin_accounts_profiles')) {
            return true;
        }

        $it = $DB->request([
            'FROM' => 'glpi_plugin_accounts_profiles',
            'WHERE' => ['profiles_id' => $profiles_id],
        ]);
        foreach ($it as $profile_data) {
            $matching       = ['accounts'    => 'plugin_accounts',
                'all_users'   => 'plugin_accounts_see_all_users',
                'my_groups'   => 'plugin_accounts_my_groups',
                'open_ticket' => 'plugin_accounts_open_ticket'];
            $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
            foreach ($matching as $old => $new) {
                if (!isset($current_rights[$old])) {
                    $DB->update('glpi_profilerights', ['rights' => self::translateARight($profile_data[$old])], [
                        'name'        => $new,
                        'profiles_id' => $profiles_id,
                    ]);
                }
            }
        }
    }

    /**
     * Initialize profiles, and migrate it necessary
     */
    public static function initProfile()
    {
        global $DB;
        $profile = new self();
        $dbu     = new DbUtils();
        //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights() as $data) {
            if ($dbu->countElementsInTable(
                "glpi_profilerights",
                ["name" => $data['field']]
            ) == 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        //Migration old rights in new ones
        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_profiles',
        ]);
        foreach ($it as $prof) {
            self::migrateOneProfile($prof['id']);
        }
        $it = $DB->request([
            'FROM' => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                'name' => ['LIKE', '%plugin_accounts%'],
            ],
        ]);
        foreach ($it as $prof) {
            if (isset($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
            }
        }
    }


    public static function removeRightsFromSession()
    {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_accounts_profiles')) {
            return true;
        }
        foreach (self::getAllRights() as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }

    /**
     * @param      $profiles_id
     * @param      $rights
     * @param bool $drop_existing
     *
     * @internal param $profile
     */
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $profileRight = new ProfileRight();
        $dbu          = new DbUtils();
        foreach ($rights as $right => $value) {
            if ($dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id,
                    "name" => $right]
            ) && $drop_existing) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }
            if (!$dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id,
                    "name" => $right]
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }
}
