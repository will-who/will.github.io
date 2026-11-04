<?php

    include("auth.php");

    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    // Admin účty nemajú obľúbené hry
    if (is_admin($user)) {
        header("Location: track.php");
        exit;
    }

    $sort   = ($_POST["sort"] ?? "az") === "updated" ? "updated" : "az";
    $view   = in_array($_POST["view"] ?? "", ["games", "favorites"], true) ? $_POST["view"] : "home";
    $tag    = filter_input(INPUT_POST, "tag", FILTER_VALIDATE_INT) ?: 0;
    $search = trim(mb_substr((string)($_POST["q"] ?? ""), 0, 50, "UTF-8"));
    $gameId = filter_input(INPUT_POST, "game_id", FILTER_VALIDATE_INT);

    if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_valid() && $gameId) {

        $uid = (int)$user["id"];

        // Ak je hra už medzi obľúbenými, odstráni sa, inak sa pridá
        $stmt = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ? AND game_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $uid, $gameId);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) === 0) {
            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO favorites (user_id, game_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ii", $uid, $gameId);
                mysqli_stmt_execute($stmt);
            }
            catch (mysqli_sql_exception $ex) {
                // hra neexistuje – nič sa nestane
            }
        }
    }

    header("Location: " . track_url($view, $sort, $tag, $search) . ($gameId ? "#game-" . $gameId : ""));
    exit;

?>