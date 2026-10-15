<?php

require_once "config/database.php";
require_once "config/session.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        if (!$user) {

            $error = "Invalid email or password.";

        } elseif ($user["status"] !== "active") {

            $error = "This account is currently unavailable.";

        } elseif (!password_verify($password, $user["password"])) {

            $error = "Invalid email or password.";

        } else {

            /*
             * Regenerate the session ID after authentication.
             */
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["logged_in"] = true;


            header("Location: dashboard.php");
            exit;
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

    <title>Login - DonorLink Ghana</title>

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

        <div class="col-lg-5 col-md-7">

            <div class="auth-card">

                <div class="text-center mb-4">

                    <div class="auth-icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>

                    <h2>Welcome Back</h2>

                    <p>
                        Login to your DonorLink Ghana account.
                    </p>

                </div>


                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">

                        <i class="bi bi-exclamation-circle"></i>

                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <form method="POST" action="login.php">

                    <div class="mb-3">

                        <label class="form-label">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your email"
                            required
                        >

                    </div>


                    <div class="mb-4">

                        <label class="form-label">
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >

                        <i class="bi bi-box-arrow-in-right"></i>

                        Login

                    </button>

                </form>


                <div class="text-center mt-4">

                    <p>
                        Don't have an account?

                        <a href="register.php">
                            Create an account
                        </a>
                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<?php include "includes/footer.php"; ?>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>