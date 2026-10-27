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
            "SELECT u.id, u.username, u.email, u.role
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

    // Bezpečná URL/cesta k obrázku z DB (podporuje lokálny súbor aj http/https odkaz)
    function img_url($path) {
        $path = trim((string)$path);
        if ($path === "") {
            return "";
        }
        if (preg_match('#^https?://#i', $path)) {
            if (!filter_var($path, FILTER_VALIDATE_URL)) {
                return "";
            }
            return strtr($path, [
                "'" => "%27", '"' => "%22", "(" => "%28", ")" => "%29",
                " " => "%20", "\\" => "%5C", "<" => "%3C", ">" => "%3E",
            ]);
        }
        return str_replace("%2F", "/", rawurlencode($path));
    }

    // Bezpečná hodnota pre CSS (background-size / background-position) z DB
    function css_val($v, $default) {
        $v = trim((string)$v);
        return preg_match('/^[a-z0-9 %.\-]{1,50}$/i', $v) ? $v : $default;
    }

    /* ---------- Admin / správa hier ---------- */

    function is_admin($user) {
        return $user && ($user["role"] ?? "user") === "admin";
    }

    // Stránka je len pre admina: hosť -> login, bežný user -> 403
    function require_admin($conn) {
        $u = current_user($conn);
        if (!$u) {
            header("Location: login.php");
            exit;
        }
        if (!is_admin($u)) {
            http_response_code(403);
            die("Access denied.");
        }
        return $u;
    }

    function valid_image_src($s) {
        if (preg_match('#^https?://#i', $s)) {
            return strlen($s) <= 500 && filter_var($s, FILTER_VALIDATE_URL) !== false;
        }
        return preg_match('/^[A-Za-z0-9_\-.\/]{1,255}$/', $s) && strpos($s, "..") === false;
    }

    function game_input() {
        return [
            "title" => trim($_POST["title"] ?? ""),
            "tags"  => trim($_POST["tags"]  ?? ""),
            "image" => trim($_POST["image"] ?? ""),
        ];
    }

    // Vráti text chyby alebo "" ak je všetko v poriadku
    function game_validate($v) {
        if ($v["title"] === "")                     return "Game title is required.";
        if (mb_strlen($v["title"], "UTF-8") > 100)  return "Game title is too long (max 100 characters).";
        if (mb_strlen($v["tags"], "UTF-8") > 100)   return "Tags are too long (max 100 characters).";
        if ($v["image"] !== "" && !valid_image_src($v["image"])) {
            return "Img source must be an https:// link or a file path like img/game.png.";
        }
        return "";
    }

    function e($s) {
        return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
    }

?>