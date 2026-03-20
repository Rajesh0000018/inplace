<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
define('DB_HOST', 'inplace.cqvq8wu4spwq.us-east-1.rds.amazonaws.com');
define('DB_USER', 'admin');
define('DB_PASS', 'Chinna!0712');
define('DB_NAME', 'inplace_db');
define('DB_PORT', 3306);
 
$pdo = new PDO(
  "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
  DB_USER,
  DB_PASS,
  [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]
);
?>