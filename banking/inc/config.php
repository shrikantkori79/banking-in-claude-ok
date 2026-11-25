<?php
// MySQL connection (user root).
const DB_HOST = '127.0.0.1';
const DB_NAME = 'banking';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'Sookth Banking';
// Folder of the app under the web root, worked out automatically:
// banking.test -> ''   and   localhost/banking -> '/banking'
$__root = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$__app  = rtrim(str_replace('\\', '/', (string)realpath(__DIR__ . '/..')), '/');
define('BASE_URL', ($__root !== '' && stripos($__app, $__root) === 0) ? substr($__app, strlen($__root)) : '');

// Term and CC accounts must be passed by a different user than the one who created them.
const MAKER_CHECKER_DIFFERENT_USER = true;

// Shows the real error text on screens when a save fails. Set to false once everything works.
const APP_DEBUG = true;

// ---- 2-step login ----
const BANK_CODE = 'SHRISAI';            // shown/checked on the sign-in screen
// OTP is asked only for branches that have "OTP login" ticked in Branch settings.
const OTP_DEV_MODE = true;              // true: the code is shown on screen (testing). Set false in production.
// SMS gateway: any HTTP GET API. {mobile} and {message} are replaced (URL-encoded). Leave '' until you have a gateway.
const SMS_API_URL = '';                 // e.g. 'https://gateway.example/send?key=XXXX&to={mobile}&text={message}'
