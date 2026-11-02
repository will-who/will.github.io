<?php

    include("auth.php");

    if (current_user($conn)) {
        header("Location: track.php");
        exit;
    }

    $token = (string)($_GET["token"] ?? $_POST["token"] ?? "");
    $reset = find_password_reset($conn, $token);
    $error = "";

    if ($reset && $_SERVER["REQUEST_METHOD"] === "POST") {

        $password  = $_POST["pass"]  ?? "";
        $password2 = $_POST["pass2"] ?? "";

        if (!csrf_valid()) {
            $error = "Neplatný formulár, skús to znova.";
        }
        elseif (strlen($password) < 8) {
            $error = "Heslo musí mať aspoň 8 znakov.";
        }
        elseif (strlen($password) > 72) {
            $error = "Heslo môže mať najviac 72 znakov.";
        }
        elseif ($password !== $password2) {
            $error = "Heslá sa nezhodujú.";
        }
        else {
            $userId = (int)$reset["user_id"];
            $hash   = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hash, $userId);
            mysqli_stmt_execute($stmt);

            // odkaz je jednorazový a staré prihlásenia sa odhlásia
            $stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM sessions WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);

            flash("ok", "Heslo bolo zmenené, môžeš sa prihlásiť.");
            header("Location: login.php");
            exit;
        }
    }

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <title>Reset password</title>
</head>
<body class="auth-page">

    <div class="auth-box">

        <a class="close-auth" href="login.php">×</a>

        <h2>New Password</h2>

        <?php if ($reset): ?>

            <p class="auth-subtitle">Choose a new password for your account</p>

            <form method="POST" action="reset-password.php">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <label for="pass">New password</label>
                <input type="password" id="pass" name="pass"
                       placeholder="Min. 8 characters" required>

                <label for="pass2">Repeat password</label>
                <input type="password" id="pass2" name="pass2"
                       placeholder="Repeat the password" required>

                <button type="submit" class="auth-submit">CHANGE PASSWORD</button>
            </form>

            <?php if ($error): ?>
                <div class="auth-message"><?= e($error) ?></div>
            <?php endif; ?>

        <?php else: ?>

            <p class="auth-subtitle">This link is not valid anymore</p>

            <div class="auth-message">
                Odkaz na obnovu hesla je neplatný alebo vypršal.
            </div>

            <div class="auth-switch">
                <a href="forgot-password.php">REQUEST A NEW LINK</a>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>