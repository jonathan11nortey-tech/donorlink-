<?php

require_once "config/database.php";
require_once "config/session.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "requester";


    // Validate required fields
    if (
        empty($fullName) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirmPassword)
    ) {

        $error = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif (!in_array($role, ["donor", "requester"])) {

        $error = "Invalid account type.";

    } else {

        // Check whether email already exists
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $check->execute([$email]);

        if ($check->fetch()) {

            $error = "An account with this email already exists.";

        } else {

            // Secure password hash
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert user
            $stmt = $pdo->prepare(
                "INSERT INTO users
                (full_name, email, phone, password, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $fullName,
                $email,
                $phone,
                $hashedPassword,
                $role
            ]);

            $success = "Registration successful. You can now log in.";
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

    <title>Create Account - DonorLink Ghana</title>

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

        <div class="col-lg-6 col-md-8">

            <div class="auth-card">

                <div class="text-center mb-4">

                    <div class="auth-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>

                    <h2>Create Your Account</h2>

                    <p>
                        Join DonorLink Ghana and help connect
                        people with blood donors.
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

                        <br>

                        <a href="login.php">
                            Login to your account
                        </a>

                    </div>

                <?php endif; ?>


                <form method="POST" action="register.php">

                    <div class="mb-3">

                        <label class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            placeholder="Enter your full name"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="example@email.com"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            name="phone"
                            class="form-control"
                            placeholder="+233 XX XXX XXXX"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Account Type
                        </label>

                        <select
                            name="role"
                            class="form-select"
                            required
                        >

                            <option value="requester">
                                I Need Blood
                            </option>

                            <option value="donor">
                                I Want to Donate Blood
                            </option>

                        </select>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Minimum 8 characters"
                            required
                        >

                    </div>


                    <div class="mb-4">

                        <label class="form-label">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Repeat your password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >

                        <i class="bi bi-person-plus"></i>

                        Create Account

                    </button>

                </form>


                <div class="text-center mt-4">

                    <p>
                        Already have an account?

                        <a href="login.php">
                            Login here
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