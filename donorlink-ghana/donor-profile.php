<?php

require_once "config/database.php";
require_once "config/session.php";


// Make sure the user is logged in
if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {
    header("Location: login.php");
    exit;
}


$userId = $_SESSION["user_id"];

if ($_SESSION["role"] !== "donor") {

    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";


// Get existing donor profile
$stmt = $pdo->prepare(
    "SELECT *
     FROM donor_profiles
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->execute([$userId]);

$profile = $stmt->fetch();


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $bloodGroup = trim($_POST["blood_group"] ?? "");
    $dateOfBirth = trim($_POST["date_of_birth"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $latitude = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");
    $availability = trim($_POST["availability"] ?? "Available");
    $lastDonationDate = trim($_POST["last_donation_date"] ?? "");


    // Validate required fields
    if (
        empty($bloodGroup) ||
        empty($location)
    ) {

        $error = "Please complete all required fields.";

    } elseif (
        !in_array(
            $bloodGroup,
            [
                "A+",
                "A-",
                "B+",
                "B-",
                "AB+",
                "AB-",
                "O+",
                "O-"
            ]
        )
    ) {

        $error = "Please select a valid blood group.";

    } elseif (
        !in_array(
            $availability,
            [
                "Available",
                "Unavailable"
            ]
        )
    ) {

        $error = "Invalid availability status.";

    } else {

        if ($profile) {

            // Update existing profile
            $update = $pdo->prepare(
                "UPDATE donor_profiles
                 SET
                    blood_group = ?,
                    date_of_birth = ?,
                    gender = ?,
                    location = ?,
                    latitude = ?,
                    longitude = ?,
                    availability = ?,
                    last_donation_date = ?
                 WHERE user_id = ?"
            );

            $update->execute([
                $bloodGroup,
                !empty($dateOfBirth) ? $dateOfBirth : null,
                !empty($gender) ? $gender : null,
                $location,
                !empty($latitude) ? $latitude : null,
                !empty($longitude) ? $longitude : null,
                $availability,
                !empty($lastDonationDate)
                    ? $lastDonationDate
                    : null,
                $userId
            ]);

            $success = "Your donor profile has been updated successfully.";

        } else {

            // Create new profile
            $insert = $pdo->prepare(
                "INSERT INTO donor_profiles
                (
                    user_id,
                    blood_group,
                    date_of_birth,
                    gender,
                    location,
                    latitude,
                    longitude,
                    availability,
                    last_donation_date
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $insert->execute([
                $userId,
                $bloodGroup,
                !empty($dateOfBirth) ? $dateOfBirth : null,
                !empty($gender) ? $gender : null,
                $location,
                !empty($latitude) ? $latitude : null,
                !empty($longitude) ? $longitude : null,
                $availability,
                !empty($lastDonationDate)
                    ? $lastDonationDate
                    : null
            ]);

            $success = "Your donor profile has been created successfully.";
        }


        // Reload profile
        $stmt = $pdo->prepare(
            "SELECT *
             FROM donor_profiles
             WHERE user_id = ?
             LIMIT 1"
        );

        $stmt->execute([$userId]);

        $profile = $stmt->fetch();
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

    <title>Donor Profile - DonorLink Ghana</title>

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


<?php include "includes/navbar.php"; ?>


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <div class="auth-card">

                <div class="text-center mb-4">

                    <div class="auth-icon">
                        <i class="bi bi-person-heart"></i>
                    </div>

                    <h2>Donor Profile</h2>

                    <p>
                        Complete your donor information so that compatible
                        blood requests can find you.
                    </p>

                </div>


                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">

                        <i class="bi bi-exclamation-circle"></i>

                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty($success)): ?>

                    <div class="alert alert-success">

                        <i class="bi bi-check-circle"></i>

                        <?php echo htmlspecialchars($success); ?>

                    </div>

                <?php endif; ?>


                <form method="POST" action="donor-profile.php">

                    <div class="row g-4">


                        <!-- Blood Group -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Blood Group <span class="text-danger">*</span>
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

                                $groups = [
                                    "A+",
                                    "A-",
                                    "B+",
                                    "B-",
                                    "AB+",
                                    "AB-",
                                    "O+",
                                    "O-"
                                ];

                                foreach ($groups as $group):

                                ?>

                                    <option
                                        value="<?php echo $group; ?>"
                                        <?php
                                        echo (
                                            ($profile["blood_group"] ?? "")
                                            === $group
                                        )
                                            ? "selected"
                                            : "";
                                        ?>
                                    >

                                        <?php echo $group; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Gender -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Gender
                            </label>

                            <select
                                name="gender"
                                class="form-select"
                            >

                                <option value="">
                                    Select gender
                                </option>

                                <?php

                                $genders = [
                                    "Male",
                                    "Female",
                                    "Other"
                                ];

                                foreach ($genders as $item):

                                ?>

                                    <option
                                        value="<?php echo $item; ?>"
                                        <?php
                                        echo (
                                            ($profile["gender"] ?? "")
                                            === $item
                                        )
                                            ? "selected"
                                            : "";
                                        ?>
                                    >

                                        <?php echo $item; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Date of Birth -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                class="form-control"
                                value="<?php
                                    echo htmlspecialchars(
                                        $profile["date_of_birth"] ?? ""
                                    );
                                ?>"
                            >

                        </div>


                        <!-- Last Donation -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Last Donation Date
                            </label>

                            <input
                                type="date"
                                name="last_donation_date"
                                class="form-control"
                                value="<?php
                                    echo htmlspecialchars(
                                        $profile["last_donation_date"] ?? ""
                                    );
                                ?>"
                            >

                        </div>


                        <!-- Location -->

                        <div class="col-12">

                            <label class="form-label">
                                Location <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="location"
                                id="location"
                                class="form-control"
                                placeholder="Example: Kumasi, Ashanti Region"
                                value="<?php
                                    echo htmlspecialchars(
                                        $profile["location"] ?? ""
                                    );
                                ?>"
                                required
                            >

                        </div>


                        <!-- Latitude -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Latitude
                            </label>

                            <input
                                type="text"
                                name="latitude"
                                id="latitude"
                                class="form-control"
                                value="<?php
                                    echo htmlspecialchars(
                                        $profile["latitude"] ?? ""
                                    );
                                ?>"
                                readonly
                            >

                        </div>


                        <!-- Longitude -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Longitude
                            </label>

                            <input
                                type="text"
                                name="longitude"
                                id="longitude"
                                class="form-control"
                                value="<?php
                                    echo htmlspecialchars(
                                        $profile["longitude"] ?? ""
                                    );
                                ?>"
                                readonly
                            >

                        </div>


                        <!-- Location Button -->

                        <div class="col-12">

                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                id="getLocationBtn"
                            >

                                <i class="bi bi-geo-alt"></i>

                                Use My Current Location

                            </button>

                            <small
                                class="d-block text-muted mt-2"
                            >
                                Your browser may ask for permission to
                                access your location.
                            </small>

                            <div
                                id="locationMessage"
                                class="mt-2"
                            ></div>

                        </div>


                        <!-- Availability -->

                        <div class="col-12">

                            <label class="form-label">
                                Donor Availability
                            </label>

                            <select
                                name="availability"
                                class="form-select"
                            >

                                <option
                                    value="Available"
                                    <?php
                                    echo (
                                        ($profile["availability"] ?? "")
                                        === "Available"
                                    )
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Available
                                </option>

                                <option
                                    value="Unavailable"
                                    <?php
                                    echo (
                                        ($profile["availability"] ?? "")
                                        === "Unavailable"
                                    )
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Unavailable
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="d-flex justify-content-between mt-5">

                        <a
                            href="dashboard.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-save"></i>

                            Save Donor Profile

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<?php include "includes/footer.php"; ?>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

document
    .getElementById("getLocationBtn")
    .addEventListener("click", function () {

        const message =
            document.getElementById("locationMessage");

        const latitude =
            document.getElementById("latitude");

        const longitude =
            document.getElementById("longitude");


        if (!navigator.geolocation) {

            message.innerHTML =
                '<div class="alert alert-danger">' +
                'Geolocation is not supported by this browser.' +
                '</div>';

            return;
        }


        message.innerHTML =
            '<div class="alert alert-info">' +
            'Getting your location...' +
            '</div>';


        navigator.geolocation.getCurrentPosition(

            function (position) {

                latitude.value =
                    position.coords.latitude.toFixed(8);

                longitude.value =
                    position.coords.longitude.toFixed(8);


                message.innerHTML =
                    '<div class="alert alert-success">' +
                    '<i class="bi bi-check-circle"></i> ' +
                    'Location captured successfully.' +
                    '</div>';

            },

            function (error) {

                message.innerHTML =
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-exclamation-circle"></i> ' +
                    'Unable to access your location. ' +
                    'Please allow location access or enter your area manually.' +
                    '</div>';

            }

        );

    });

</script>

</body>
</html>