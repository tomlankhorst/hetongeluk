<?php

// Debug mode
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// Release mode: Suppress messages
error_reporting(0);

// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 1);

// Same site cookie: PHP 7.3+
ini_set('session.cookie_samesite', 'Lax');

session_start();

date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_MONETARY, 'nl_NL');

$root = realpath($_SERVER["DOCUMENT_ROOT"]);

require_once $root . '/config.php';
require_once 'templates.php';
require_once 'database.php';
require_once 'users.php';
require_once 'utils.php';

try {
  /** @var TDatabase $database */
  $database = new TDatabase();
  $database->open();
  $user = new TUser($database);
} catch (Exception $e){
  die('Internal error: Initialization failed');
}

