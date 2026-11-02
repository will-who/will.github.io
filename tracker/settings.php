<?php

    include("auth.php");
    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $uid = (int)$user["id"];

    $values = ["username" => $user["username"], "email" => $user["email"]];
    $errors = ["username" => "", "email" => "", "password" => ""];

    // Overí aktuálne heslo prihláseného používateľa
    function current_password_ok($conn, $uid, $password) {
        $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return $row && password_verify($password, $row["password_hash"]);
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $action  = $_POST["action"] ?? "";
        $current = $_POST["current_pass"] ?? "";

        if (!csrf_valid()) {
            flash("error", "Invalid form, please try again.");
            header("Location: settings.php");
            exit;
        }

        /* ---------- Zmena mena ---------- */
        if ($action === "username") {

            $new = trim($_POST["username"] ?? "");
            $values["username"] = $new;

            if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $new)) {
                $errors["username"] = "Username must have 3–50 characters (letters, numbers, _).";
            }
            elseif ($new === $user["username"]) {
                $errors["username"] = "This is already your username.";
            }
            elseif (!current_password_ok($conn, $uid, $current)) {
                $errors["username"] = "Wrong current password.";
            }
            else {
                try {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $new, $uid);
                    mysqli_stmt_execute($stmt);
                    flash("ok", "Username changed.");
                    header("Location: settings.php");
                    exit;
                }
                catch (mysqli_sql_exception $ex) {
                    $errors["username"] = ($ex->getCode() === 1062)
                        ? "This username is already taken."
                        : "Could not change the username, try again later.";
                }
            }
        }

        /* ---------- Zmena emailu ---------- */
        elseif ($action === "email") {

            $new = trim($_POST["email"] ?? "");
            $values["email"] = $new;

            if (!filter_var($new, FILTER_VALIDATE_EMAIL) || strlen($new) > 255) {
                $errors["email"] = "Please enter a valid email.";
            }
            elseif (strcasecmp($new, $user["email"]) === 0) {
                $errors["email"] = "This is already your email.";
            }
            elseif (!current_password_ok($conn, $uid, $current)) {
                $errors["email"] = "Wrong current password.";
            }
            else {
                try {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET email = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $new, $uid);
                    mysqli_stmt_execute($stmt);
                    flash("ok", "Email changed.");
                    header("Location: settings.php");
                    exit;
                }
                catch (mysqli_sql_exception $ex) {
                    $errors["email"] = ($ex->getCode() === 1062)
                        ? "This email is already used by another account."
                        : "Could not change the email, try again later.";
                }
            }
        }

        /* ---------- Zmena hesla ---------- */
        elseif ($action === "password") {

            $new  = $_POST["new_pass"]  ?? "";
            $new2 = $_POST["new_pass2"] ?? "";

            if (strlen($new) < 8) {
                $errors["password"] = "Password must have at least 8 characters.";
            }
            elseif (strlen($new) > 72) {
                $errors["password"] = "Password can have at most 72 characters.";
            }
            elseif ($new !== $new2) {
                $errors["password"] = "Passwords do not match.";
            }
            elseif (!current_password_ok($conn, $uid, $current)) {
                $errors["password"] = "Wrong current password.";
            }
            else {
                $hash = password_hash($new, PASSWORD_DEFAULT);

                $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $hash, $uid);
                mysqli_stmt_execute($stmt);

                // ostatné prihlásenia (iné zariadenia) sa odhlásia, aktuálne ostáva
                $mine = hash("sha256", $_COOKIE[AUTH_COOKIE] ?? "");
                $stmt = mysqli_prepare($conn, "DELETE FROM sessions WHERE user_id = ? AND token <> ?");
                mysqli_stmt_bind_param($stmt, "is", $uid, $mine);
                mysqli_stmt_execute($stmt);

                // zrušia sa aj rozpracované odkazy na obnovu hesla
                $stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $uid);
                mysqli_stmt_execute($stmt);

                flash("ok", "Password changed.");
                header("Location: settings.php");
                exit;
            }
        }
    }

    $flash = pull_flash();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title>Settings – LuLo Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="logo.jpg" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php">HOME</a>
            <a href="#">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <?php if (is_editor($user)): ?>
            <a href="changelog.php" class="notification users-link" title="Change log">📜</a>
            <?php endif; ?>

            <?php if (is_admin($user)): ?>
            <a href="users.php" class="notification users-link" title="All users">👥</a>
            <?php endif; ?>

            <a href="settings.php" class="notification users-link active" title="Settings">⚙</a>

            <span class="username"><?= e($user["username"]) ?></span>

            <form method="POST" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="account-button">LOGOUT</button>
            </form>

        </div>

    </header>


    <main class="content">

        <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>

        <h1>Settings</h1>

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <!-- Meno -->
        <form method="POST" action="settings.php" class="settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="username">

            <h2>Username</h2>

            <label for="username">New username</label>
            <input type="text" id="username" name="username" maxlength="50"
                   autocomplete="username" value="<?= e($values["username"]) ?>" required>

            <label for="current_pass_u">Current password</label>
            <input type="password" id="current_pass_u" name="current_pass"
                   autocomplete="current-password" required>

            <div class="settings-actions">
                <button type="submit" class="account-button">CHANGE USERNAME</button>
            </div>

            <?php if ($errors["username"]): ?>
                <div class="news-form-error"><?= e($errors["username"]) ?></div>
            <?php endif; ?>
        </form>

        <!-- Email -->
        <form method="POST" action="settings.php" class="settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="email">

            <h2>Email</h2>

            <label for="email">New email</label>
            <input type="email" id="email" name="email" maxlength="255"
                   autocomplete="email" value="<?= e($values["email"]) ?>" required>

            <label for="current_pass_e">Current password</label>
            <input type="password" id="current_pass_e" name="current_pass"
                   autocomplete="current-password" required>

            <div class="settings-actions">
                <button type="submit" class="account-button">CHANGE EMAIL</button>
            </div>

            <?php if ($errors["email"]): ?>
                <div class="news-form-error"><?= e($errors["email"]) ?></div>
            <?php endif; ?>
        </form>

        <!-- Heslo -->
        <form method="POST" action="settings.php" class="settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <h2>Password</h2>

            <label for="current_pass_p">Current password</label>
            <input type="password" id="current_pass_p" name="current_pass"
                   autocomplete="current-password" required>

            <label for="new_pass">New password</label>
            <input type="password" id="new_pass" name="new_pass"
                   placeholder="Min. 8 characters" autocomplete="new-password" required>

            <label for="new_pass2">Repeat new password</label>
            <input type="password" id="new_pass2" name="new_pass2"
                   autocomplete="new-password" required>

            <div class="settings-actions">
                <button type="submit" class="account-button">CHANGE PASSWORD</button>
            </div>

            <?php if ($errors["password"]): ?>
                <div class="news-form-error"><?= e($errors["password"]) ?></div>
            <?php endif; ?>
        </form>

    </main>

</body>
</html>