
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["donor", "requester"], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

// Process requests to mark notifications as opened.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !is_string($sessionToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        $_SESSION["flash_message"] = [
            "type" => "danger",
            "text" => "Security verification failed. Please try again."
        ];

        header("Location: notifications.php");
        exit;
    }

    $action = $_POST["action"] ?? "";

    if ($action === "mark_opened") {
        $notificationId = filter_input(
            INPUT_POST,
            "notification_id",
            FILTER_VALIDATE_INT
        );

        if ($notificationId && $notificationId > 0) {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = :notification_id
                  AND user_id = :user_id
            ");

            $stmt->execute([
                "notification_id" => $notificationId,
                "user_id" => $userId
            ]);

            $_SESSION["flash_message"] = [
                "type" => "success",
                "text" => "Notification marked as opened."
            ];
        }
    } elseif ($action === "mark_all_opened") {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = :user_id
              AND is_read = 0
        ");

        $stmt->execute(["user_id" => $userId]);

        $_SESSION["flash_message"] = [
            "type" => "success",
            "text" => "All notifications have been marked as opened."
        ];
    }

    header("Location: notifications.php");
    exit;
}

$flash = $_SESSION["flash_message"] ?? null;
unset($_SESSION["flash_message"]);

function escapeNotificationHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Retrieve only the logged-in user's notifications.
$stmt = $pdo->prepare("
    SELECT id, title, message, notification_type,
           related_request_id, is_read, created_at
    FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC, id DESC
");

$stmt->execute(["user_id" => $userId]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;

foreach ($notifications as $notification) {
    if ((int) $notification["is_read"] === 0) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Notifications | DonorLink Ghana</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="bg-light">

<?php require_once __DIR__ . "/includes/navbar.php"; ?>

<main class="container py-5">

    <?php if ($flash): ?>
        <div class="alert alert-<?php
            echo escapeNotificationHtml($flash["type"]);
        ?> alert-dismissible fade show" role="alert">

            <?php echo escapeNotificationHtml($flash["text"]); ?>

            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Notifications</h1>
            <p class="text-muted mb-0">
                View updates about blood requests and donor responses.
            </p>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form method="POST" action="notifications.php">
                <input type="hidden" name="csrf_token"
                       value="<?php echo escapeNotificationHtml($csrfToken); ?>">

                <input type="hidden" name="action"
                       value="mark_all_opened">

                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-check2-all me-1"></i>
                    Mark All as Opened
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <span class="badge text-bg-danger">
            <?php echo $unreadCount; ?> unread
        </span>
    </div>

    <?php if (empty($notifications)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-bell display-4 text-muted"></i>
                <h2 class="h5 mt-3">No notifications yet</h2>
                <p class="text-muted mb-0">
                    New notifications will appear here when there are updates.
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="d-flex flex-column gap-3">

            <?php foreach ($notifications as $notification): ?>

                <?php
                $isRead = (int) $notification["is_read"] === 1;

                $destination = null;

                if (!empty($notification["related_request_id"])) {
                    $relatedId = (int) $notification["related_request_id"];

                    if ($role === "donor") {
                        $destination = "donor-responses.php";
                    } elseif ($role === "requester") {
                        $destination = "request-matches.php?id=" . $relatedId;
                    }
                }
                ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start gap-3">

                            <div class="flex-grow-1">
                                <h2 class="h5 mb-2">
                                    <i class="bi bi-bell me-2 text-danger"></i>
                                    <?php echo escapeNotificationHtml(
                                        $notification["title"]
                                    ); ?>
                                </h2>

                                <p class="mb-2">
                                    <?php echo nl2br(
                                        escapeNotificationHtml(
                                            $notification["message"]
                                        )
                                    ); ?>
                                </p>

                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo escapeNotificationHtml(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($notification["created_at"])
                                        )
                                    ); ?>
                                </small>
                            </div>

                            <span class="badge <?php
                                echo $isRead
                                    ? "text-bg-secondary"
                                    : "text-bg-danger";
                            ?>">
                                <?php echo $isRead ? "Opened" : "New"; ?>
                            </span>

                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">

                            <?php if (!$isRead): ?>

                                <form method="POST" action="notifications.php">
                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo escapeNotificationHtml($csrfToken); ?>">

                                    <input type="hidden" name="action"
                                           value="mark_opened">

                                    <input type="hidden" name="notification_id"
                                           value="<?php echo (int) $notification["id"]; ?>">

                                    <button type="submit"
                                            class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-envelope-open me-1"></i>
                                        Mark as Opened
                                    </button>
                                </form>

                            <?php endif; ?>

                            <?php if ($destination !== null): ?>
                                <a href="<?php echo escapeNotificationHtml($destination); ?>"
                                   class="btn btn-sm btn-outline-danger">
                                    View Related Request
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            <?php endif; ?>

                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
