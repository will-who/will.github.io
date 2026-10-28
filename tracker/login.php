<?php

    include("auth.php");

    if (current_user($conn)) {
        header("Location: track.php");
        exit;
    }

    $error = "";
    $username = "";
    $notice = pull_flash();     // napr. "Heslo bolo zmenené" po obnove hesla

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $username = trim($_POST["user"] ?? "");
        $password = $_POST["pass"] ?? "";

        if (!csrf_valid()) {
            $error = "Neplatný formulár, skús to znova.";
        }
        elseif ($username === "" || $password === "") {
            $error = "Vyplň meno aj heslo.";
        }
        else {
            $stmt = mysqli_prepare($conn,
                "SELECT id, password_hash, is_active FROM users WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if ($row && $row["is_active"] && password_verify($password, $row["password_hash"])) {
                session_regenerate_id(true);
                create_login_session($conn, (int)$row["id"]);
                header("Location: track.php");
                exit;
            }

            // zámerne rovnaká správa pre zlé meno aj zlé heslo
            $error = "Nesprávne meno alebo heslo.";
        }
    }

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <title>Login</title>
</head>
<body class="auth-page">

    <div class="auth-box">

        <a class="close-auth" href="track.php">×</a>

        <h2>Welcome Back</h2>
        <p class="auth-subtitle">Login to your account</p>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>

            <label for="user">Username</label>
            <input type="text" id="user" name="user"
                   placeholder="Enter your username"
                   value="<?= e($username) ?>" required>

            <label for="pass">Password</label>
            <input type="password" id="pass" name="pass"
                   placeholder="Enter your password" required>

            <div class="auth-forgot">
                <a href="forgot-password.php">Forgot password?</a>
            </div>

            <button type="submit" class="auth-submit">LOGIN</button>
        </form>

        <?php if ($notice): ?>
            <div class="auth-message ok"><?= e($notice[1]) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="auth-message"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="auth-switch">
            Don't have an account?
            <a href="register.php">SIGN UP</a>
        </div>

    </div>

</body>
</html>