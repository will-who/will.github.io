<?php

    include("auth.php");

    if (current_user($conn)) {
        header("Location: track.php");
        exit;
    }

    $error = "";
    $email = "";
    $sent  = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $email = trim($_POST["email"] ?? "");

        if (!csrf_valid()) {
            $error = "Invalid form, please try again.";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $error = "Please enter a valid email.";
        }
        else {
            $stmt = mysqli_prepare($conn,
                "SELECT id, username FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if ($row) {
                $token = create_password_reset($conn, (int)$row["id"]);

                if ($token) {
                    $link = SITE_URL . "/reset-password.php?token=" . $token;

                    send_mail($email, "Reset your password",
                        "Hi " . $row["username"] . ",\r\n\r\n"
                      . "we received a request to reset your Game Launcher password.\r\n"
                      . "Open this link to choose a new one (valid for 1 hour):\r\n\r\n"
                      . $link . "\r\n\r\n"
                      . "If you did not ask for this, you can ignore this email.\r\n");
                }
            }

            // zámerne rovnaká odpoveď, či účet existuje alebo nie
            $sent = true;
        }
    }

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <title>Forgot password</title>
</head>
<body class="auth-page">

    <div class="auth-box">

        <a class="close-auth" href="login.php">×</a>

        <h2>Forgot Password</h2>
        <p class="auth-subtitle">We will email you a link to reset it</p>

        <?php if ($sent): ?>

            <div class="auth-message ok">
                If an account with this email exists, we have sent a password reset link to it.
            </div>

        <?php else: ?>

            <form method="POST" action="forgot-password.php">
                <?= csrf_field() ?>

                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       placeholder="Your account email"
                       value="<?= e($email) ?>" required>

                <button type="submit" class="auth-submit">SEND RESET LINK</button>
            </form>

            <?php if ($error): ?>
                <div class="auth-message"><?= e($error) ?></div>
            <?php endif; ?>

        <?php endif; ?>

        <div class="auth-switch">
            Remembered it?
            <a href="login.php">LOGIN</a>
        </div>

    </div>

</body>
</html>