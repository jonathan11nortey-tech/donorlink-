
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true ||
    !in_array($_SESSION["role"] ?? "", ["donor", "requester"], true)
) {
    http_response_code(401);
    echo json_encode(["count" => 0]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = :user_id
      AND is_read = 0
");

$stmt->execute([
    "user_id" => (int) $_SESSION["user_id"]
]);

echo json_encode([
    "count" => (int) $stmt->fetchColumn()
]);
