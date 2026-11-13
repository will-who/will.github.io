<?php

    include("auth.php");

    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $gameId = filter_input(INPUT_POST, "game_id", FILTER_VALIDATE_INT);

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !$gameId) {
        header("Location: track.php");
        exit;
    }

    $uid  = (int)$user["id"];
    $back = "game.php?id=" . $gameId . "#stats";

    function stats_flash($type, $msg) {
        $_SESSION["stats_flash"] = [$type, $msg];
    }

    function stats_done($url) {
        header("Location: " . $url);
        exit;
    }

    if (!csrf_valid()) {
        stats_flash("error", "Invalid form, please try again.");
        stats_done($back);
    }

    // Admin staty len schvaľuje
    if (is_admin($user)) {
        stats_flash("error", "Admin accounts review stats, they cannot add them.");
        stats_done($back);
    }

    // hra musí existovať
    $stmt = mysqli_prepare($conn, "SELECT id FROM games WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $gameId);
    mysqli_stmt_execute($stmt);
    if (!mysqli_fetch_row(mysqli_stmt_get_result($stmt))) {
        header("Location: track.php");
        exit;
    }

    $action = $_POST["action"] ?? "submit";

    /* ---------- Zrušenie vlastného čakajúceho záznamu ---------- */

    if ($action === "cancel") {

        $sid = filter_input(INPUT_POST, "stat_id", FILTER_VALIDATE_INT);

        $stmt = mysqli_prepare($conn,
            "SELECT id, screenshot FROM game_stats
              WHERE id = ? AND user_id = ? AND game_id = ? AND status = 'pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iii", $sid, $uid, $gameId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $stmt = mysqli_prepare($conn, "DELETE FROM game_stats WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $row["id"]);
            mysqli_stmt_execute($stmt);

            if (stat_src($row["screenshot"]) !== "") {
                @unlink(__DIR__ . "/" . stat_src($row["screenshot"]));
            }
            stats_flash("ok", "Submission cancelled.");
        } else {
            stats_flash("error", "Submission not found or already reviewed.");
        }
        stats_done("game-stats.php?id=" . $gameId . "#mine");
    }

    /* ---------- Nové staty ---------- */

    $posted = is_array($_POST["stat"] ?? null) ? $_POST["stat"] : [];
    $values = [];

    foreach (STAT_FIELDS as $key => $label) {
        $n = filter_var($posted[$key] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 99999]]);

        if ($n === false) {
            stats_flash("error", $label . " must be a whole number from 0 to 99999.");
            stats_done($back);
        }
        $values[$key] = $n;
    }

    // aby sa nedali zaplaviť čakajúce záznamy
    $stmt = mysqli_prepare($conn,
        "SELECT COUNT(*) FROM game_stats WHERE game_id = ? AND user_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "ii", $gameId, $uid);
    mysqli_stmt_execute($stmt);
    if ((int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] >= 5) {
        stats_flash("error", "You already have 5 submissions waiting for approval for this game. Wait for the admin or cancel one.");
        stats_done($back);
    }

    // screenshot je povinný
    $f     = $_FILES["screenshot"] ?? null;
    $types = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_GIF => "gif", IMAGETYPE_WEBP => "webp"];
    $dir   = __DIR__ . "/" . STATS_DIR;

    if (!$f || $f["error"] === UPLOAD_ERR_NO_FILE) {
        stats_flash("error", "A screenshot is required as proof of your stats.");
        stats_done($back);
    }
    if (in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f["size"] > 5 * 1024 * 1024) {
        stats_flash("error", "The image is too large (max 5 MB).");
        stats_done($back);
    }
    if ($f["error"] !== UPLOAD_ERR_OK) {
        stats_flash("error", "Upload failed, try again.");
        stats_done($back);
    }

    $info = @getimagesize($f["tmp_name"]);

    if (!$info || !isset($types[$info[2]])) {
        stats_flash("error", "Only JPG, PNG, GIF or WEBP images are allowed.");
        stats_done($back);
    }
    if ($info[0] > 8000 || $info[1] > 8000) {
        stats_flash("error", "The image is too big (max 8000 × 8000 px).");
        stats_done($back);
    }
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        stats_flash("error", "Could not create the upload folder.");
        stats_done($back);
    }

    $name = "stat_" . $gameId . "_" . $uid . "_" . bin2hex(random_bytes(8)) . "." . $types[$info[2]];

    if (!move_uploaded_file($f["tmp_name"], $dir . "/" . $name)) {
        stats_flash("error", "Could not save the image.");
        stats_done($back);
    }

    try {
        $cols = array_keys(STAT_FIELDS);       // názvy stĺpcov pochádzajú z konštanty, nie od používateľa
        $stmt = mysqli_prepare($conn,
            "INSERT INTO game_stats (game_id, user_id, screenshot, " . implode(", ", $cols) . ")
             VALUES (?, ?, ?, " . implode(", ", array_fill(0, count($cols), "?")) . ")");
        mysqli_stmt_bind_param($stmt, "iis" . str_repeat("i", count($cols)), $gameId, $uid, $name, ...array_values($values));
        mysqli_stmt_execute($stmt);

        stats_flash("ok", "Thanks, your stats were sent. They show up after an admin approves them.");
    }
    catch (mysqli_sql_exception $ex) {
        @unlink($dir . "/" . $name);
        stats_flash("error", "Could not save your stats, try again later.");
    }

    stats_done($back);

?>