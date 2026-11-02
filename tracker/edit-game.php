<?php

    include("auth.php");
    $user = require_editor($conn);

    $isAjax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";

    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $game = null;

    if ($id) {
        $stmt = mysqli_prepare($conn,
            "SELECT id, name, image_back, image_front, image_size, image_position, description
               FROM games WHERE id = ? LIMIT 1");
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
        "title"       => $game["name"],
        "tags"        => array_column(game_tags($conn, (int)$game["id"]), "id"),
        "image"       => $game["image_back"] ?? "",
        "description" => $game["description"] ?? "",
    ];
    $allTags = all_tags($conn);
    $old     = $values;     // pôvodné hodnoty pre zoznam zmien
    $error = "";
    $saved = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        /* ---------- Vymazanie hry ---------- */
        if (($_POST["action"] ?? "") === "delete") {

            if (csrf_valid()) {
                $stmt = mysqli_prepare($conn, "DELETE FROM games WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);   // favorites sa zmažú cez ON DELETE CASCADE
                log_change($conn, $user, "deleted", "game", $game["name"]);
                header("Location: track.php");
                exit;
            }
            $error = "Invalid form, please reload the page.";
        }

        /* ---------- Uloženie zmien ---------- */
        else {

            $values = game_input();

            if (!csrf_valid()) {
                $error = "Invalid form, please reload the page.";
            } else {
                $error = game_validate($values);
            }

            if (!$error) {
                $image = $values["image"]       === "" ? null : $values["image"];
                $desc  = $values["description"] === "" ? null : $values["description"];

                try {
                    $stmt = mysqli_prepare($conn,
                        "UPDATE games SET name = ?, image_back = ?, description = ?, updated_at = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssi", $values["title"], $image, $desc, $id);
                    mysqli_stmt_execute($stmt);
                    save_game_tags($conn, $id, $values["tags"]);
                    $saved = true;

                    // čo sa presne zmenilo
                    $changes = [];
                    if ($old["title"] !== $values["title"]) {
                        $changes[] = 'title: "' . log_short($old["title"]) . '" → "' . log_short($values["title"]) . '"';
                    }
                    if ($old["image"] !== $values["image"]) {
                        $changes[] = "image source changed";
                    }
                    if ($old["description"] !== $values["description"]) {
                        $changes[] = "description changed";
                    }
                    $tagNames = array_column($allTags, "name", "id");
                    $oldT = array_map("intval", $old["tags"]);
                    $newT = array_map("intval", $values["tags"]);
                    sort($oldT); sort($newT);
                    if ($oldT !== $newT) {
                        $fmt = function ($ids) use ($tagNames) {
                            return $ids ? implode(", ", array_map(function ($i) use ($tagNames) { return $tagNames[$i] ?? "#" . $i; }, $ids)) : "none";
                        };
                        $changes[] = "tags: " . $fmt($oldT) . " → " . $fmt($newT);
                    }
                    if ($changes) {
                        log_change($conn, $user, "edited", "game", $values["title"], implode("; ", $changes));
                    }
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
    }

    $imgBack  = img_url($game["image_back"]);
    $imgFront = img_url($game["image_front"]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title>Edit game – <?= e($game["name"]) ?></title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="logo.jpg" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php" class="active">HOME</a>
            <a href="#">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <a href="settings.php" class="notification users-link" title="Settings">⚙</a>

            <span class="username"><?= e($user["username"]) ?></span>

            <form method="POST" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="account-button">LOGOUT</button>
            </form>

        </div>

    </header>


    <main class="content">

        <a href="<?= e(back_url((int)$game["id"])) ?>" class="back-link">← Back to games</a>

        <div class="content-header">

            <h1>Edit game</h1>

            <a href="game.php?id=<?= (int)$game["id"] ?>" class="account-button">VIEW GAME PAGE</a>

        </div>


        <div class="game-detail">

            <!-- Náhľad obrázka (rovnaký ako na stránke hry) -->
            <div
                class="game-detail-image"
                id="preview"
                <?php if ($imgBack): ?>
                style="background-image: url('<?= e($imgBack) ?>'); background-size: <?= e(css_val($game["image_size"], "cover")) ?>; background-position: <?= e(css_val($game["image_position"], "center")) ?>;"
                <?php endif; ?>
            >
                <?php if ($imgFront): ?>
                <div class="game-title">
                    <img class="game-logo" src="<?= e($imgFront) ?>" alt="<?= e($game["name"]) ?>">
                </div>
                <?php endif; ?>
            </div>


            <div class="game-detail-info">

                <form method="POST" action="edit-game.php?id=<?= (int)$game["id"] ?>" id="gameForm" class="edit-form">
                    <?= csrf_field() ?>

                    <label for="title">Game title</label>
                    <input type="text" id="title" name="title" class="edit-title" maxlength="100"
                           value="<?= e($values["title"]) ?>" required>

                    <span class="edit-label">Tags</span>
                    <?= tag_picker($allTags, $values["tags"]) ?>

                    <label for="image">Img source</label>
                    <input type="text" id="image" name="image" maxlength="500"
                           placeholder="https://… or img/game.png"
                           value="<?= e($values["image"]) ?>">

                    <label for="description">About this game</label>
                    <textarea id="description" name="description" maxlength="2000"
                              placeholder="Write a description of the game…"><?= e($values["description"]) ?></textarea>

                    <button type="submit" class="auth-submit edit-save" id="saveButton">SAVE</button>

                    <div class="edit-status<?= $saved ? " ok" : "" ?>" id="formMessage"><?php
                        if ($error) { echo e($error); }
                        elseif ($saved) { echo "Saved ✓"; }
                    ?></div>

                </form>


                <!-- Vymazanie hry (bez JS: najprv sa rozbalí potvrdenie) -->
                <details class="danger-zone">
                    <summary>Delete game</summary>

                    <p>
                        This permanently removes <strong><?= e($game["name"]) ?></strong>,
                        including everyone's favorites of it. This cannot be undone.
                    </p>

                    <form method="POST" action="edit-game.php?id=<?= (int)$game["id"] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="danger-button">YES, DELETE THIS GAME</button>
                    </form>
                </details>

            </div>

        </div>

    </main>


    <script>
        // Automatické ukladanie: po každej zmene sa po krátkej pauze odošle formulár do DB.
        // Bez JS funguje formulár normálne cez tlačidlo SAVE.
        (function () {
            var form    = document.getElementById("gameForm");
            var msg     = document.getElementById("formMessage");
            var btn     = document.getElementById("saveButton");
            var preview = document.getElementById("preview");
            var timer   = null;

            if (!window.fetch || !window.FormData) { return; }
            btn.style.display = "none";

            function setMsg(text, cls) {
                msg.className = "edit-status " + (cls || "");
                msg.textContent = text;
            }

            function save() {
                setMsg("Saving…", "muted");
                fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    headers: { "X-Requested-With": "fetch" },
                    credentials: "same-origin"
                })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.ok) {
                        setMsg("Saved ✓", "ok");
                        var v = form.elements["image"].value.trim();
                        preview.style.backgroundImage = v ? "url(" + JSON.stringify(v) + ")" : "none";
                    } else {
                        setMsg(j.error || "Save failed.", "");
                    }
                })
                .catch(function () { setMsg("Save failed.", ""); });
            }

            form.addEventListener("input", function () {
                clearTimeout(timer);
                setMsg("Unsaved changes…", "muted");
                timer = setTimeout(save, 700);
            });
        })();
    </script>

</body>
</html>