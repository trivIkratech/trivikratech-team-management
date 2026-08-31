<?php
require_once __DIR__ . '/../team-management-system/config/app.php';
require_once __DIR__ . '/../team-management-system/config/database.php';

$db = getDB();
$passHash = password_hash('Founder@123', PASSWORD_BCRYPT);
$pinHash = password_hash('1234', PASSWORD_BCRYPT);

$stmt = $db->prepare("UPDATE users SET password = ?, pin = ?");
$stmt->execute([$passHash, $pinHash]);

echo "Successfully updated " . $stmt->rowCount() . " users with Password: 'Founder@123' and PIN: '1234'.\n";
