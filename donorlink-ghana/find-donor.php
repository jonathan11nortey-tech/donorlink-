<?php

require_once "config/database.php";
require_once "config/session.php";

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Only requesters can search for donors
|--------------------------------------------------------------------------
*/
if ($_SESSION["role"] !== "requester") {
    header("Location: dashboard.php");
    exit;
}

$donors = [];
$message = "";

$selectedBloodGroup = "";
$searchLatitude = "";
$searchLongitude = "";
$searchLocation = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selectedBloodGroup = trim($_POST["blood_group"] ?? "");
    $searchLocation = trim($_POST["location"] ?? "");
    $searchLatitude = trim($_POST["latitude"] ?? "");
    $searchLongitude = trim($_POST["longitude"] ?? "");

    $allowedBloodGroups = [
        "A+", "A-",
        "B+", "B-",
        "AB+", "AB-",
        "O+", "O-"
    ];

    if (!in_array($selectedBloodGroup, $allowedBloodGroups, true)) {

        $message = "Please select a valid blood group.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get available donors with the requested blood group
        |--------------------------------------------------------------------------
        */
        $sql = "
            SELECT
                dp.id AS donor_profile_id,
                dp.user_id,
                dp.blood_group,
                dp.location,
                dp.latitude,
                dp.longitude,
                dp.availability,
                dp.last_donation_date,
                u.full_name,
                u.phone,
                u.email
            FROM donor_profiles dp
            INNER JOIN users u
                ON dp.user_id = u.id
            WHERE dp.blood_group = :blood_group
              AND dp.availability = 'Available'
              AND u.status = 'active'
            ORDER BY u.full_name ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":blood_group" => $selectedBloodGroup
        ]);

        $donors = $stmt->fetchAll();


        /*
        |--------------------------------------------------------------------------
        | Calculate distance when requester coordinates are available
        |--------------------------------------------------------------------------
        */
        if (
            $searchLatitude !== "" &&
            $searchLongitude !== "" &&
            is_numeric($searchLatitude) &&
            is_numeric($searchLongitude)
        ) {

            $requestLatitude = (float) $searchLatitude;
            $requestLongitude = (float) $searchLongitude;

            foreach ($donors as &$donor) {

                if (
                    $donor["latitude"] !== null &&
                    $donor["longitude"] !== null &&
                    is_numeric($donor["latitude"]) &&
                    is_numeric($donor["longitude"])
                ) {

                    $donorLatitude = (float) $donor["latitude"];
                    $donorLongitude = (float) $donor["longitude"];

                    /*
                    |--------------------------------------------------------------------------
                    | Haversine distance calculation
                    | Result is in kilometres
                    |--------------------------------------------------------------------------
                    */

                    $earthRadius = 6371;

                    $latDifference = deg2rad(
                        $donorLatitude - $requestLatitude
                    );

                    $lonDifference = deg2rad(
                        $donorLongitude - $requestLongitude
                    );

                    $a =
                        sin($latDifference / 2) *
                        sin($latDifference / 2)
                        +
                        cos(deg2rad($requestLatitude)) *
                        cos(deg2rad($donorLatitude)) *
                        sin($lonDifference / 2) *
                        sin($lonDifference / 2);

                    $c = 2 * atan2(
                        sqrt($a),
                        sqrt(1 - $a)
                    );

                    $distance = $earthRadius * $c;

                    $donor["distance"] = round($distance, 2);

                } else {

                    $donor["distance"] = null;

                }
            }

            unset($donor);


            /*
            |--------------------------------------------------------------------------
            | Sort donors by distance
            |--------------------------------------------------------------------------
            */
            usort(
                $donors,
                function ($a, $b) {

                    if ($a["distance"] === null) {
                        return 1;
                    }

                    if ($b["distance"] === null) {
                        return -1;
                    }

                    return $a["distance"] <=> $b["distance"];
                }
            );
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Find a Donor | DonorLink Ghana</title>

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

    <!-- Page Heading -->

    <div class="text-center mb-5">

        <div class="auth-icon mx-auto">

            <i class="bi bi-search"></i>

        </div>

        <h2 class="fw-bold mt-3">
            Find a Blood Donor
        </h2>

        <p class="text-muted">
            Search for available donors based on blood group and proximity.
        </p>

    </div>


    <!-- Search Form -->

    <div class="card border-0 shadow-sm mb-5">

        <div class="card-body p-4">

            <h5 class="fw-bold mb-4">

                <i class="bi bi-funnel me-2"></i>

                Donor Search

            </h5>


            <form method="POST" action="">

                <div class="row g-3">

                    <!-- Blood Group -->

                    <div class="col-md-4">

                        <label class="form-label">
                            Blood Group
                        </label>

                        <select
                            name="blood_group"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select blood group
                            </option>

                            <?php
                            $bloodGroups = [
                                "A+", "A-",
                                "B+", "B-",
                                "AB+", "AB-",
                                "O+", "O-"
                            ];
                            ?>

                            <?php foreach ($bloodGroups as $group): ?>

                                <option
                                    value="<?php echo $group; ?>"
                                    <?php
                                    echo ($selectedBloodGroup === $group)
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    <?php echo $group; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Location -->

                    <div class="col-md-4">

                        <label class="form-label">
                            Your Location
                        </label>

                        <input
                            type="text"
                            name="location"
                            class="form-control"
                            placeholder="Example: Accra"
                            value="<?php echo htmlspecialchars($searchLocation); ?>"
                        >

                    </div>


                    <!-- Coordinates -->

                    <div class="col-md-4">

                        <label class="form-label">
                            Search Coordinates
                        </label>

                        <button
                            type="button"
                            id="getSearchLocation"
                            class="btn btn-outline-primary w-100"
                        >

                            <i class="bi bi-geo-alt me-1"></i>

                            Use My Current Location

                        </button>

                    </div>

                </div>


                <!-- Hidden coordinates -->

                <input
                    type="hidden"
                    name="latitude"
                    id="searchLatitude"
                    value="<?php echo htmlspecialchars($searchLatitude); ?>"
                >

                <input
                    type="hidden"
                    name="longitude"
                    id="searchLongitude"
                    value="<?php echo htmlspecialchars($searchLongitude); ?>"
                >


                <div class="mt-4">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-search me-1"></i>

                        Search Available Donors

                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- Results -->

    <?php if ($_SERVER["REQUEST_METHOD"] === "POST"): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>

                <h4 class="fw-bold mb-1">
                    Matching Donors
                </h4>

                <p class="text-muted mb-0">

                    Blood group:
                    <strong>
                        <?php echo htmlspecialchars($selectedBloodGroup); ?>
                    </strong>

                </p>

            </div>

            <span class="badge bg-primary rounded-pill px-3 py-2">

                <?php echo count($donors); ?>

                donor(s)

            </span>

        </div>


        <?php if (empty($donors)): ?>

            <div class="alert alert-warning">

                <i class="bi bi-info-circle me-2"></i>

                No available donors were found for
                <strong>
                    <?php echo htmlspecialchars($selectedBloodGroup); ?>
                </strong>.

            </div>

        <?php else: ?>


            <div class="row g-4">

                <?php foreach ($donors as $donor): ?>

                    <div class="col-md-6 col-lg-4">

                        <div class="card h-100 border-0 shadow-sm">

                            <div class="card-body">

                                <!-- Donor icon -->

                                <div class="d-flex align-items-center mb-3">

                                    <div
                                        class="rounded-circle bg-light p-3 me-3"
                                    >

                                        <i
                                            class="bi bi-person-fill fs-4 text-primary"
                                        ></i>

                                    </div>

                                    <div>

                                        <h5 class="fw-bold mb-0">

                                            <?php
                                            echo htmlspecialchars(
                                                $donor["full_name"]
                                            );
                                            ?>

                                        </h5>

                                        <small class="text-success">

                                            <i class="bi bi-circle-fill"></i>

                                            Available

                                        </small>

                                    </div>

                                </div>


                                <!-- Blood group -->

                                <div class="mb-3">

                                    <span class="badge bg-danger fs-6">

                                        <?php
                                        echo htmlspecialchars(
                                            $donor["blood_group"]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <!-- Location -->

                                <p class="mb-2">

                                    <i class="bi bi-geo-alt text-primary me-2"></i>

                                    <?php
                                    echo htmlspecialchars(
                                        $donor["location"]
                                    );
                                    ?>

                                </p>


                                <!-- Distance -->

                                <?php if ($donor["distance"] !== null): ?>

                                    <p class="mb-2">

                                        <i class="bi bi-signpost-2 text-primary me-2"></i>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $donor["distance"]
                                            );
                                            ?>
                                            km
                                        </strong>

                                        away

                                    </p>

                                <?php else: ?>

                                    <p class="text-muted mb-2">

                                        <i class="bi bi-signpost-2 me-2"></i>

                                        Distance unavailable

                                    </p>

                                <?php endif; ?>


                                <!-- Phone -->

                                <p class="mb-3">

                                    <i class="bi bi-telephone text-primary me-2"></i>

                                    <?php
                                    echo htmlspecialchars(
                                        $donor["phone"]
                                    );
                                    ?>

                                </p>


                                <hr>


                                <!-- Contact button -->

                                <a
                                    href="tel:<?php echo htmlspecialchars($donor["phone"]); ?>"
                                    class="btn btn-outline-primary w-100"
                                >

                                    <i class="bi bi-telephone me-1"></i>

                                    Contact Donor

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const locationButton =
        document.getElementById("getSearchLocation");

    const latitudeInput =
        document.getElementById("searchLatitude");

    const longitudeInput =
        document.getElementById("searchLongitude");


    if (locationButton) {

        locationButton.addEventListener("click", function () {

            if (!navigator.geolocation) {

                alert(
                    "Geolocation is not supported by your browser."
                );

                return;
            }


            locationButton.disabled = true;

            locationButton.innerHTML =
                '<i class="bi bi-hourglass-split"></i> Getting location...';


            navigator.geolocation.getCurrentPosition(

                function (position) {

                    latitudeInput.value =
                        position.coords.latitude;

                    longitudeInput.value =
                        position.coords.longitude;


                    locationButton.disabled = false;

                    locationButton.innerHTML =
                        '<i class="bi bi-check-circle"></i> Location Captured';

                },

                function () {

                    locationButton.disabled = false;

                    locationButton.innerHTML =
                        '<i class="bi bi-geo-alt me-1"></i> Use My Current Location';

                    alert(
                        "Unable to get your location. Please allow location access and try again."
                    );

                }

            );

        });

    }

});

</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>