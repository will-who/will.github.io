<?php

    include("auth.php");

    if (current_user($conn)) {
        header("Location: track.php");
        exit;
    }

    $error = "";
    $username = "";
    $email = "";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $username = trim($_POST["user"] ?? "");
        $email    = trim($_POST["email"] ?? "");
        $password = $_POST["pass"] ?? "";

        if (!csrf_valid()) {
            $error = "Neplatný formulár, skús to znova.";
        }
        elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $error = "Meno musí mať 3–50 znakov (písmená, čísla, _).";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $error = "Zadaj platný email.";
        }
        elseif (strlen($password) < 8) {
            $error = "Heslo musí mať aspoň 8 znakov.";
        }
        elseif (strlen($password) > 72) {
            $error = "Heslo môže mať najviac 72 znakov.";
        }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hash);
                mysqli_stmt_execute($stmt);

                $userId = mysqli_insert_id($conn);

                // rovno prihlásiť
                session_regenerate_id(true);
                create_login_session($conn, (int)$userId);
                header("Location: track.php");
                exit;
            }
            catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) {   // duplicate entry
                    $error = "Toto meno alebo email už je obsadené.";
                } else {
                    $error = "Registrácia zlyhala, skús to neskôr.";
                }
            }
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
    <title>Register</title>
</head>
<body class="auth-page">

    <div class="auth-box">

        <a class="close-auth" href="track.php">×</a>

        <h2>Create Account</h2>
        <p class="auth-subtitle">Create your game launcher account</p>

        <form method="POST" action="register.php">
            <?= csrf_field() ?>

            <label for="user">Username</label>
            <input type="text" id="user" name="user"
                   placeholder="Choose a username"
                   value="<?= e($username) ?>" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   placeholder="Your email"
                   value="<?= e($email) ?>" required>

            <label for="pass">Password</label>
            <input type="password" id="pass" name="pass"
                   placeholder="Choose a password (min. 8 characters)" required>

            <button type="submit" class="auth-submit">CREATE ACCOUNT</button>
        </form>

        <?php if ($error): ?>
            <div class="auth-message"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="auth-switch">
            Already have an account?
            <a href="login.php">LOGIN</a>
        </div>

    </div>

</body>
</html>