<?php
$pageTitle = "DonorLink Ghana";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<?php include 'includes/navbar.php'; ?>


<!-- ================= HERO SECTION ================= -->

<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-75">

            <div class="col-lg-6">

                <span class="hero-badge">
                    <i class="bi bi-heart-pulse-fill"></i>
                    Saving Lives Through Connection
                </span>

                <h1 class="hero-title">
                    Find a Blood Donor
                    <span>When You Need One.</span>
                </h1>

                <p class="hero-text">
                    DonorLink Ghana connects people who need blood with
                    available donors based on blood group and location.
                </p>

                <div class="hero-buttons">

                    <a href="#" class="btn btn-primary btn-lg">
                        <i class="bi bi-search"></i>
                        Find a Donor
                    </a>

                    <a href="#" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-person-plus"></i>
                        Become a Donor
                    </a>

                </div>

            </div>


            <div class="col-lg-6 text-center">

                <div class="hero-image-container">

                    <div class="hero-circle">
                        <i class="bi bi-droplet-half"></i>
                    </div>

                    <div class="floating-card card-one">
                        <i class="bi bi-check-circle-fill"></i>
                        Donor Found
                    </div>

                    <div class="floating-card card-two">
                        <i class="bi bi-geo-alt-fill"></i>
                        Nearby Donor
                    </div>

                </div>

            </div>

        </div>
    </div>
</section>


<!-- ================= STATISTICS ================= -->

<section class="statistics-section">
    <div class="container">

        <div class="row g-4 text-center">

            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-people-fill"></i>
                    <h2>0+</h2>
                    <p>Registered Donors</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-heart-fill"></i>
                    <h2>0+</h2>
                    <p>Blood Requests</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <i class="bi bi-droplet-fill"></i>
                    <h2>0+</h2>
                    <p>Donations Recorded</p>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- ================= HOW IT WORKS ================= -->

<section class="section-padding">

    <div class="container">

        <div class="section-heading text-center">

            <span>HOW IT WORKS</span>

            <h2>
                Getting Blood Help Is Simple
            </h2>

            <p>
                DonorLink Ghana makes it easier to connect blood donors
                with people who need blood.
            </p>

        </div>


        <div class="row g-4">

            <div class="col-md-4">

                <div class="process-card">

                    <div class="process-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>

                    <h4>1. Create an Account</h4>

                    <p>
                        Register as a donor or requester and provide
                        the required information.
                    </p>

                </div>

            </div>


            <div class="col-md-4">

                <div class="process-card">

                    <div class="process-icon">
                        <i class="bi bi-search-heart"></i>
                    </div>

                    <h4>2. Find or Request Blood</h4>

                    <p>
                        Search for compatible donors or submit a
                        blood request when assistance is needed.
                    </p>

                </div>

            </div>


            <div class="col-md-4">

                <div class="process-card">

                    <div class="process-icon">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>

                    <h4>3. Connect</h4>

                    <p>
                        Communicate with compatible users and
                        coordinate the next steps.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- ================= BLOOD GROUPS ================= -->

<section class="blood-section">

    <div class="container">

        <div class="section-heading text-center">

            <span>BLOOD GROUPS</span>

            <h2>
                Search by Blood Group
            </h2>

            <p>
                DonorLink Ghana will help requesters identify compatible
                donors based on blood group.
            </p>

        </div>


        <div class="row g-3 justify-content-center">

            <?php

            $bloodGroups = [
                "A+",
                "A-",
                "B+",
                "B-",
                "AB+",
                "AB-",
                "O+",
                "O-"
            ];

            foreach ($bloodGroups as $group):

            ?>

                <div class="col-6 col-sm-4 col-md-3 col-lg-2">

                    <div class="blood-group-card">
                        <div class="blood-icon">
                            <i class="bi bi-droplet-fill"></i>
                        </div>

                        <h4><?php echo $group; ?></h4>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<!-- ================= CTA ================= -->

<section class="cta-section">

    <div class="container">

        <div class="cta-box">

            <div>

                <span>
                    <i class="bi bi-heart-pulse-fill"></i>
                    BE A DONOR
                </span>

                <h2>
                    Your Donation Could Help Save a Life.
                </h2>

                <p>
                    Join DonorLink Ghana and make it easier for people
                    in need to connect with blood donors.
                </p>

            </div>

            <a href="#" class="btn btn-light btn-lg">
                Register Now
                <i class="bi bi-arrow-right"></i>
            </a>

        </div>

    </div>

</section>


<?php include 'includes/footer.php'; ?>


<!-- Bootstrap JS -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<!-- Custom JS -->
<script src="assets/js/script.js"></script>

</body>
</html>