
<?php
require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";

// This page is for requesters only.
if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true ||
    ($_SESSION["role"] ?? "") !== "requester"
) {
    header("Location: login.php");
    exit;
}

// Validate the blood request ID from the URL.
$requestId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$requestId || $requestId < 1) {
    $_SESSION["flash_message"] = [
        "type" => "danger",
        "text" => "Please select a valid blood request."
    ];
    header("Location: my-requests.php");
    exit;
}

$requesterId = (int) $_SESSION["user_id"];

// Make a CSRF token for the invitation forms.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];

$flash = $_SESSION["flash_message"] ?? null;
unset($_SESSION["flash_message"]);

try {
    // Only allow the requester to view their own blood request.
    $requestStmt = $pdo->prepare("
        SELECT id, blood_group, units_required, hospital_name,
               hospital_location, latitude, longitude,
               urgency, status, created_at
        FROM blood_requests
        WHERE id = :request_id
          AND requester_id = :requester_id
        LIMIT 1
    ");

    $requestStmt->execute([
        "request_id" => $requestId,
        "requester_id" => $requesterId
    ]);

    $bloodRequest = $requestStmt->fetch();

    if (!$bloodRequest) {
        http_response_code(404);
        exit("Blood request not found, or you do not have permission to view it.");
    }

    // Find active, available donors with the requested recorded blood group.
    // Include any invitation status for this donor and request.
    $donorStmt = $pdo->prepare("
        SELECT
            u.id AS donor_id,
            u.full_name,
            dp.blood_group,
            dp.location,
            dp.latitude,
            dp.longitude,
            dp.availability,
            drr.response_status,
            drr.created_at AS invitation_date,
            drr.responded_at
        FROM donor_profiles dp
        INNER JOIN users u ON u.id = dp.user_id
        LEFT JOIN donor_request_responses drr
            ON drr.donor_id = u.id
            AND drr.blood_request_id = :request_id
        WHERE u.role = 'donor'
          AND u.status = 'active'
          AND dp.availability = 'Available'
          AND dp.blood_group = :blood_group
        ORDER BY u.full_name ASC
    ");

    $donorStmt->execute([
        "request_id" => $requestId,
        "blood_group" => $bloodRequest["blood_group"]
    ]);

    $donors = $donorStmt->fetchAll();

    // Calculate approximate distance when both locations have coordinates.
    $requestLat = $bloodRequest["latitude"];
    $requestLon = $bloodRequest["longitude"];

    foreach ($donors as &$donor) {
        $donor["distance_km"] = null;

        if (
            $requestLat !== null &&
            $requestLon !== null &&
            $donor["latitude"] !== null &&
            $donor["longitude"] !== null
        ) {
            $lat1 = deg2rad((float) $requestLat);
            $lon1 = deg2rad((float) $requestLon);
            $lat2 = deg2rad((float) $donor["latitude"]);
            $lon2 = deg2rad((float) $donor["longitude"]);

            $latDifference = $lat2 - $lat1;
            $lonDifference = $lon2 - $lon1;

            $a = sin($latDifference / 2) ** 2
                + cos($lat1) * cos($lat2)
                * sin($lonDifference / 2) ** 2;

            $a = max(0, min(1, $a));

            $donor["distance_km"] = 6371 * 2 * asin(sqrt($a));
        }
    }
    unset($donor);

    // Sort donors with known distances first, nearest to farthest.
    usort($donors, function ($a, $b) {
        if ($a["distance_km"] === null && $b["distance_km"] === null) {
            return strcmp($a["full_name"], $b["full_name"]);
        }

        if ($a["distance_km"] === null) {
            return 1;
        }

        if ($b["distance_km"] === null) {
            return -1;
        }

        return $a["distance_km"] <=> $b["distance_km"];
    });

} catch (PDOException $e) {
    error_log("DonorLink request matches error: " . $e->getMessage());
    http_response_code(500);
    exit("Something went wrong while loading donor matches. Please try again later.");
}

function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$canInvite = in_array(
    $bloodRequest["status"],
    ["Pending", "Matched"],
    true
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donor Matches | DonorLink Ghana</title>

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
        <div class="alert alert-<?php echo escapeHtml($flash["type"]); ?> alert-dismissible fade show">
            <?php echo escapeHtml($flash["text"]); ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Donor Matches</h1>
            <p class="text-muted mb-0">
                Review donors for your blood request and send invitations.
            </p>
        </div>

        <a href="my-requests.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> My Requests
        </a>
    </div>

    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <span class="text-muted small">Requested blood group</span>
                    <div class="display-6 fw-bold text-danger">
                        <?php echo escapeHtml($bloodRequest["blood_group"]); ?>
                    </div>
                </div>

                <div>
                    <span class="text-muted small">Units required</span>
                    <div class="h4">
                        <?php echo (int) $bloodRequest["units_required"]; ?>
                    </div>
                </div>

                <div>
                    <span class="text-muted small">Urgency</span>
                    <div>
                        <span class="badge <?php
                            echo $bloodRequest["urgency"] === "Critical"
                                ? "text-bg-danger"
                                : ($bloodRequest["urgency"] === "Urgent"
                                    ? "text-bg-warning"
                                    : "text-bg-secondary");
                        ?>">
                            <?php echo escapeHtml($bloodRequest["urgency"]); ?>
                        </span>
                    </div>
                </div>

                <div>
                    <span class="text-muted small">Request status</span>
                    <div>
                        <span class="badge text-bg-info">
                            <?php echo escapeHtml($bloodRequest["status"]); ?>
                        </span>
                    </div>
                </div>
            </div>

            <hr>

            <p class="mb-1">
                <strong>Hospital:</strong>
                <?php echo escapeHtml($bloodRequest["hospital_name"]); ?>
            </p>
            <p class="mb-0">
                <strong>Location:</strong>
                <?php echo escapeHtml($bloodRequest["hospital_location"]); ?>
            </p>

            <?php if (!$canInvite): ?>
                <div class="alert alert-secondary mt-3 mb-0">
                    This request is no longer open for new donor invitations.
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Matching donors</h2>
        <span class="badge text-bg-secondary">
            <?php echo count($donors); ?> found
        </span>
    </div>

    <?php if (empty($donors)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-search display-5 text-muted"></i>
                <h3 class="h5 mt-3">No matching donors found</h3>
                <p class="text-muted mb-0">
                    No active, available donor with this recorded blood group
                    is currently listed. You can check again later.
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="row g-3">
            <?php foreach ($donors as $donor): ?>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <h3 class="h5 mb-1">
                                        <?php echo escapeHtml($donor["full_name"]); ?>
                                    </h3>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?php echo escapeHtml($donor["location"]); ?>
                                    </p>
                                </div>

                                <div class="text-center">
                                    <span class="badge text-bg-danger fs-6">
                                        <?php echo escapeHtml($donor["blood_group"]); ?>
                                    </span>
                                    <div class="small text-success mt-2">
                                        Available
                                    </div>
                                </div>
                            </div>

                            <p class="small text-muted">
                                <?php if ($donor["distance_km"] !== null): ?>
                                    <i class="bi bi-signpost-2 me-1"></i>
                                    Approx. <?php
                                        echo number_format($donor["distance_km"], 1);
                                    ?> km from the request location
                                <?php else: ?>
                                    <i class="bi bi-info-circle me-1"></i>
                                    Distance unavailable because coordinates are missing.
                                <?php endif; ?>
                            </p>

                            <?php if ($donor["response_status"] !== null): ?>

                                <?php
                                $responseStatus = $donor["response_status"];
                                $statusClass = [
                                    "Pending" => "text-bg-warning",
                                    "Accepted" => "text-bg-success",
                                    "Declined" => "text-bg-secondary"
                                ][$responseStatus] ?? "text-bg-secondary";
                                ?>

                                <div class="mb-3">
                                    <span class="text-muted small">Invitation status:</span>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo escapeHtml($responseStatus); ?>
                                    </span>
                                </div>

                                <?php if ($responseStatus === "Accepted"): ?>
                                    <p class="small text-success mb-0">
                                        This donor has accepted the invitation.
                                        Please coordinate through the appropriate
                                        donation service and confirm eligibility
                                        with qualified medical personnel.
                                    </p>
                                <?php elseif ($responseStatus === "Declined"): ?>
                                    <p class="small text-muted mb-0">
                                        This donor declined the invitation.
                                    </p>
                                <?php else: ?>
                                    <p class="small text-muted mb-0">
                                        Waiting for the donor to respond.
                                    </p>
                                <?php endif; ?>

                            <?php elseif ($canInvite): ?>

                                <form action="send-donor-request.php" method="POST"
                                      onsubmit="return confirm('Send a donation invitation to this donor?');">

                                    <input type="hidden" name="request_id"
                                           value="<?php echo (int) $requestId; ?>">

                                    <input type="hidden" name="donor_id"
                                           value="<?php echo (int) $donor["donor_id"]; ?>">

                                    <input type="hidden" name="csrf_token"
                                           value="<?php echo escapeHtml($csrfToken); ?>">

                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="bi bi-send me-1"></i>
                                        Send Donor Request
                                    </button>
                                </form>

                            <?php else: ?>

                                <button class="btn btn-outline-secondary w-100" disabled>
                                    Request Closed
                                </button>

                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <div class="alert alert-light border mt-4 small">
        <strong>Important:</strong> Matches are based on the donor's recorded
        blood group and availability. They do not confirm medical compatibility
        or eligibility. A healthcare professional must confirm both.
        Approximate distance is available only when both locations have coordinates.
    </div>

</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>