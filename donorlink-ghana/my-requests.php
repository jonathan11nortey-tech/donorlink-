<?php

require_once "config/database.php";
require_once "config/session.php";

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "requester") {
    header("Location: dashboard.php");
    exit;
}

$requesterId = $_SESSION["user_id"];

$sql = "
    SELECT
        id,
        blood_group,
        units_required,
        hospital_name,
        hospital_location,
        urgency,
        status,
        created_at
    FROM blood_requests
    WHERE requester_id = :requester_id
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ":requester_id" => $requesterId
]);

$requests = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Blood Requests | DonorLink Ghana</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>

<body>

<?php require_once "includes/navbar.php"; ?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">
                My Blood Requests
            </h2>

            <p class="text-muted mb-0">
                View and manage the blood requests you have submitted.
            </p>

        </div>

        <a
            href="blood-request.php"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-circle me-1"></i>
            New Request
        </a>

    </div>


    <?php if (empty($requests)): ?>

        <div class="alert alert-info">

            <i class="bi bi-info-circle me-2"></i>

            You have not submitted any blood requests yet.

        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($requests as $request): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card h-100 border-0 shadow-sm">

                        <div class="card-body">

                            <div class="d-flex justify-content-between mb-3">

                                <span class="badge bg-danger fs-6">

                                    <?php
                                    echo htmlspecialchars(
                                        $request["blood_group"]
                                    );
                                    ?>

                                </span>

                                <?php

                                $statusClass = "bg-secondary";

                                if ($request["status"] === "Pending") {
                                    $statusClass = "bg-warning text-dark";
                                } elseif ($request["status"] === "Matched") {
                                    $statusClass = "bg-primary";
                                } elseif ($request["status"] === "Fulfilled") {
                                    $statusClass = "bg-success";
                                } elseif ($request["status"] === "Cancelled") {
                                    $statusClass = "bg-dark";
                                }

                                ?>

                                <span class="badge <?php echo $statusClass; ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $request["status"]
                                    );
                                    ?>

                                </span>

                            </div>


                            <h5 class="fw-bold">

                                <?php
                                echo htmlspecialchars(
                                    $request["hospital_name"]
                                );
                                ?>

                            </h5>


                            <p class="text-muted mb-2">

                                <i class="bi bi-geo-alt me-2"></i>

                                <?php
                                echo htmlspecialchars(
                                    $request["hospital_location"]
                                );
                                ?>

                            </p>


                            <p class="mb-2">

                                <strong>
                                    Units:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $request["units_required"]
                                );
                                ?>

                            </p>


                            <p class="mb-3">

                                <strong>
                                    Urgency:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $request["urgency"]
                                );
                                ?>

                            </p>


                            <small class="text-muted d-block mb-3">

                                Submitted:

                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime($request["created_at"])
                                );
                                ?>

                            </small>


                            <a
                                href="request-matches.php?id=<?php echo $request["id"]; ?>"
                                class="btn btn-primary w-100"
                            >

                                <i class="bi bi-search me-1"></i>

                                Find Matching Donors

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>