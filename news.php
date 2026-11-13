<?php

    include("auth.php");
    $user = require_editor($conn);

    $editId    = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT) ?: 0;
    $formError = "";
    $form      = ["title" => "", "body" => "", "image" => "", "game_id" => 0];
    $allGames  = mysqli_fetch_all(mysqli_query($conn, "SELECT id, name FROM games ORDER BY name"), MYSQLI_ASSOC);
    $posted    = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        if (!csrf_valid()) {
            flash("error", "Invalid form, please reload the page.");
            header("Location: news.php");
            exit;
        }

        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: 0;

        // Zmazanie
        if (($_POST["action"] ?? "") === "delete") {
            if ($id) {
                $stmt = mysqli_prepare($conn, "SELECT title FROM news WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $gone = mysqli_fetch_row(mysqli_stmt_get_result($stmt));

                $stmt = mysqli_prepare($conn, "DELETE FROM news WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                if ($gone) {
                    log_change($conn, $user, "deleted", "news", $gone[0]);
                }
                flash("ok", "News deleted.");
            }
            header("Location: news.php");
            exit;
        }

        // Uloženie (nové / úprava)
        $form = [
            "title" => trim($_POST["title"] ?? ""),
            "body"  => trim($_POST["body"] ?? ""),
            "image" => trim($_POST["image"] ?? ""),
            "game_id" => filter_input(INPUT_POST, "game_id", FILTER_VALIDATE_INT) ?: 0,
        ];
        $editId = $id;
        $posted = true;

        if ($form["title"] === "") {
            $formError = "Title is required.";
        } elseif (mb_strlen($form["title"], "UTF-8") > 150) {
            $formError = "Title is too long (max 150 characters).";
        } elseif (mb_strlen($form["body"], "UTF-8") > 300) {
            $formError = "Text is too long (max 300 characters).";
        } elseif ($form["image"] !== "" && !valid_image_src($form["image"])) {
            $formError = "Img source must be an https:// link or a file path like img/news.png.";
        } elseif ($form["game_id"] && !in_array($form["game_id"], array_map("intval", array_column($allGames, "id")), true)) {
            $formError = "The selected game does not exist.";
        }

        if ($formError === "") {
            $image  = $form["image"] === "" ? null : $form["image"];
            $gameId = $form["game_id"] ?: null;
            $gameNames = array_column($allGames, "name", "id");

            if ($id) {
                // pôvodné hodnoty pre zoznam zmien
                $stmt = mysqli_prepare($conn, "SELECT title, body, image, game_id FROM news WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $before = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

                $stmt = mysqli_prepare($conn,
                    "UPDATE news SET title = ?, body = ?, image = ?, game_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssii", $form["title"], $form["body"], $image, $gameId, $id);
                mysqli_stmt_execute($stmt);

                if ($before) {
                    $changes = [];
                    if ($before["title"] !== $form["title"]) {
                        $changes[] = 'title: "' . log_short($before["title"]) . '" → "' . log_short($form["title"]) . '"';
                    }
                    if ($before["body"] !== $form["body"]) {
                        $changes[] = "text changed";
                    }
                    if ((string)$before["image"] !== $form["image"]) {
                        $changes[] = "image changed";
                    }
                    if ((int)$before["game_id"] !== (int)$form["game_id"]) {
                        $changes[] = "game: " . ($before["game_id"] ? ($gameNames[$before["game_id"]] ?? "#" . $before["game_id"]) : "none")
                                   . " → " . ($form["game_id"] ? ($gameNames[$form["game_id"]] ?? "none") : "none");
                    }
                    if ($changes) {
                        log_change($conn, $user, "edited", "news", $form["title"], implode("; ", $changes));
                    }
                }
                flash("ok", "News updated.");
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO news (title, body, image, game_id) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssi", $form["title"], $form["body"], $image, $gameId);
                mysqli_stmt_execute($stmt);
                log_change($conn, $user, "added", "news", $form["title"],
                           $gameId ? "game: " . ($gameNames[$gameId] ?? "#" . $gameId) : "");
                flash("ok", "News added.");
            }

            // späť navrch stránky (bez kotvy, aby sa stránka nescrollovala nadol)
            header("Location: news.php");
            exit;
        }
    }

    // Načítanie novinky do formulára pri úprave (?edit=ID)
    if ($editId && !$posted) {
        $stmt = mysqli_prepare($conn, "SELECT title, body, image, game_id FROM news WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $editId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $form = ["title" => $row["title"], "body" => $row["body"], "image" => (string)$row["image"], "game_id" => (int)$row["game_id"]];
        } else {
            $editId = 0;
        }
    }

    $allNews = mysqli_fetch_all(mysqli_query($conn,
        "SELECT n.id, n.title, n.image, n.created_at, g.name AS game_name
           FROM news n
           LEFT JOIN games g ON g.id = n.game_id
          ORDER BY n.created_at DESC, n.id DESC"), MYSQLI_ASSOC);

    $flash = pull_flash();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title>Manage news – Game Launcher</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="icon.png" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php" class="active">HOME</a>
            <a href="friends.php">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">
            <?= bell_html($conn, $user) ?>
            <a href="settings.php" class="notification users-link" title="Settings">⚙</a>

            <a href="account.php" class="topbar-user" title="<?= e($user["username"]) ?>"><?= avatar_html($user["username"], $user["avatar"] ?? null, "avatar-sm") ?></a>

            <form method="POST" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="account-button">LOGOUT</button>
            </form>
        </div>

    </header>

    <main class="content">

        <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>

        <h1>Manage news</h1>

        <?php if ($flash): ?>
            <div class="flash-msg <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <form method="POST" action="news.php" class="news-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$editId ?>">

            <h2><?= $editId ? "Edit news" : "Add news" ?></h2>

            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="150"
                   value="<?= e($form["title"]) ?>" required>

            <label for="body">Text (max 300 characters)</label>
            <textarea id="body" name="body" maxlength="300"><?= e($form["body"]) ?></textarea>

            <label for="image">Img source</label>
            <input type="text" id="image" name="image" maxlength="500"
                   placeholder="https://… or img/news.png"
                   value="<?= e($form["image"]) ?>">

            <label>Preview</label>
            <div class="img-preview news-preview" id="imagePreview">Paste an image link to see a preview</div>

            <label for="game_id">Game (shown when the news is opened)</label>
            <select id="game_id" name="game_id" class="news-select">
                <option value="0">— no game —</option>
                <?php foreach ($allGames as $g): ?>
                    <option value="<?= (int)$g["id"] ?>"<?= (int)$g["id"] === (int)$form["game_id"] ? " selected" : "" ?>><?= e($g["name"]) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($formError): ?>
                <div class="news-form-error"><?= e($formError) ?></div>
            <?php endif; ?>

            <div class="news-form-actions">
                <button type="submit" class="account-button"><?= $editId ? "SAVE" : "ADD NEWS" ?></button>
                <?php if ($editId): ?>
                    <a href="news.php" class="rating-meta">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h2 class="news-list-heading" id="news-list">All news</h2>

        <div class="news-list">
            <?php foreach ($allNews as $n): ?>
                <?php $thumb = img_url($n["image"]); ?>
                <div class="news-list-item" id="news-<?= (int)$n["id"] ?>">
                    <div class="news-thumb"<?php if ($thumb): ?> style="background-image: url('<?= e($thumb) ?>');"<?php endif; ?>></div>

                    <div class="news-list-title">
                        <strong><?= e($n["title"]) ?></strong>
                        <small><?= e(date("j. n. Y H:i", strtotime($n["created_at"]))) ?><?= $n["game_name"] ? " · 🎮 " . e($n["game_name"]) : "" ?></small>
                    </div>

                    <div class="news-list-actions">
                        <a href="news.php?edit=<?= (int)$n["id"] ?>">✎ Edit</a>

                        <form method="POST" action="news.php"
                              onsubmit="return confirm('Delete this news item?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$n["id"] ?>">
                            <button type="submit" class="danger">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$allNews): ?>
                <p class="empty-state">No news yet. The home page shows grey placeholders until you add some.</p>
            <?php endif; ?>
        </div>

    </main>

    <script>
        // Náhľad obrázka: po napísaní odkazu sa po chvíli ukáže, ako bude obrázok vyzerať na karte hry.
        (function () {
            var input = document.getElementById("image");
            var box   = document.getElementById("imagePreview");
            var timer = null;

            if (!input || !box) { return; }

            function update() {
                var v = input.value.trim();

                box.style.backgroundImage = "none";
                box.classList.remove("has-image", "failed");

                if (v === "") {
                    box.textContent = "Paste an image link to see a preview";
                    return;
                }

                box.textContent = "Loading…";

                var img = new Image();
                img.onload = function () {
                    if (input.value.trim() !== v) { return; }
                    box.style.backgroundImage = "url(" + JSON.stringify(v) + ")";
                    box.classList.add("has-image");
                    box.textContent = "";
                };
                img.onerror = function () {
                    if (input.value.trim() !== v) { return; }
                    box.classList.add("failed");
                    box.textContent = "This image could not be loaded";
                };
                img.src = v;
            }

            input.addEventListener("input", function () {
                clearTimeout(timer);
                timer = setTimeout(update, 400);
            });
            update();
        })();
    </script>

</body>
</html>