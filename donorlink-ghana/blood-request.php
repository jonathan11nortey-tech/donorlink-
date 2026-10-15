<?php

require_once "config/database.php";
require_once "config/session.php";

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Only requesters can create blood requests
|--------------------------------------------------------------------------
*/
if ($_SESSION["role"] !== "requester") {
    header("Location: dashboard.php");
    exit;
}

$requesterId = $_SESSION["user_id"];

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $bloodGroup = trim($_POST["blood_group"] ?? "");
    $unitsRequired = (int) ($_POST["units_required"] ?? 0);
    $hospitalName = trim($_POST["hospital_name"] ?? "");
    $hospitalLocation = trim($_POST["hospital_location"] ?? "");
    $latitude = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");
    $urgency = trim($_POST["urgency"] ?? "Normal");
    $reason = trim($_POST["reason"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Validate blood group
    |--------------------------------------------------------------------------
    */
    $allowedBloodGroups = [
        "A+", "A-", "B+", "B-",
        "AB+", "AB-", "O+", "O-"
    ];

    $allowedUrgency = [
        "Normal",
        "Urgent",
        "Critical"
    ];

    if (!in_array($bloodGroup, $allowedBloodGroups, true)) {

        $message = "Please select a valid blood group.";
        $messageType = "danger";

    } elseif ($unitsRequired < 1) {

        $message = "The number of units must be at least 1.";
        $messageType = "danger";

    } elseif (empty($hospitalName)) {

        $message = "Please enter the hospital name.";
        $messageType = "danger";

    } elseif (empty($hospitalLocation)) {

        $message = "Please enter the hospital location.";
        $messageType = "danger";

    } elseif (!in_array($urgency, $allowedUrgency, true)) {

        $message = "Please select a valid urgency level.";
        $messageType = "danger";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Convert empty coordinates to NULL
        |--------------------------------------------------------------------------
        */
        $latitudeValue = $latitude !== "" ? $latitude : null;
        $longitudeValue = $longitude !== "" ? $longitude : null;

        try {

            $sql = "
                INSERT INTO blood_requests (
                    requester_id,
                    blood_group,
                    units_required,
                    hospital_name,
                    hospital_location,
                    latitude,
                    longitude,
                    urgency,
                    reason
                )
                VALUES (
                    :requester_id,
                    :blood_group,
                    :units_required,
                    :hospital_name,
                    :hospital_location,
                    :latitude,
                    :longitude,
                    :urgency,
                    :reason
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":requester_id" => $requesterId,
                ":blood_group" => $bloodGroup,
                ":units_required" => $unitsRequired,
                ":hospital_name" => $hospitalName,
                ":hospital_location" => $hospitalLocation,
                ":latitude" => $latitudeValue,
                ":longitude" => $longitudeValue,
                ":urgency" => $urgency,
                ":reason" => $reason
            ]);

            $requestId = $pdo->lastInsertId();

            $message = "Blood request #{$requestId} has been submitted successfully.";
            $messageType = "success";

            /*
            |--------------------------------------------------------------------------
            | Clear form values after successful submission
            |--------------------------------------------------------------------------
            */
            $bloodGroup = "";
            $unitsRequired = "";
            $hospitalName = "";
            $hospitalLocation = "";
            $latitude = "";
            $longitude = "";
            $urgency = "Normal";
            $reason = "";

        } catch (PDOException $e) {

            $message = "Unable to submit the blood request. Please try again.";
            $messageType = "danger";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Blood Request | DonorLink Ghana</title>

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

<body class="auth-page">

<?php require_once "includes/navbar.php"; ?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <div class="auth-card">

                <div class="text-center mb-4">

                    <div class="auth-icon">
                        <i class="bi bi-droplet-fill"></i>
                    </div>

                    <h2 class="fw-bold mt-3">
                        Create Blood Request
                    </h2>

                    <p class="text-muted">
                        Provide the details below to request blood from available donors.
                    </p>

                </div>

                <?php if (!empty($message)): ?>

                    <div class="alert alert-<?php echo $messageType; ?>">

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>


                <form method="POST" action="">

                    <!-- Blood Information -->

                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-droplet me-2"></i>
                        Blood Information
                    </h5>

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Required Blood Group
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
                                        echo (($bloodGroup ?? "") === $group)
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        <?php echo $group; ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Number of Units Required
                            </label>

                            <input
                                type="number"
                                name="units_required"
                                class="form-control"
                                min="1"
                                value="<?php echo htmlspecialchars($unitsRequired ?? ""); ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- Hospital Information -->

                    <h5 class="fw-bold mt-4 mb-3">

                        <i class="bi bi-hospital me-2"></i>

                        Hospital Information

                    </h5>


                    <div class="mb-3">

                        <label class="form-label">
                            Hospital Name
                        </label>

                        <input
                            type="text"
                            name="hospital_name"
                            class="form-control"
                            placeholder="Enter hospital name"
                            value="<?php echo htmlspecialchars($hospitalName ?? ""); ?>"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Hospital Location
                        </label>

                        <input
                            type="text"
                            name="hospital_location"
                            class="form-control"
                            placeholder="Example: Accra, Greater Accra"
                            value="<?php echo htmlspecialchars($hospitalLocation ?? ""); ?>"
                            required
                        >

                    </div>


                    <!-- Location -->

                    <div class="mb-3">

                        <label class="form-label">

                            Request Location Coordinates

                            <small class="text-muted">
                                (Optional)
                            </small>

                        </label>

                        <div class="d-flex gap-2">

                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                id="getRequestLocation"
                            >

                                <i class="bi bi-geo-alt"></i>

                                Use My Current Location

                            </button>

                        </div>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Latitude
                            </label>

                            <input
                                type="text"
                                name="latitude"
                                id="requestLatitude"
                                class="form-control"
                                value="<?php echo htmlspecialchars($latitude ?? ""); ?>"
                                readonly
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Longitude
                            </label>

                            <input
                                type="text"
                                name="longitude"
                                id="requestLongitude"
                                class="form-control"
                                value="<?php echo htmlspecialchars($longitude ?? ""); ?>"
                                readonly
                            >

                        </div>

                    </div>


                    <!-- Urgency -->

                    <h5 class="fw-bold mt-4 mb-3">

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        Request Priority

                    </h5>


                    <div class="mb-3">

                        <label class="form-label">
                            Urgency
                        </label>

                        <select
                            name="urgency"
                            class="form-select"
                            required
                        >

                            <option
                                value="Normal"
                                <?php echo (($urgency ?? "Normal") === "Normal") ? "selected" : ""; ?>
                            >
                                Normal
                            </option>

                            <option
                                value="Urgent"
                                <?php echo (($urgency ?? "") === "Urgent") ? "selected" : ""; ?>
                            >
                                Urgent
                            </option>

                            <option
                                value="Critical"
                                <?php echo (($urgency ?? "") === "Critical") ? "selected" : ""; ?>
                            >
                                Critical
                            </option>

                        </select>

                    </div>


                    <!-- Reason -->

                    <div class="mb-4">

                        <label class="form-label">
                            Reason / Additional Information
                        </label>

                        <textarea
                            name="reason"
                            class="form-control"
                            rows="4"
                            placeholder="Provide additional information about the blood request..."
                        ><?php echo htmlspecialchars($reason ?? ""); ?></textarea>

                    </div>


                    <div class="d-flex gap-2">

                        <a
                            href="dashboard.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary flex-grow-1"
                        >

                            <i class="bi bi-send me-1"></i>

                            Submit Blood Request

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const locationButton =
        document.getElementById("getRequestLocation");

    const latitudeInput =
        document.getElementById("requestLatitude");

    const longitudeInput =
        document.getElementById("requestLongitude");


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
                        '<i class="bi bi-geo-alt"></i> Use My Current Location';

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