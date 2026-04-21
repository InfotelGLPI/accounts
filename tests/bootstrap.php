<?php

$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';

$loader->addPsr4('GlpiPlugin\\Accounts\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Accounts\\Tests\\', dirname(__DIR__) . '/tests/');
