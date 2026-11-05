<?php
$host     = '127.0.0.1';
$username = 'root';
$password = '';             // Default XAMPP MariaDB password is empty
$database = 'dci_db'; // Ensure this database exists in phpMyAdmin
$port     = 3307;           // Matches phpMyAdmin configuration

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>