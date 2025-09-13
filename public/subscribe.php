<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $BASE_URL . '/public/index.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $BASE_URL . '/public/index.php?err=invalid');
    exit;
}

$stmt = $conn->prepare("INSERT INTO subscribers (name, email) VALUES (?, ?) ON DUPLICATE KEY UPDATE status=1, name=VALUES(name)");
$stmt->bind_param('ss', $name, $email);

if ($stmt->execute()) {
    // send welcome email
    $subject = "🎉 Welcome — Thanks for subscribing!";
    $html = "<div style='font-family:Arial,sans-serif;'>
        <h2 style='color:#f5945c'>Welcome, " . htmlspecialchars($name ?: 'Friend') . "!</h2>
        <p>Thanks for subscribing. Expect weekly curated tech & AI updates.</p>
        <p><a href='" . $BASE_URL . "/public/unsubscribe.php?email=" . urlencode($email) . "'>Unsubscribe</a></p>
    </div>";

    sendMailHTML($email, $name ?: '', $subject, $html);
    header('Location: ' . $BASE_URL . '/public/index.php?success=1');
    exit;
} else {
    header('Location: ' . $BASE_URL . '/public/index.php?err=db');
    exit;
}
