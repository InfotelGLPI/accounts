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

// Bootstrap GLPI's test environment (DB connection, session, etc.)
require_once dirname(__DIR__, 3) . '/tests/bootstrap.php';

// Register the plugin classes into the already-loaded Composer autoloader: the test
// environment only loads the plugins of tests/fixtures/plugins
$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
$loader->addPsr4('GlpiPlugin\\Accounts\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Accounts\\Tests\\', dirname(__DIR__) . '/tests/');

// Plugin::getPhpDir() does not find a plugin outside tests/fixtures in the test environment
if (!defined('PLUGIN_ACCOUNTS_DIR')) {
    define('PLUGIN_ACCOUNTS_DIR', str_replace('\\', '/', dirname(__DIR__)));
}
if (!defined('PLUGIN_ACCOUNTS_WEBDIR')) {
    define('PLUGIN_ACCOUNTS_WEBDIR', '/plugins/accounts');
}
if (!defined('PLUGIN_ACCOUNTS_VERSION')) {
    require_once dirname(__DIR__) . '/setup.php';
}

// The CI installs and activates the plugin through bin/console before the tests; install it
// here too when the suite runs against a database that does not have it yet.
global $DB;
if (!$DB->tableExists('glpi_plugin_accounts_accounts')) {
    require_once dirname(__DIR__) . '/hook.php';
    plugin_accounts_install();
}

// The test environment does not load the plugin, so its Twig namespace may be missing
$twig_loader = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment()->getLoader();
if ($twig_loader instanceof \Twig\Loader\FilesystemLoader && !in_array('accounts', $twig_loader->getNamespaces(), true)) {
    $twig_loader->addPath(dirname(__DIR__) . '/templates', 'accounts');
}
