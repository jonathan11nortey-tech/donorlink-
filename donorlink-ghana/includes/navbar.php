
<?php
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

$unreadNotificationCount = 0;

if (
    isset($_SESSION["logged_in"]) &&
    $_SESSION["logged_in"] === true &&
    in_array($_SESSION["role"] ?? "", ["donor", "requester"], true)
) {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ");

    $countStmt->execute([
        "user_id" => (int) $_SESSION["user_id"]
    ]);

    $unreadNotificationCount = (int) $countStmt->fetchColumn();
}
?>


<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            <span class="text-danger">DonorLink</span> Ghana
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <ul class="navbar-nav ms-auto align-items-lg-center">

              <?php if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
): ?>

    <li class="nav-item">
        <a class="nav-link" href="index.php">Home</a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="index.php#how-it-works">
            How It Works
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="index.php#about">
            About
        </a>
    </li>

<?php endif; ?>
                <?php if (
                    isset($_SESSION["logged_in"]) &&
                    $_SESSION["logged_in"] === true
                ): ?>

                    <!-- Links for authenticated users -->
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            Dashboard
                        </a>
                    </li>

                    <?php if (($_SESSION["role"] ?? "") === "requester"): ?>

                        <li class="nav-item">
                            <a class="nav-link" href="blood-request.php">
                                Create Request
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="my-requests.php">
                                My Requests
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="find-donor.php">
                                Find a Donor
                            </a>
                        </li>

                    <?php elseif (($_SESSION["role"] ?? "") === "donor"): ?>

                        <li class="nav-item">
                            <a class="nav-link" href="donor-profile.php">
                                My Donor Profile
                            </a>
                        </li>

                          <li class="nav-item">
        <a class="nav-link" href="donor-responses.php">Donor Invitations</a>
    </li>

                    <?php endif; ?>

                    <?php if (
                        in_array(
                            $_SESSION["role"] ?? "",
                            ["donor", "requester"],
                            true
                        )
                    ): ?>

                       
<li class="nav-item">
    <a class="nav-link d-flex align-items-center gap-2"
       href="notifications.php">
        <span>
            <i class="bi bi-bell me-1"></i>
            Notifications
        </span>

        <span
            id="notificationBadge"
            class="badge rounded-pill text-bg-danger"
            <?php echo $unreadNotificationCount === 0 ? 'hidden' : ''; ?>
        >
            <?php echo $unreadNotificationCount > 99
                ? '99+'
                : $unreadNotificationCount; ?>
        </span>
    </a>
</li>


                    <?php endif; ?>

                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-danger btn-sm"
                           href="logout.php">
                            Logout
                        </a>
                    </li>

                <?php else: ?>

                    <!-- Links for visitors -->
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>

                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-danger btn-sm text-white"
                           href="register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>
    </div>
</nav>



<script>
document.addEventListener("DOMContentLoaded", function () {
    const badge = document.getElementById("notificationBadge");

    if (!badge) return;

    async function updateNotificationCount() {
        try {
            const response = await fetch("notification-count.php", {
                cache: "no-store",
                credentials: "same-origin"
            });

            if (!response.ok) return;

            const data = await response.json();
            const count = Number(data.count) || 0;

            badge.textContent = count > 99 ? "99+" : String(count);
            badge.hidden = count === 0;
        } catch (error) {
            // Keep the last displayed count if a check fails.
        }
    }

    updateNotificationCount();
    window.setInterval(updateNotificationCount, 15000);
});
</script>
