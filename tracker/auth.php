<?php

    // Spoločné funkcie pre prihlásenie / registráciu / odhlásenie

    session_start();                       // používa sa len pre CSRF token
    include_once("database.php");

    const AUTH_COOKIE   = "auth_token";
    const AUTH_LIFETIME = 60 * 60 * 24 * 7;   // 7 dní


    /* ---------- CSRF ---------- */

    function csrf_token() {
        if (empty($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf"];
    }

    function csrf_field() {
        return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    }

    function csrf_valid() {
        return isset($_POST["csrf"], $_SESSION["csrf"])
            && hash_equals($_SESSION["csrf"], $_POST["csrf"]);
    }


    /* ---------- Sessions v DB ---------- */

    // V DB sa ukladá iba SHA-256 hash tokenu (char(64)), v cookie je pôvodný token.
    function create_login_session($conn, $userId) {
        $token = bin2hex(random_bytes(32));
        $hash  = hash("sha256", $token);
        $ip    = $_SERVER["REMOTE_ADDR"] ?? null;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO sessions (user_id, token, ip_address, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . AUTH_LIFETIME . " SECOND))");
        mysqli_stmt_bind_param($stmt, "iss", $userId, $hash, $ip);
        mysqli_stmt_execute($stmt);

        setcookie(AUTH_COOKIE, $token, [
            "expires"  => time() + AUTH_LIFETIME,
            "path"     => "/",
            "httponly" => true,
            "samesite" => "Lax",
            // "secure" => true,   // odkomentuj, keď pôjdeš cez HTTPS
        ]);

        $stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
    }

    // Vráti pole s údajmi používateľa alebo null, ak nie je prihlásený
    function current_user($conn) {
        if (empty($_COOKIE[AUTH_COOKIE])) {
            return null;
        }
        $hash = hash("sha256", $_COOKIE[AUTH_COOKIE]);

        $stmt = mysqli_prepare($conn,
            "SELECT u.id, u.username, u.email
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = 1
              LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $hash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return mysqli_fetch_assoc($result) ?: null;
    }

    function destroy_login_session($conn) {
        if (!empty($_COOKIE[AUTH_COOKIE])) {
            $hash = hash("sha256", $_COOKIE[AUTH_COOKIE]);
            $stmt = mysqli_prepare($conn, "DELETE FROM sessions WHERE token = ?");
            mysqli_stmt_bind_param($stmt, "s", $hash);
            mysqli_stmt_execute($stmt);
        }
        setcookie(AUTH_COOKIE, "", [
            "expires"  => time() - 3600,
            "path"     => "/",
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        // upratanie expirovaných sessions
        mysqli_query($conn, "DELETE FROM sessions WHERE expires_at < NOW()");
    }

    function e($s) {
        return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
    }

?>