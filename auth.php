<?php

    // Spoločné funkcie pre prihlásenie / registráciu / odhlásenie

    session_start();                       // používa sa len pre CSRF token
    include_once("database.php");

    const AUTH_COOKIE   = "auth_token";
    const AUTH_LIFETIME = 60 * 60 * 24 * 7;   // 7 dní
    const RESET_LIFETIME = 60 * 60;           // odkaz na obnovu hesla platí 1 hodinu

    // ZMEŇ podľa seba: adresa stránky v odkaze z emailu (bez lomky na konci)
    const SITE_URL  = "http://localhost/tracker";
    const MAIL_FROM = "trackerpage@outlook.sk";
    // true = text každého emailu sa zapíše aj do PHP error logu (xampp/apache/logs/error.log),
    // aby si na localhoste videl odkaz aj bez nastaveného SMTP. Na ostrom serveri daj false.
    const MAIL_DEV_LOG = true;


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
            "SELECT u.id, u.username, u.email, u.role, u.avatar
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

    // admin = najvyššia rola: robí všetko vrátane správy používateľov (users.php)
    function is_admin($user) {
        return $user && ($user["role"] ?? "user") === "admin";
    }

    // editor aj admin: smú pridávať, upravovať a mazať hry
    function is_editor($user) {
        return $user && in_array($user["role"] ?? "user", ["editor", "admin"], true);
    }

    // Stránka je len pre admina (správa používateľov): hosť -> login, ostatní -> 403
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

    // Stránka pre editora aj admina (správa hier): hosť -> login, bežný user -> 403
    function require_editor($conn) {
        $u = current_user($conn);
        if (!$u) {
            header("Location: login.php");
            exit;
        }
        if (!is_editor($u)) {
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
            "tags"  => tag_ids_input($_POST["tags"] ?? []),
            "image" => trim($_POST["image"] ?? ""),
            "description" => trim($_POST["description"] ?? ""),
        ];
    }

    // Vráti text chyby alebo "" ak je všetko v poriadku
    function game_validate($v) {
        if ($v["title"] === "")                     return "Game title is required.";
        if (mb_strlen($v["title"], "UTF-8") > 100)  return "Game title is too long (max 100 characters).";
        if (mb_strlen($v["description"], "UTF-8") > 2000) return "Description is too long (max 2000 characters).";
        if ($v["image"] !== "" && !valid_image_src($v["image"])) {
            return "Img source must be an https:// link or a file path like img/game.png.";
        }
        return "";
    }

    // URL hlavnej stránky so zachovaným filtrom (view), tagom (tag), hľadaním (q) a triedením (sort)
    function track_url($view = "all", $sort = "az", $tag = 0, $search = "") {
        $params = [];
        if ($view !== "all")  { $params["view"] = $view; }
        if ($tag)             { $params["tag"]  = (int)$tag; }
        if ($search !== "")   { $params["q"]    = $search; }
        if ($sort !== "az")   { $params["sort"] = $sort; }
        return "track.php" . ($params ? "?" . http_build_query($params) : "");
    }


    // Kam vedie "Back to games": posledný zoznam hier (filter, tag, triedenie) a karta danej hry.
    // Server nevie, ako ďaleko máš odscrollované, preto ťa vráti na kartu hry (kotva #game-ID).
    function back_url($gameId = 0) {
        $url = $_SESSION["list_url"] ?? "track.php";
        return $url . ($gameId ? "#game-" . (int)$gameId : "");
    }


    /* ---------- Tagy ---------- */

    // Z $_POST["tags"] (pole checkboxov) spraví pole unikátnych kladných čísel
    function tag_ids_input($raw) {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            $n = filter_var($v, FILTER_VALIDATE_INT);
            if ($n !== false && $n > 0) {
                $ids[$n] = $n;
            }
        }
        return array_values($ids);
    }

    // Všetky tagy aj s počtom hier: [["id", "name", "games"], ...]
    function all_tags($conn) {
        $res = mysqli_query($conn,
            "SELECT t.id, t.name, COUNT(gt.game_id) AS games
               FROM tags t
               LEFT JOIN game_tags gt ON gt.tag_id = t.id
              GROUP BY t.id, t.name
              ORDER BY t.id");
        return mysqli_fetch_all($res, MYSQLI_ASSOC);
    }

    // Tagy jednej hry: [["id", "name"], ...]
    function game_tags($conn, $gameId) {
        $stmt = mysqli_prepare($conn,
            "SELECT t.id, t.name
               FROM game_tags gt
               JOIN tags t ON t.id = gt.tag_id
              WHERE gt.game_id = ?
              ORDER BY t.id");
        mysqli_stmt_bind_param($stmt, "i", $gameId);
        mysqli_stmt_execute($stmt);
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    // Nahradí tagy hry vybranými (neexistujúce id sa ticho ignorujú)
    function save_game_tags($conn, $gameId, $tagIds) {
        $stmt = mysqli_prepare($conn, "DELETE FROM game_tags WHERE game_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $gameId);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($conn,
            "INSERT INTO game_tags (game_id, tag_id) SELECT ?, id FROM tags WHERE id = ?");
        foreach ($tagIds as $tagId) {
            $tagId = (int)$tagId;
            mysqli_stmt_bind_param($stmt, "ii", $gameId, $tagId);
            mysqli_stmt_execute($stmt);
        }
    }

    // Zaškrtávacie tagy pre formulár (add / edit). $selected = pole id tagov.
    function tag_picker($allTags, $selected) {
        $html = '<div class="tag-picker">';
        foreach ($allTags as $t) {
            $checked = in_array((int)$t["id"], array_map("intval", $selected), true) ? " checked" : "";
            $html .= '<label class="tag-option"><input type="checkbox" name="tags[]" value="'
                   . (int)$t["id"] . '"' . $checked . '><span>' . e($t["name"]) . '</span></label>';
        }
        return $html . '</div>';
    }

    /* ---------- Jednorazové hlášky (flash) ---------- */

    function flash($type, $msg) {
        $_SESSION["flash"] = [$type, $msg];
    }

    // Vráti [typ, text] alebo null a hlášku zároveň zmaže
    function pull_flash() {
        $f = $_SESSION["flash"] ?? null;
        unset($_SESSION["flash"]);
        return $f;
    }


    /* ---------- Email ---------- */

    function send_mail($to, $subject, $body) {
        if (MAIL_DEV_LOG) {
            error_log("[tracker mail] To: " . $to . " | " . $subject . "\n" . $body);
        }
        $headers = [
            "From: " . MAIL_FROM,
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
        ];
        return @mail($to, mb_encode_mimeheader($subject, "UTF-8"), $body, implode("\r\n", $headers));
    }


    /* ---------- Obnova hesla ---------- */

    // Vytvorí token a vráti ho (v DB je len jeho SHA-256 hash).
    // Vráti null, ak sa o obnovu pre tohto používateľa žiadalo pred menej ako minútou.
    function create_password_reset($conn, $userId) {
        $stmt = mysqli_prepare($conn,
            "SELECT 1 FROM password_resets
              WHERE user_id = ? AND used = 0
                AND expires_at > DATE_ADD(NOW(), INTERVAL " . (RESET_LIFETIME - 60) . " SECOND)
              LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_row(mysqli_stmt_get_result($stmt))) {
            return null;
        }

        // staré odkazy prestanú platiť
        $stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);

        $token = bin2hex(random_bytes(32));
        $hash  = hash("sha256", $token);

        $stmt = mysqli_prepare($conn,
            "INSERT INTO password_resets (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . RESET_LIFETIME . " SECOND))");
        mysqli_stmt_bind_param($stmt, "is", $userId, $hash);
        mysqli_stmt_execute($stmt);

        return $token;
    }

    // Vráti ["id", "user_id"] pre platný, nepoužitý token, inak null
    function find_password_reset($conn, $token) {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $hash = hash("sha256", $token);

        $stmt = mysqli_prepare($conn,
            "SELECT r.id, r.user_id
               FROM password_resets r
               JOIN users u ON u.id = r.user_id
              WHERE r.token = ? AND r.used = 0 AND r.expires_at > NOW() AND u.is_active = 1
              LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $hash);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    /* ---------- Zoznam zmien (changelog) ---------- */

    // Zapíše zmenu do tabuľky changelog. Chyba pri logovaní nikdy neskazí hlavnú akciu.
    // $action: added / edited / deleted / promoted / demoted, $entity: game / news / user
    function log_change($conn, $user, $action, $entity, $name, $details = "") {
        try {
            $uid   = (int)$user["id"];
            $uname = (string)$user["username"];
            $name  = mb_substr((string)$name, 0, 150, "UTF-8");
            $details = $details === "" ? null : $details;
            $stmt = mysqli_prepare($conn,
                "INSERT INTO changelog (user_id, username, action, entity, entity_name, details)
                 VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isssss", $uid, $uname, $action, $entity, $name, $details);
            mysqli_stmt_execute($stmt);
        }
        catch (mysqli_sql_exception $ex) {
            error_log("[tracker changelog] " . $ex->getMessage());
        }
    }

    // Skráti dlhý text na zobrazenie v zázname o zmene
    function log_short($s, $max = 60) {
        $s = trim(preg_replace('/\s+/', ' ', (string)$s));
        return mb_strlen($s, "UTF-8") > $max ? mb_substr($s, 0, $max, "UTF-8") . "…" : $s;
    }

    /* ---------- Profilovky (avatar) ---------- */

    const AVATAR_DIR = "uploads/avatars";

    // Bezpečná cesta k súboru profilovky alebo "" ak žiadna nie je
    function avatar_src($file) {
        $file = (string)$file;
        return preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $file) ? AVATAR_DIR . "/" . $file : "";
    }

    // Obrázok profilovky, alebo kruh s prvým písmenom mena, ak ju používateľ nemá
    function avatar_html($username, $file, $class = "avatar-sm") {
        $src = avatar_src($file);
        if ($src !== "") {
            return '<img class="avatar ' . e($class) . '" src="' . e($src) . '" alt="">';
        }
        $initial = mb_strtoupper(mb_substr((string)$username, 0, 1, "UTF-8"), "UTF-8");
        return '<span class="avatar avatar-default ' . e($class) . '">' . e($initial) . '</span>';
    }

    /* ---------- Priatelia a notifikácie (zvonček) ---------- */

    // "3 hours ago" z počtu sekúnd (rozdiel sa počíta v databáze)
    function time_ago($seconds) {
        $s = max(0, (int)$seconds);
        if ($s < 60)    { return "just now"; }
        if ($s < 3600)  { $n = intdiv($s, 60);   return $n . " minute" . ($n === 1 ? "" : "s") . " ago"; }
        if ($s < 86400) { $n = intdiv($s, 3600); return $n . " hour"   . ($n === 1 ? "" : "s") . " ago"; }
        $n = intdiv($s, 86400);
        return $n . " day" . ($n === 1 ? "" : "s") . " ago";
    }

    // Skryté pole "kam sa vrátiť" po akcii (aktuálna stránka)
    function back_field() {
        return '<input type="hidden" name="back" value="' . e($_SERVER["REQUEST_URI"] ?? "") . '">';
    }

    // Povolí len cestu v rámci stránky (žiadne cudzie adresy)
    function safe_back($b, $default) {
        $b = (string)$b;
        if ($b !== "" && $b[0] === "/" && strlen($b) < 500 && !preg_match('#^//|[\r\n\\\\]#', $b)) {
            return $b;
        }
        return $default;
    }

    // Zvonček s notifikáciami (rozbaľovací zoznam), len pre prihláseného používateľa
    function bell_html($conn, $user) {
        $uid = (int)$user["id"];

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        $unread = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

        $stmt = mysqli_prepare($conn,
            "SELECT n.id, n.type, n.is_read, n.from_user_id, n.media_id, u.username, u.avatar,
                    TIMESTAMPDIFF(SECOND, n.created_at, NOW()) AS ago,
                    f.status AS fstatus, m.caption AS clip_caption
               FROM notifications n
               JOIN users u ON u.id = n.from_user_id
               LEFT JOIN friendships f ON f.requester_id = n.from_user_id AND f.addressee_id = n.user_id
               LEFT JOIN game_media m ON m.id = n.media_id
              WHERE n.user_id = ?
              ORDER BY n.created_at DESC, n.id DESC
              LIMIT 20");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        $h  = '<details class="bell" id="bell" data-csrf="' . e(csrf_token()) . '">';
        $h .= '<summary class="notification" title="Notifications">🕭';
        if ($unread) {
            $h .= '<span class="bell-badge">' . ($unread > 9 ? "9+" : $unread) . '</span>';
        }
        $h .= '</summary><div class="bell-menu">';
        $h .= '<div class="bell-head"><strong>Notifications</strong>';
        if ($unread) {
            $h .= '<form method="POST" action="notifications.php">' . csrf_field() . back_field()
                . '<input type="hidden" name="action" value="read_all">'
                . '<button type="submit" class="rate-remove">Mark all as read</button></form>';
        }
        $h .= '</div>';

        if (!$items) {
            $h .= '<p class="bell-empty">No notifications yet.</p>';
        }

        foreach ($items as $it) {
            $fid  = (int)$it["from_user_id"];
            $name = '<a href="account.php?id=' . $fid . '"><strong>' . e($it["username"]) . '</strong></a>';

            $h .= '<div class="bell-item' . ($it["is_read"] ? "" : " unread") . '">';
            $h .= '<a href="account.php?id=' . $fid . '">' . avatar_html($it["username"], $it["avatar"], "avatar-sm") . '</a>';
            $h .= '<div class="bell-text">';

            if ($it["type"] === "friend_request") {
                $h .= '<div>' . $name . ' sent you a friend request</div>';
                $h .= '<small>' . e(time_ago($it["ago"])) . '</small>';

                if ($it["fstatus"] === "pending") {
                    $h .= '<div class="bell-actions">';
                    foreach (["accept" => "ACCEPT", "decline" => "DECLINE"] as $act => $label) {
                        $h .= '<form method="POST" action="friend.php">' . csrf_field() . back_field()
                            . '<input type="hidden" name="action" value="' . $act . '">'
                            . '<input type="hidden" name="user_id" value="' . $fid . '">'
                            . '<button type="submit" class="bell-btn' . ($act === "decline" ? " muted" : "") . '">' . $label . '</button></form>';
                    }
                    $h .= '</div>';
                } elseif ($it["fstatus"] === "accepted") {
                    $h .= '<div class="bell-state">✓ You are now friends</div>';
                } else {
                    $h .= '<div class="bell-state">Request is no longer available</div>';
                }
            }
            elseif ($it["type"] === "friend_accepted") {
                $h .= '<div>' . $name . ' accepted your friend request</div>';
                $h .= '<small>' . e(time_ago($it["ago"])) . '</small>';
            }
            elseif ($it["type"] === "clip_like" || $it["type"] === "clip_comment") {
                $isLike  = $it["type"] === "clip_like";
                $clipUrl = "clip.php?id=" . (int)$it["media_id"] . ($isLike ? "" : "#comments");
                $caption = (string)$it["clip_caption"];

                $h .= '<div>' . $name . ($isLike ? ' liked ' : ' commented on ')
                    . '<a href="' . e($clipUrl) . '"><strong>your clip</strong></a>'
                    . ($caption !== "" ? ' “' . e(log_short($caption, 40)) . '”' : "") . '</div>';
                $h .= '<small>' . e(time_ago($it["ago"])) . '</small>';
            }

            $h .= '</div></div>';
        }

        $h .= '</div></details>';

        // skript sa vypíše len raz: po otvorení označí notifikácie ako prečítané, klik mimo zvonček zavrie
        static $script = false;
        if (!$script) {
            $script = true;
            $h .= '<script>(function(){var d=document.getElementById("bell");if(!d){return;}'
                . 'd.addEventListener("toggle",function(){if(!d.open){return;}var b=d.querySelector(".bell-badge");if(!b){return;}'
                . 'var fd=new FormData();fd.append("csrf",d.getAttribute("data-csrf"));fd.append("action","read_all");'
                . 'fetch("notifications.php",{method:"POST",body:fd,headers:{"X-Requested-With":"fetch"},credentials:"same-origin"})'
                . '.then(function(){if(b.parentNode){b.parentNode.removeChild(b);}});});'
                . 'document.addEventListener("click",function(e){if(d.open&&!d.contains(e.target)){d.open=false;}});})();</script>';
        }

        return $h;
    }

    /* ---------- Lajky a komentáre klipov ---------- */

    // Pošle notifikáciu majiteľovi klipu (typ: clip_like / clip_comment). Sebe sa nič neposiela.
    function notify_clip($conn, $toUserId, $fromUserId, $type, $mediaId) {
        if ((int)$toUserId === (int)$fromUserId) {
            return;
        }
        $to = (int)$toUserId; $from = (int)$fromUserId; $mid = (int)$mediaId;

        if ($type === "clip_like") {
            // jeden lajk = jedna notifikácia (opakované lajkovanie ich nehromadí)
            drop_clip_notification($conn, $to, $from, $type, $mid);
        }
        $stmt = mysqli_prepare($conn,
            "INSERT INTO notifications (user_id, from_user_id, type, media_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iisi", $to, $from, $type, $mid);
        mysqli_stmt_execute($stmt);
    }

    function drop_clip_notification($conn, $toUserId, $fromUserId, $type, $mediaId) {
        $to = (int)$toUserId; $from = (int)$fromUserId; $mid = (int)$mediaId;
        $stmt = mysqli_prepare($conn,
            "DELETE FROM notifications WHERE user_id = ? AND from_user_id = ? AND type = ? AND media_id = ?");
        mysqli_stmt_bind_param($stmt, "iisi", $to, $from, $type, $mid);
        mysqli_stmt_execute($stmt);
    }

    // Koľko lajkov dostali všetky klipy používateľa dokopy
    function clip_likes_received($conn, $userId) {
        $uid = (int)$userId;
        $stmt = mysqli_prepare($conn,
            "SELECT COUNT(*) FROM media_likes l JOIN game_media m ON m.id = l.media_id WHERE m.user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        return (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    }


    /* ---------- Screenshoty a klipy od používateľov ---------- */

    const MEDIA_DIR = "uploads/media";

    // Bezpečná cesta k nahranému screenshotu alebo ""
    function media_src($file) {
        return preg_match('/^[A-Za-z0-9_.\-]{1,120}$/', (string)$file) ? MEDIA_DIR . "/" . $file : "";
    }

    function e($s) {
        return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
    }

?>