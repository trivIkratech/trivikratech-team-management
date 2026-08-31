<?php
require_once __DIR__ . '/../team-management-system/config/app.php';
require_once __DIR__ . '/../team-management-system/config/database.php';
require_once __DIR__ . '/../team-management-system/includes/auth.php';

$resPin = authenticateUser('hr@company.com', '1234');
echo "PIN test for hr@company.com: " . json_encode($resPin) . "\n";

$resPass = authenticateUser('hr@company.com', 'Founder@123');
echo "Password test for hr@company.com: " . json_encode($resPass) . "\n";
