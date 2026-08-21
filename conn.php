<?php

// Database credentials are loaded from the private configuration/environment.
// Never store database passwords in this repository.
$config = require __DIR__ . '/config.php';

$username = (string) ($config['db_username'] ?? '');
$dbname   = (string) ($config['db_name'] ?? '');
$hostname = (string) ($config['db_hostname'] ?? '');
$password = (string) ($config['db_password'] ?? '');

if ($username === '' || $dbname === '' || $hostname === '' || $password === '') {
    throw new RuntimeException('Database configuration is missing. Configure the private environment before connecting.');
}
