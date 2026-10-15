
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// Only logged-in requesters may send donor invitations.
if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true ||
    ($_SESSION["role"] ?? "") !== "requester"
) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed. Please submit the donor request form.");
}


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
        "text" => "Your session security check failed. Please try again."
    ];

    header("Location: my-requests.php");
    exit;
}


$requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
$donorId = filter_input(INPUT_POST, "donor_id", FILTER_VALIDATE_INT);
$requesterId = (int) $_SESSION["user_id"];

if (!$requestId || !$donorId || $requestId < 1 || $donorId < 1) {
    $_SESSION["flash_message"] = [
        "type" => "danger",
        "text" => "Invalid blood request or donor selected."
    ];

    header("Location: my-requests.php");
    exit;
}

try {
    // Confirm that this blood request belongs to the logged-in requester.
    $requestStmt = $pdo->prepare("
        SELECT id, blood_group, hospital_name, urgency, status
        FROM blood_requests
        WHERE id = :request_id
          AND requester_id = :requester_id
          AND status IN ('Pending', 'Matched')
        LIMIT 1
    ");

    $requestStmt->execute([
        "request_id" => $requestId,
        "requester_id" => $requesterId
    ]);

    $bloodRequest = $requestStmt->fetch();

    if (!$bloodRequest) {
        $_SESSION["flash_message"] = [
            "type" => "danger",
            "text" => "The blood request was not found, is not yours, or is no longer active."
        ];

        header("Location: my-requests.php");
        exit;
    }

    // Confirm that the selected user is an active, available donor
    // whose recorded blood group matches this request.
    $donorStmt = $pdo->prepare("
        SELECT u.id, u.full_name, dp.blood_group
        FROM users u
        INNER JOIN donor_profiles dp ON dp.user_id = u.id
        WHERE u.id = :donor_id
          AND u.role = 'donor'
          AND u.status = 'active'
          AND dp.availability = 'Available'
          AND dp.blood_group = :blood_group
        LIMIT 1
    ");

    $donorStmt->execute([
        "donor_id" => $donorId,
        "blood_group" => $bloodRequest["blood_group"]
    ]);

    $donor = $donorStmt->fetch();

    if (!$donor) {
        $_SESSION["flash_message"] = [
            "type" => "warning",
            "text" => "This donor is not currently available or does not match the recorded blood group."
        ];

        header("Location: request-matches.php?id=" . $requestId);
        exit;
    }

    // Do not send the same invitation twice.
    $duplicateStmt = $pdo->prepare("
        SELECT id
        FROM donor_request_responses
        WHERE blood_request_id = :request_id
          AND donor_id = :donor_id
        LIMIT 1
    ");

    $duplicateStmt->execute([
        "request_id" => $requestId,
        "donor_id" => $donorId
    ]);

    if ($duplicateStmt->fetch()) {
        $_SESSION["flash_message"] = [
            "type" => "info",
            "text" => "You have already sent an invitation to this donor for this request."
        ];

        header("Location: request-matches.php?id=" . $requestId);
        exit;
    }

    // Save the invitation and notification together.
    $pdo->beginTransaction();

    $responseStmt = $pdo->prepare("
        INSERT INTO donor_request_responses
            (blood_request_id, donor_id, response_status)
        VALUES
            (:request_id, :donor_id, 'Pending')
    ");

    $responseStmt->execute([
        "request_id" => $requestId,
        "donor_id" => $donorId
    ]);

    $notificationTitle = "New Blood Donation Request";

    $notificationMessage =
        "A requester needs blood group " .
        $bloodRequest["blood_group"] .
        " at " . $bloodRequest["hospital_name"] .
        ". Urgency: " . $bloodRequest["urgency"] .
        ". Please review the request and respond.";

    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, title, message, notification_type, related_request_id)
        VALUES
            (:user_id, :title, :message, 'donor_request', :request_id)
    ");

    $notificationStmt->execute([
        "user_id" => $donorId,
        "title" => $notificationTitle,
        "message" => $notificationMessage,
        "request_id" => $requestId
    ]);

    $pdo->commit();

    $_SESSION["flash_message"] = [
        "type" => "success",
        "text" => "Your invitation has been sent to " .
                  $donor["full_name"] . "."
    ];

    header("Location: request-matches.php?id=" . $requestId);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("DonorLink send request error: " . $e->getMessage());

    $_SESSION["flash_message"] = [
        "type" => "danger",
        "text" => "We could not send the invitation. Please try again."
    ];

    header("Location: request-matches.php?id=" . $requestId);
    exit;
}
