<?php

require_once "config/session.php";

if (
    !isset($_SESSION["logged_in"]) ||
    $_SESSION["logged_in"] !== true
) {

    header("Location: login.php");
    exit;
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

    <title>Dashboard - DonorLink Ghana</title>

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

    <div class="mb-4">

        <h1 class="fw-bold">

            Welcome,
            <?php echo htmlspecialchars($_SESSION["full_name"]); ?>

        </h1>

        <p class="text-muted">

            You are logged in as a

            <strong>
                <?php echo htmlspecialchars($_SESSION["role"]); ?>
            </strong>.

        </p>

    </div>


    <div class="row g-4">


        <div class="col-md-4">

            <div class="process-card">

                <div class="process-icon">
                    <i class="bi bi-person-circle"></i>
                </div>

                <h4>My Profile</h4>

                <p>
                    Manage your DonorLink Ghana profile.
                </p>

               <a href="donor-profile.php" class="btn btn-primary">
      View Profile
               </a>

            </div>

        </div>


       <?php if ($_SESSION["role"] === "requester"): ?>

     <div class="col-md-4">

         <div class="process-card">

            <div class="process-icon">

                <i class="bi bi-search fs-1 text-primary"></i>
                </div>

                <h4>
                    Find a Donor
                </h4>

                <p>
                    Search for available blood donors near your location.
                </p>

                <a
                    href="find-donor.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-search me-1"></i>
                    Find Donor
                </a>

            </div>

        </div>

    

         <?php endif; ?>


        <?php if ($_SESSION["role"] === "requester"): ?>

       <div class="col-md-4">

         <div class="process-card">

            <div class="process-icon">
                    <i class="bi bi-droplet-fill fs-1 text-danger"></i>
                </div>


                <h4>
                    Blood Requests
                </h4>

                <p >
                    Create a request when blood is needed.
                </p>

                <a href="blood-request.php" class="btn btn-primary">
                    <i class="bi bi-droplet me-1"></i>
                    Create Blood Request
                </a>

        

            </div>

        </div>

    

            <?php endif; ?>

     



         <?php if ($_SESSION["role"] === "requester"): ?>

     <div class="col-md-4">

      <div class="process-card">

            <div class="process-icon">

                <i class="bi bi-list-check fs-1 text-primary"></i>
                </div>

                <h4 >
                    My Blood Requests
                </h4>

                <p>
                    View your requests and find matching donors.
                </p>

                <a
                    href="my-requests.php"
                    class="btn btn-primary"
                >
                    View My Requests
                </a>

            </div>

        </div>

    


         <?php endif; ?>




    <div class="text-center mt-5">

        <a href="logout.php" class="btn btn-outline-danger">

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>