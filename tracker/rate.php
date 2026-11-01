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

    $back = "game.php?id=" . $gameId . "#rating";

    if (!csrf_valid()) {
        flash("error", "Invalid form, please try again.");
        header("Location: " . $back);
        exit;
    }

    // Admin účty nehodnotia
    if (is_admin($user)) {
        flash("error", "Admin accounts cannot rate games.");
        header("Location: " . $back);
        exit;
    }

    $uid = (int)$user["id"];

    if (($_POST["action"] ?? "") === "remove") {

        $stmt = mysqli_prepare($conn, "DELETE FROM ratings WHERE user_id = ? AND game_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $uid, $gameId);
        mysqli_stmt_execute($stmt);
        flash("ok", "Your rating was removed.");

    } else {

        $rating = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_INT,
                               ["options" => ["min_range" => 1, "max_range" => 5]]);

        if (!$rating) {
            flash("error", "Pick a rating from 1 to 5 stars.");
        } else {
            try {
                // nové hodnotenie, alebo prepísanie existujúceho
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO ratings (user_id, game_id, rating) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating)");
                mysqli_stmt_bind_param($stmt, "iii", $uid, $gameId, $rating);
                mysqli_stmt_execute($stmt);
                flash("ok", "Thanks, your rating was saved.");
            }
            catch (mysqli_sql_exception $ex) {
                flash("error", "Game not found.");
            }
        }
    }

    header("Location: " . $back);
    exit;

?>