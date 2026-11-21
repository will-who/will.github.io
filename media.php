<?php

    include("auth.php");

    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    // Ak je súbor väčší než post_max_size, PHP zahodí celý formulár (POST aj FILES zostanú prázdne)
    if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_POST) && empty($_FILES) && (int)($_SERVER["CONTENT_LENGTH"] ?? 0) > 0) {
        parse_str((string)parse_url((string)($_SERVER["HTTP_REFERER"] ?? ""), PHP_URL_QUERY), $refQuery);
        $refId = (int)($refQuery["id"] ?? 0);

        $_SESSION["media_flash"] = ["error", "The file is too large for the server (max "
            . floor(clip_upload_limit() / 1048576) . " MB). Try a smaller video."];
        header("Location: " . ($refId ? "game.php?id=" . $refId . "#media" : "track.php"));
        exit;
    }

    $uid    = (int)$user["id"];
    $gameId = filter_input(INPUT_POST, "game_id", FILTER_VALIDATE_INT);

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !$gameId) {
        header("Location: track.php");
        exit;
    }

    $back = "game.php?id=" . $gameId . "#media";

    function media_flash($type, $msg) {
        $_SESSION["media_flash"] = [$type, $msg];
    }

    function media_done($back) {
        header("Location: " . $back);
        exit;
    }

    // Z odkazu na YouTube (watch, youtu.be, shorts, embed, live) vytiahne 11-znakové ID, inak null
    function youtube_id($url) {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        $p    = parse_url($url);
        $host = strtolower($p["host"] ?? "");
        $host = preg_replace('/^(www|m|music)\./', "", $host);
        $path = $p["path"] ?? "";
        $id   = null;

        if ($host === "youtu.be") {
            $id = explode("/", ltrim($path, "/"))[0] ?? null;
        }
        elseif ($host === "youtube.com") {
            if ($path === "/watch") {
                parse_str($p["query"] ?? "", $q);
                $id = $q["v"] ?? null;
            }
            elseif (preg_match('#^/(shorts|embed|live)/([^/?]+)#', $path, $m)) {
                $id = $m[2];
            }
        }
        return ($id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) ? $id : null;
    }

    if (!csrf_valid()) {
        media_flash("error", "Invalid form, please try again.");
        media_done($back);
    }

    // hra musí existovať
    $stmt = mysqli_prepare($conn, "SELECT name FROM games WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $gameId);
    mysqli_stmt_execute($stmt);
    $game = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$game) {
        header("Location: track.php");
        exit;
    }

    $action  = $_POST["action"] ?? "";
    $caption = trim($_POST["caption"] ?? "");

    /* ---------- Zmazanie (autor alebo editor / admin) ---------- */

    if ($action === "delete") {

        $mid = filter_input(INPUT_POST, "media_id", FILTER_VALIDATE_INT);

        $stmt = mysqli_prepare($conn,
            "SELECT m.id, m.user_id, m.type, m.file, u.username
               FROM game_media m JOIN users u ON u.id = m.user_id
              WHERE m.id = ? AND m.game_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $mid, $gameId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$row) {
            media_flash("error", "Item not found.");
        }
        elseif ((int)$row["user_id"] !== $uid && !is_editor($user)) {
            media_flash("error", "You cannot delete this item.");
        }
        else {
            $stmt = mysqli_prepare($conn, "DELETE FROM game_media WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $row["id"]);
            mysqli_stmt_execute($stmt);

            if (media_src($row["file"]) !== "") {          // screenshot aj nahraný MP4 klip
                @unlink(__DIR__ . "/" . media_src($row["file"]));
            }
            if ((int)$row["user_id"] !== $uid) {
                log_change($conn, $user, "edited", "game", $game["name"],
                           "removed " . $row["type"] . " added by " . $row["username"]);
            }
            media_flash("ok", ucfirst($row["type"]) . " removed.");
        }
        media_done($back);
    }

    /* ---------- Pridanie ---------- */

    if (mb_strlen($caption, "UTF-8") > 150) {
        media_flash("error", "Caption is too long (max 150 characters).");
        media_done($back);
    }

    if (!is_editor($user)) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM game_media WHERE game_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $gameId, $uid);
        mysqli_stmt_execute($stmt);
        if ((int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] >= 10) {
            media_flash("error", "You can add at most 10 screenshots and clips per game.");
            media_done($back);
        }
    }

    if ($action === "add_clip") {

        $vid = youtube_id($_POST["clip_url"] ?? "");

        if (!$vid) {
            media_flash("error", "Please paste a valid YouTube link (youtube.com or youtu.be).");
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO game_media (game_id, user_id, type, video_id, caption) VALUES (?, ?, 'clip', ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiss", $gameId, $uid, $vid, $caption);
            mysqli_stmt_execute($stmt);
            media_flash("ok", "Clip added.");
        }
        media_done($back);
    }

    if ($action === "add_clip_file") {

        $f     = $_FILES["clip_file"] ?? null;
        $dir   = __DIR__ . "/" . MEDIA_DIR;
        $limit = clip_upload_limit();
        $maxMb = floor($limit / 1048576);

        if (!$f || $f["error"] === UPLOAD_ERR_NO_FILE) {
            media_flash("error", "Choose an MP4 file first.");
        }
        elseif (in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f["size"] > $limit) {
            media_flash("error", "The video is too large (max " . $maxMb . " MB).");
        }
        elseif ($f["error"] !== UPLOAD_ERR_OK) {
            media_flash("error", "Upload failed, try again.");
        }
        elseif (strtolower(pathinfo((string)$f["name"], PATHINFO_EXTENSION)) !== "mp4") {
            media_flash("error", "Only MP4 videos are allowed.");
        }
        else {
            // obsah musí byť naozaj MP4 video: v hlavičke je na pozícii 4 "ftyp" a typ súboru je video/*
            $head = (string)@file_get_contents($f["tmp_name"], false, null, 0, 12);
            $mime = function_exists("mime_content_type") ? (string)@mime_content_type($f["tmp_name"]) : "";

            if (strlen($head) < 12 || substr($head, 4, 4) !== "ftyp" || ($mime !== "" && strpos($mime, "video/") !== 0)) {
                media_flash("error", "This file is not a valid MP4 video.");
            }
            elseif (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                media_flash("error", "Could not create the upload folder.");
            }
            else {
                $name = $gameId . "_" . $uid . "_" . bin2hex(random_bytes(8)) . ".mp4";

                if (move_uploaded_file($f["tmp_name"], $dir . "/" . $name)) {
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO game_media (game_id, user_id, type, file, caption) VALUES (?, ?, 'clip', ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiss", $gameId, $uid, $name, $caption);
                    mysqli_stmt_execute($stmt);
                    media_flash("ok", "Clip uploaded.");
                } else {
                    media_flash("error", "Could not save the video.");
                }
            }
        }
        media_done($back);
    }

    if ($action === "add_screenshot") {

        $f     = $_FILES["screenshot"] ?? null;
        $types = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_GIF => "gif", IMAGETYPE_WEBP => "webp"];
        $dir   = __DIR__ . "/" . MEDIA_DIR;

        if (!$f || $f["error"] === UPLOAD_ERR_NO_FILE) {
            media_flash("error", "Choose an image first.");
        }
        elseif (in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f["size"] > 5 * 1024 * 1024) {
            media_flash("error", "The image is too large (max 5 MB).");
        }
        elseif ($f["error"] !== UPLOAD_ERR_OK) {
            media_flash("error", "Upload failed, try again.");
        }
        else {
            $info = @getimagesize($f["tmp_name"]);

            if (!$info || !isset($types[$info[2]])) {
                media_flash("error", "Only JPG, PNG, GIF or WEBP images are allowed.");
            }
            elseif ($info[0] > 8000 || $info[1] > 8000) {
                media_flash("error", "The image is too big (max 8000 × 8000 px).");
            }
            elseif (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                media_flash("error", "Could not create the upload folder.");
            }
            else {
                $name = $gameId . "_" . $uid . "_" . bin2hex(random_bytes(8)) . "." . $types[$info[2]];

                if (move_uploaded_file($f["tmp_name"], $dir . "/" . $name)) {
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO game_media (game_id, user_id, type, file, caption) VALUES (?, ?, 'screenshot', ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiss", $gameId, $uid, $name, $caption);
                    mysqli_stmt_execute($stmt);
                    media_flash("ok", "Screenshot added.");
                } else {
                    media_flash("error", "Could not save the image.");
                }
            }
        }
        media_done($back);
    }

    media_done($back);

?>