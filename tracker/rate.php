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

    // Moderovanie: len admin môže zmazať cudziu recenziu (hviezdičky + komentár)
    if (($_POST["action"] ?? "") === "delete_review") {

        if (!is_admin($user)) {
            flash("error", "You cannot delete reviews.");
        } else {
            $reviewUserId = filter_input(INPUT_POST, "review_user_id", FILTER_VALIDATE_INT);

            if ($reviewUserId) {
                $stmt = mysqli_prepare($conn, "DELETE FROM ratings WHERE user_id = ? AND game_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $reviewUserId, $gameId);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    flash("ok", "Review deleted.");
                } else {
                    flash("error", "Review not found.");
                }
            }
        }

        header("Location: game.php?id=" . $gameId . "#reviews");
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
        flash("ok", "Your review was removed.");

    } else {

        $rating  = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_INT,
                                ["options" => ["min_range" => 1, "max_range" => 5]]);
        $comment = trim((string)($_POST["comment"] ?? ""));

        $error = "";
        if (!$rating) {
            $error = "Pick a rating from 1 to 5 stars.";
        } elseif ($comment === "") {
            $error = "Please write a comment, it is required.";
        } elseif (mb_strlen($comment, "UTF-8") > 1000) {
            $error = "Comment is too long (max 1000 characters).";
        }

        if ($error !== "") {
            flash("error", $error);
            // napísaný text sa vráti do formulára, aby ho používateľ nemusel písať znova
            $_SESSION["review_draft"] = [
                "game_id" => $gameId,
                "rating"  => (int)$rating,
                "comment" => mb_substr($comment, 0, 1000, "UTF-8"),
            ];
        } else {
            try {
                // nová recenzia, alebo prepísanie existujúcej
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO ratings (user_id, game_id, rating, comment) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)");
                mysqli_stmt_bind_param($stmt, "iiis", $uid, $gameId, $rating, $comment);
                mysqli_stmt_execute($stmt);
                flash("ok", "Thanks, your review was saved.");
            }
            catch (mysqli_sql_exception $ex) {
                flash("error", "Game not found.");
            }
        }
    }

    header("Location: " . $back);
    exit;

?>