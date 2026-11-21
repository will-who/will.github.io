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

    // Uloží jeden nahraný obrázok z poľa $field. Vráti [názov súboru, null] alebo [null, text chyby].
    function save_stat_image($field, $what, $gameId, $uid) {
        $f     = $_FILES[$field] ?? null;
        $types = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_GIF => "gif", IMAGETYPE_WEBP => "webp"];
        $dir   = __DIR__ . "/" . STATS_DIR;

        if (!$f || $f["error"] === UPLOAD_ERR_NO_FILE) {
            return [null, $what . " is required."];
        }
        if (in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f["size"] > 5 * 1024 * 1024) {
            return [null, $what . ": the image is too large (max 5 MB)."];
        }
        if ($f["error"] !== UPLOAD_ERR_OK) {
            return [null, $what . ": upload failed, try again."];
        }

        $info = @getimagesize($f["tmp_name"]);

        if (!$info || !isset($types[$info[2]])) {
            return [null, $what . ": only JPG, PNG, GIF or WEBP images are allowed."];
        }
        if ($info[0] > 8000 || $info[1] > 8000) {
            return [null, $what . ": the image is too big (max 8000 × 8000 px)."];
        }
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return [null, "Could not create the upload folder."];
        }

        $name = "stat_" . $gameId . "_" . $uid . "_" . bin2hex(random_bytes(8)) . "." . $types[$info[2]];

        if (!move_uploaded_file($f["tmp_name"], $dir . "/" . $name)) {
            return [null, "Could not save the image."];
        }
        return [$name, null];
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
    $stmt = mysqli_prepare($conn, "SELECT id, name FROM games WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $gameId);
    mysqli_stmt_execute($stmt);
    $gameRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$gameRow) {
        header("Location: track.php");
        exit;
    }

    $action = $_POST["action"] ?? "submit";

    /* ---------- Zrušenie vlastného čakajúceho záznamu ---------- */

    if ($action === "cancel") {

        $sid = filter_input(INPUT_POST, "stat_id", FILTER_VALIDATE_INT);

        $stmt = mysqli_prepare($conn,
            "SELECT id, screenshot, rank_screenshot FROM game_stats
              WHERE id = ? AND user_id = ? AND game_id = ? AND status = 'pending' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iii", $sid, $uid, $gameId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $stmt = mysqli_prepare($conn, "DELETE FROM game_stats WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $row["id"]);
            mysqli_stmt_execute($stmt);

            foreach ([$row["screenshot"], $row["rank_screenshot"]] as $file) {
                if (stat_src($file) !== "") {
                    @unlink(__DIR__ . "/" . stat_src($file));
                }
            }
            stats_flash("ok", "Submission cancelled.");
        } else {
            stats_flash("error", "Submission not found or already reviewed.");
        }
        stats_done("game-stats.php?id=" . $gameId . "#mine");
    }

    /* ---------- Nové staty ---------- */

    // režim: Quick play alebo Ranked
    $mode = (string)($_POST["mode"] ?? "");

    if (!array_key_exists($mode, STAT_MODES)) {
        stats_flash("error", "Choose Quick play or Ranked.");
        stats_done($back);
    }

    // nick v hre je povinný (bez riadiacich znakov, najviac 40 znakov)
    $nick = trim(preg_replace('/[\x00-\x1F\x7F]+/u', "", (string)($_POST["nickname"] ?? "")));

    if ($nick === "") {
        stats_flash("error", "Enter your nickname from the game.");
        stats_done($back);
    }
    if (mb_strlen($nick, "UTF-8") > 40) {
        stats_flash("error", "The nickname is too long (max 40 characters).");
        stats_done($back);
    }

    // meno postavy: povinné okrem hier, kde sa postava nevyberá (Call of Duty, Fortnite)
    $character = "";

    if (game_needs_character($gameRow["name"])) {
        $character = trim(preg_replace('/[\x00-\x1F\x7F]+/u', "", (string)($_POST["character"] ?? "")));

        if ($character === "") {
            stats_flash("error", "Enter the name of the character you played.");
            stats_done($back);
        }
        if (mb_strlen($character, "UTF-8") > 60) {
            stats_flash("error", "The character name is too long (max 60 characters).");
            stats_done($back);
        }
    }

    // staty pre túto hru a tento režim (napr. Apex: knocks, damage, pri Ranked aj RP)
    $fields = stat_fields_for($gameRow["name"], $mode);
    $posted = is_array($_POST["stat"] ?? null) ? $_POST["stat"] : [];
    $values = [];

    foreach ($fields as $key => $label) {
        [, $min, $max] = STAT_COLUMNS[$key];
        $n = filter_var($posted[$key] ?? "", FILTER_VALIDATE_INT, ["options" => ["min_range" => $min, "max_range" => $max]]);

        if ($n === false) {
            stats_flash("error", $label . " must be a whole number from " . $min . " to " . $max . ".");
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

    // screenshot so statmi je povinný vždy
    [$shot, $err] = save_stat_image("screenshot", "A screenshot of your stats", $gameId, $uid);

    if ($err) {
        stats_flash("error", $err);
        stats_done($back);
    }

    // screenshot postupu v ranku je voliteľný (len pri Ranked)
    $rankShot = null;

    if ($mode === "ranked" && ($_FILES["rank_screenshot"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$rankShot, $err] = save_stat_image("rank_screenshot", "The rank progress screenshot", $gameId, $uid);

        if ($err) {
            @unlink(__DIR__ . "/" . STATS_DIR . "/" . $shot);
            stats_flash("error", $err);
            stats_done($back);
        }
    }

    try {
        $cols = array_keys($fields);           // názvy stĺpcov pochádzajú z konštanty, nie od používateľa
        $stmt = mysqli_prepare($conn,
            "INSERT INTO game_stats (game_id, user_id, nickname, character_name, `mode`, screenshot, rank_screenshot, " . implode(", ", $cols) . ")
             VALUES (?, ?, ?, ?, ?, ?, ?, " . implode(", ", array_fill(0, count($cols), "?")) . ")");
        mysqli_stmt_bind_param($stmt, "iisssss" . str_repeat("i", count($cols)),
                               $gameId, $uid, $nick, $character, $mode, $shot, $rankShot, ...array_values($values));
        mysqli_stmt_execute($stmt);

        stats_flash("ok", "Thanks, your stats were sent. They show up after an admin approves them.");
    }
    catch (mysqli_sql_exception $ex) {
        @unlink(__DIR__ . "/" . STATS_DIR . "/" . $shot);
        if ($rankShot) {
            @unlink(__DIR__ . "/" . STATS_DIR . "/" . $rankShot);
        }
        stats_flash("error", "Could not save your stats, try again later.");
    }

    stats_done($back);

?>