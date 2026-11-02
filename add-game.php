<?php

    include("auth.php");
    $user = require_editor($conn);

    $values = ["title" => "", "tags" => [], "image" => ""];
    $allTags = all_tags($conn);
    $error  = "";
    $saved  = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $values = game_input();

        if (!csrf_valid()) {
            $error = "Invalid form, please try again.";
        } else {
            $error = game_validate($values);
        }

        if (!$error) {
            $image = $values["image"] === "" ? null : $values["image"];

            try {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO games (name, image_back) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ss", $values["title"], $image);
                mysqli_stmt_execute($stmt);

                $newId = (int)mysqli_insert_id($conn);
                save_game_tags($conn, $newId, $values["tags"]);

                log_change($conn, $user, "added", "game", $values["title"]);

                header("Location: track.php");
                exit;
            }
            catch (mysqli_sql_exception $ex) {
                $error = ($ex->getCode() === 1062)
                    ? "A game with this title already exists."
                    : "Could not add the game, try again later.";
            }
        }
    }

    $formTitle    = "Add game";
    $formSubtitle = "Add a new game to the launcher";
    $formAction   = "add-game.php";
    $submitLabel  = "ADD GAME";
    $autosave     = false;

    include("game-form.php");

?>