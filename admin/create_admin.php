<?php
// File: admin/create_admin.php
// Run only once from your browser: http://localhost/salmannewsletter/admin/create_admin.php
// After successful run, DELETE this file for security.

require_once __DIR__ . '/../config.php';

// ==== ADMIN DETAILS ====
// Change these details before running
$username   = 'kushal_admin';      // unique username
$full_name  = 'Kushal Kamble Dev';     // admin ka full name
// $password   = 'Kushal@123';        // temporary password (change immediately)
$password    = 'Kushal@1998';        // temporary password (change immediately)

// ==== HASH PASSWORD ====
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admins (username, full_name, password) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $username, $full_name, $hash);

if ($stmt->execute()) {
    echo "<h3>✅ Admin created successfully!</h3>";
    echo "<p><strong>Username:</strong> {$username}</p>";
    echo "<p><strong>Full Name:</strong> {$full_name}</p>";
    echo "<p><strong>Password:</strong> {$password}</p>";
    echo "<p style='color:red;'>⚠️ Please delete this file (create_admin.php) immediately for security.</p>";
} else {
    echo "<h3>❌ Error:</h3> " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
