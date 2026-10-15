
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// Only logged-in donors can view and respond to invitations.
if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true ||
    ($_SESSION["role"] ?? "") !== "donor"
) {
    header("Location: login.php");
    exit;
}

$donorId = (int) $_SESSION["user_id"];

// Create a CSRF token for the response forms.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$flash = null;

// Process an Accept or Decline submission.
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

        header("Location: donor-responses.php");
        exit;
    }

    $responseId = filter_input(
        INPUT_POST,
        "response_id",
        FILTER_VALIDATE_INT
    );

    $action = $_POST["action"] ?? "";

    if (
        !$responseId ||
        $responseId < 1 ||
        !in_array($action, ["Accepted", "Declined"], true)
    ) {
        $_SESSION["flash_message"] = [
            "type" => "danger",
            "text" => "Invalid response submitted."
        ];

        header("Location: donor-responses.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Lock and verify this invitation belongs to the logged-in donor.
        $checkStmt = $pdo->prepare("
            SELECT
                drr.id,
                drr.blood_request_id,
                drr.response_status,
                br.requester_id,
                br.status AS request_status,
                br.blood_group,
                br.hospital_name
            FROM donor_request_responses drr
            INNER JOIN blood_requests br
                ON br.id = drr.blood_request_id
            WHERE drr.id = :response_id
              AND drr.donor_id = :donor_id
            LIMIT 1
            FOR UPDATE
        ");

        $checkStmt->execute([
            "response_id" => $responseId,
            "donor_id" => $donorId
        ]);

        $invitation = $checkStmt->fetch();

        if (!$invitation) {
            $pdo->rollBack();

            $_SESSION["flash_message"] = [
                "type" => "danger",
                "text" => "Invitation not found or you are not authorised to respond."
            ];

            header("Location: donor-responses.php");
            exit;
        }

        if ($invitation["response_status"] !== "Pending") {
            $pdo->rollBack();

            $_SESSION["flash_message"] = [
                "type" => "info",
                "text" => "You have already responded to this invitation."
            ];

            header("Location: donor-responses.php");
            exit;
        }

        if (!in_array(
            $invitation["request_status"],
            ["Pending", "Matched"],
            true
        )) {
            $pdo->rollBack();

            $_SESSION["flash_message"] = [
                "type" => "warning",
                "text" => "This blood request is no longer active."
            ];

            header("Location: donor-responses.php");
            exit;
        }

        // Update only this donor's pending invitation.
        $updateStmt = $pdo->prepare("
            UPDATE donor_request_responses
            SET response_status = :response_status,
                responded_at = CURRENT_TIMESTAMP
            WHERE id = :response_id
              AND donor_id = :donor_id
              AND response_status = 'Pending'
        ");

        $updateStmt->execute([
            "response_status" => $action,
            "response_id" => $responseId,
            "donor_id" => $donorId
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException(
                "The invitation could not be updated."
            );
        }

        // An accepted invitation changes the request to Matched.
        // This records a response, not confirmed medical compatibility
        // or completed blood donation.
        if ($action === "Accepted") {
            $requestUpdateStmt = $pdo->prepare("
                UPDATE blood_requests
                SET status = 'Matched'
                WHERE id = :request_id
                  AND status = 'Pending'
            ");

            $requestUpdateStmt->execute([
                "request_id" => $invitation["blood_request_id"]
            ]);
        }

        // Notify the requester about the donor's decision.
        $title = $action === "Accepted"
            ? "Donor Accepted Your Invitation"
            : "Donor Declined Your Invitation";

        $message = $action === "Accepted"
            ? "A donor has accepted your invitation for blood group "
                . $invitation["blood_group"] . " at "
                . $invitation["hospital_name"]
                . ". Please coordinate with the appropriate healthcare facility."
            : "A donor has declined your invitation for blood group "
                . $invitation["blood_group"] . " at "
                . $invitation["hospital_name"]
                . ". You may review other available donors.";

        $notificationStmt = $pdo->prepare("
            INSERT INTO notifications
                (user_id, title, message, notification_type, related_request_id)
            VALUES
                (:user_id, :title, :message, 'donor_response', :request_id)
        ");

        $notificationStmt->execute([
            "user_id" => $invitation["requester_id"],
            "title" => $title,
            "message" => $message,
            "request_id" => $invitation["blood_request_id"]
        ]);

        $pdo->commit();

        $_SESSION["flash_message"] = [
            "type" => "success",
            "text" => $action === "Accepted"
                ? "You accepted the invitation. The requester has been notified."
                : "You declined the invitation. The requester has been notified."
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("DonorLink donor response error: " . $e->getMessage());

        $_SESSION["flash_message"] = [
            "type" => "danger",
            "text" => "We could not save your response. Please try again."
        ];
    }

    header("Location: donor-responses.php");
    exit;
}

$flash = $_SESSION["flash_message"] ?? null;
unset($_SESSION["flash_message"]);

function escapeResponseHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

try {
    // Show only invitations addressed to this donor.
    $stmt = $pdo->prepare("
        SELECT
            drr.id AS response_id,
            drr.response_status,
            drr.created_at AS invitation_date,
            drr.responded_at,
            br.id AS request_id,
            br.blood_group,
            br.units_required,
            br.hospital_name,
            br.hospital_location,
            br.urgency,
            br.reason,
            br.status AS request_status,
            br.created_at AS request_date,
            u.full_name AS requester_name
        FROM donor_request_responses drr
        INNER JOIN blood_requests br
            ON br.id = drr.blood_request_id
        INNER JOIN users u
            ON u.id = br.requester_id
        WHERE drr.donor_id = :donor_id
        ORDER BY
            CASE drr.response_status
                WHEN 'Pending' THEN 0
                WHEN 'Accepted' THEN 1
                ELSE 2
            END,
            drr.created_at DESC
    ");

    $stmt->execute(["donor_id" => $donorId]);
    $invitations = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("DonorLink invitation list error: " . $e->getMessage());
    http_response_code(500);
    exit("Could not load donor invitations. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Donor Responses | DonorLink Ghana</title>

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
            echo escapeResponseHtml($flash["type"]);
        ?> alert-dismissible fade show" role="alert">
            <?php echo escapeResponseHtml($flash["text"]); ?>

            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-4">
        <h1 class="h3">Donor Invitations</h1>
        <p class="text-muted">
            Review blood donation invitations and respond when you are able.
        </p>
    </div>

    <?php if (empty($invitations)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <h2 class="h5 mt-3">No invitations yet</h2>
                <p class="text-muted mb-0">
                    When a requester sends you a donor invitation,
                    it will appear here.
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="row g-3">

            <?php foreach ($invitations as $invitation): ?>

                <?php
                $status = $invitation["response_status"];

                $statusClass = [
                    "Pending" => "text-bg-warning",
                    "Accepted" => "text-bg-success",
                    "Declined" => "text-bg-secondary"
                ][$status] ?? "text-bg-secondary";
                ?>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between gap-3 mb-3">
                                <div>
                                    <h2 class="h5 mb-1">
                                        <?php echo escapeResponseHtml(
                                            $invitation["requester_name"]
                                        ); ?>
                                    </h2>

                                    <p class="small text-muted mb-0">
                                        Invitation received:
                                        <?php echo escapeResponseHtml(
                                            date(
                                                "d M Y, H:i",
                                                strtotime($invitation["invitation_date"])
                                            )
                                        ); ?>
                                    </p>
                                </div>

                                <span class="badge <?php echo $statusClass; ?> align-self-start">
                                    <?php echo escapeResponseHtml($status); ?>
                                </span>
                            </div>

                            <div class="rounded bg-light p-3 mb-3">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <span class="small text-muted">Blood group</span>
                                        <div class="fw-bold text-danger fs-5">
                                            <?php echo escapeResponseHtml(
                                                $invitation["blood_group"]
                                            ); ?>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <span class="small text-muted">Units requested</span>
                                        <div class="fw-semibold">
                                            <?php echo (int) $invitation["units_required"]; ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <span class="small text-muted">Hospital</span>
                                        <div class="fw-semibold">
                                            <?php echo escapeResponseHtml(
                                                $invitation["hospital_name"]
                                            ); ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo escapeResponseHtml(
                                                $invitation["hospital_location"]
                                            ); ?>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <span class="small text-muted">Urgency</span>
                                        <div>
                                            <?php echo escapeResponseHtml(
                                                $invitation["urgency"]
                                            ); ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($invitation["reason"])): ?>
                                        <div class="col-12">
                                            <span class="small text-muted">Additional information</span>
                                            <p class="mb-0">
                                                <?php echo nl2br(
                                                    escapeResponseHtml(
                                                        $invitation["reason"]
                                                    )
                                                ); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($status === "Pending"): ?>

                                <?php if (in_array(
                                    $invitation["request_status"],
                                    ["Pending", "Matched"],
                                    true
                                )): ?>

                                    <form method="POST"
                                          action="donor-responses.php"
                                          class="d-flex flex-wrap gap-2">

                                        <input type="hidden" name="csrf_token"
                                               value="<?php echo escapeResponseHtml($csrfToken); ?>">

                                        <input type="hidden" name="response_id"
                                               value="<?php echo (int) $invitation["response_id"]; ?>">

                                        <button type="submit" name="action"
                                                value="Accepted"
                                                class="btn btn-success flex-fill"
                                                onclick="return confirm('Accept this invitation?');">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Accept
                                        </button>

                                        <button type="submit" name="action"
                                                value="Declined"
                                                class="btn btn-outline-danger flex-fill"
                                                onclick="return confirm('Decline this invitation?');">
                                            <i class="bi bi-x-circle me-1"></i>
                                            Decline
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <p class="small text-muted mb-0">
                                        This blood request is no longer active,
                                        so no response can be submitted.
                                    </p>

                                <?php endif; ?>

                            <?php elseif ($status === "Accepted"): ?>

                                <div class="alert alert-success mb-0">
                                    You accepted this invitation.
                                    Please coordinate with the appropriate
                                    healthcare facility. Acceptance does not
                                    confirm medical eligibility or a completed donation.
                                </div>

                            <?php else: ?>

                                <div class="alert alert-secondary mb-0">
                                    You declined this invitation.
                                </div>

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
