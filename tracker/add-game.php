<?php

    include("auth.php");
    $user = require_admin($conn);

    $values = ["title" => "", "tags" => "", "image" => ""];
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
            $tags  = $values["tags"]  === "" ? null : $values["tags"];
            $image = $values["image"] === "" ? null : $values["image"];

            try {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO games (name, genre, image_back) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sss", $values["title"], $tags, $image);
                mysqli_stmt_execute($stmt);

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