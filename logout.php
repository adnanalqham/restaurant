<?php
require_once __DIR__ . '/config/db.php';
startSession();
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
