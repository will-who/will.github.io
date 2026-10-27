<?php

    include("auth.php");
    $user = require_admin($conn);

    $isAjax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";

    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $game = null;

    if ($id) {
        $stmt = mysqli_prepare($conn, "SELECT id, name, genre, image_back FROM games WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $game = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$game) {
        http_response_code(404);
        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode(["ok" => false, "error" => "Game not found."]);
            exit;
        }
        die("Game not found.");
    }

    $values = [
        "title" => $game["name"],
        "tags"  => $game["genre"] ?? "",
        "image" => $game["image_back"] ?? "",
    ];
    $error = "";
    $saved = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $values = game_input();

        if (!csrf_valid()) {
            $error = "Invalid form, please reload the page.";
        } else {
            $error = game_validate($values);
        }

        if (!$error) {
            $tags  = $values["tags"]  === "" ? null : $values["tags"];
            $image = $values["image"] === "" ? null : $values["image"];

            try {
                $stmt = mysqli_prepare($conn,
                    "UPDATE games SET name = ?, genre = ?, image_back = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $values["title"], $tags, $image, $id);
                mysqli_stmt_execute($stmt);
                $saved = true;
            }
            catch (mysqli_sql_exception $ex) {
                $error = ($ex->getCode() === 1062)
                    ? "A game with this title already exists."
                    : "Could not save, try again later.";
            }
        }

        // Automatické ukladanie (fetch) dostane JSON
        if ($isAjax) {
            header("Content-Type: application/json");
            echo json_encode(["ok" => $saved, "error" => $error]);
            exit;
        }
    }

    $formTitle    = "Edit game";
    $formSubtitle = "Changes are saved automatically";
    $formAction   = "edit-game.php?id=" . (int)$id;
    $submitLabel  = "SAVE";
    $autosave     = true;

    include("game-form.php");

?>