<?php
require_once __DIR__ . '/src/bootstrap.php';

$adminEmail = 'admin@gmail.com';
$employeeEmail = 'employee@gmail.com';
$password = '123456';

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert admin
$stmt1 = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
$stmt1->bind_param("ss", $adminEmail, $hashedPassword);
$stmt1->execute();

// Insert employee
$stmt2 = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'employee')");
$stmt2->bind_param("ss", $employeeEmail, $hashedPassword);
$stmt2->execute();

echo "Users inserted successfully.";
?>
