<?php
require_once __DIR__ . '/../database/models/SuperAdminModel.php';

$model = new SuperAdminModel();

// Define the credentials for the new Super Admin
$firstName = 'Aldrin';
$lastName = 'Bautista';
$username = 'aldrin_admin';
$email = 'aldrinbautista0425@gmail.com';
$password = 'AdminPass123!'; // You should change this after your first login
$pin = '1234'; // Your 4-digit security PIN

$result = $model->register($firstName, $lastName, $username, $email, $password, $pin);

if ($result['success']) {
    echo "<h2>Super Admin account created successfully!</h2>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
    echo "<p><strong>Temporary Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<p><strong>Security PIN:</strong> " . htmlspecialchars($pin) . "</p>";
    echo "<hr>";
    echo "<p style='color:red;'><strong>Security Note:</strong> Please delete this file (<code>seed_superadmin.php</code>) immediately after use.</p>";
} else {
    echo "<h2>Failed to create account</h2>";
    echo "<p>Error: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
}
?>