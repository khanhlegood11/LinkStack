<?php

// Keep the web document root separate from Laravel's source and configuration.
$_SERVER['QUERY_STRING'] = $_SERVER['QUERY_STRING'] ?? '';
chdir(dirname(__DIR__));
require dirname(__DIR__) . '/index.php';
