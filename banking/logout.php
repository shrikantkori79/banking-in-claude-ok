<?php
require __DIR__ . '/inc/bootstrap.php';
$_SESSION = []; session_destroy();
header('Location: ' . BASE_URL . '/index.php');
