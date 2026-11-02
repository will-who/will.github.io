<?php

    include("auth.php");
    $user = current_user($conn);

    // Načítanie hry podľa ?id=
    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $game = null;

    if ($id) {
        $stmt = mysqli_prepare($conn,
            "SELECT id, name, image_back, image_front, image_size, image_position, description FROM games WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $game = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$game) {
        http_response_code(404);
    }

    $gameTags = $game ? game_tags($conn, (int)$game["id"]) : [];

    // Hodnotenia: priemer (bez adminov), počet a hodnotenie prihláseného používateľa
    $ratingAvg   = null;
    $ratingCount = 0;
    $myRating    = 0;
    $myComment   = "";
    $hasReview   = false;
    $reviews     = [];

    if ($game) {
        $stmt = mysqli_prepare($conn,
            "SELECT AVG(r.rating), COUNT(*)
               FROM ratings r
               JOIN users u ON u.id = r.user_id
              WHERE r.game_id = ? AND u.role <> 'admin'");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        $ratingCount = (int)$row[1];
        $ratingAvg   = $ratingCount ? round((float)$row[0], 1) : null;

        if ($user && !is_admin($user)) {
            $stmt = mysqli_prepare($conn, "SELECT rating, comment FROM ratings WHERE user_id = ? AND game_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $user["id"], $game["id"]);
            mysqli_stmt_execute($stmt);
            $mine = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
            $hasReview = (bool)$mine;
            $myRating  = $mine ? (int)$mine[0] : 0;
            $myComment = $mine ? (string)$mine[1] : "";
        }

        // Recenzie (hviezdičky + komentár), najnovšie hore; admin účty sa nezobrazujú
        $stmt = mysqli_prepare($conn,
            "SELECT r.user_id, u.username, r.rating, r.comment, r.updated_at
               FROM ratings r
               JOIN users u ON u.id = r.user_id
              WHERE r.game_id = ? AND u.role <> 'admin'
              ORDER BY r.updated_at DESC
              LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        $reviews = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    // Rozpísaná recenzia, ktorá sa nepodarila uložiť (chýbal komentár...), sa vráti do formulára
    $draft = $_SESSION["review_draft"] ?? null;
    unset($_SESSION["review_draft"]);
    if ($game && $draft && (int)$draft["game_id"] === (int)$game["id"]) {
        $myRating  = (int)$draft["rating"];
        $myComment = (string)$draft["comment"];
    }

    $flash = pull_flash();

    $imgBack  = $game ? img_url($game["image_back"])  : "";
    $imgFront = $game ? img_url($game["image_front"]) : "";

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title><?= $game ? e($game["name"]) : "Game not found" ?> – Game Launcher</title>
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

            <?php if ($user): ?>

                <a href="settings.php" class="notification users-link" title="Settings">⚙</a>

                <span class="username"><?= e($user["username"]) ?></span>

                <form method="POST" action="logout.php" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="account-button">LOGOUT</button>
                </form>

            <?php else: ?>

                <a href="login.php" class="account-button">LOGIN</a>

            <?php endif; ?>

        </div>

    </header>


    <main class="content">

        <a href="<?= e(back_url($game ? $game["id"] : 0)) ?>" class="back-link">← Back to games</a>

        <?php if ($flash): ?>
            <div class="flash-msg <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <?php if ($game): ?>

            <div class="game-detail">

                <div
                    class="game-detail-image"
                    <?php if ($imgBack): ?>
                    style="background-image: url('<?= e($imgBack) ?>'); background-size: <?= e(css_val($game["image_size"], "cover")) ?>; background-position: <?= e(css_val($game["image_position"], "center")) ?>;"
                    <?php endif; ?>
                >
                    <div class="rating-badge <?= $ratingCount ? "" : "none" ?>">
                        ★ <?= $ratingCount ? e(number_format($ratingAvg, 1)) : "–" ?>
                    </div>

                    <?php if ($imgFront): ?>
                    <div class="game-title">
                        <img class="game-logo" src="<?= e($imgFront) ?>" alt="<?= e($game["name"]) ?>">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="game-detail-info">

                    <h1><?= e($game["name"]) ?></h1>

                    <?php if ($gameTags): ?>
                        <div class="game-tags">
                            <?php foreach ($gameTags as $t): ?>
                                <a class="tag-chip" href="<?= e(track_url("all", "az", $t["id"])) ?>"><?= e($t["name"]) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rating-summary">
                        <?php if ($ratingCount): ?>
                            <span class="rating-big">★ <?= e(number_format($ratingAvg, 1)) ?></span>
                            <span class="rating-meta">/ 5 &middot; <?= $ratingCount ?> rating<?= $ratingCount === 1 ? "" : "s" ?></span>
                        <?php else: ?>
                            <span class="rating-meta">No ratings yet</span>
                        <?php endif; ?>
                    </div>

                    <div class="rate-box" id="rating">
                    <?php if (!$user): ?>
                        <p class="rating-meta"><a href="login.php">Log in</a> to rate this game.</p>
                    <?php elseif (is_admin($user)): ?>
                        <p class="rating-meta">Admin accounts cannot rate games.</p>
                    <?php else: ?>
                        <form method="POST" action="rate.php" class="review-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">

                            <span class="rating-meta"><?= $hasReview ? "Your review:" : "Rate this game (comment required):" ?></span>

                            <div class="star-input">
                                <?php for ($n = 5; $n >= 1; $n--): ?>
                                <input type="radio" id="star<?= $n ?>" name="rating" value="<?= $n ?>"
                                       <?= $n === $myRating ? "checked" : "" ?> required>
                                <label for="star<?= $n ?>" title="<?= $n ?> out of 5">★</label>
                                <?php endfor; ?>
                            </div>

                            <textarea name="comment" maxlength="1000" required
                                      placeholder="Write your comment…"><?= e($myComment) ?></textarea>

                            <div class="review-actions">
                                <button type="submit" class="account-button"><?= $hasReview ? "UPDATE REVIEW" : "SUBMIT REVIEW" ?></button>
                                <?php if ($hasReview): ?>
                                <button type="submit" name="action" value="remove" class="rate-remove" formnovalidate>Remove</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                    </div>

                    <h2>About this game</h2>

                    <p class="game-description">
                        <?= $game["description"]
                            ? nl2br(e($game["description"]))
                            : "No description yet." ?>
                    </p>

                    <?php if (is_editor($user)): ?>
                    <a href="edit-game.php?id=<?= (int)$game["id"] ?>"
                       class="account-button edit-game-link">✎ EDIT GAME</a>
                    <?php endif; ?>

                </div>

            </div>


            <section class="reviews" id="reviews">

                <h2>Reviews<?= $ratingCount ? " (" . $ratingCount . ")" : "" ?></h2>

                <?php if (!$reviews): ?>
                    <p class="rating-meta">No reviews yet.</p>
                <?php endif; ?>

                <?php foreach ($reviews as $rv): ?>
                <article class="review">
                    <div class="review-head">
                        <strong><?= e($rv["username"]) ?></strong>
                        <span class="review-stars"><?= str_repeat("★", (int)$rv["rating"]) . str_repeat("☆", 5 - (int)$rv["rating"]) ?></span>
                        <small><?= e(date("j. n. Y", strtotime($rv["updated_at"]))) ?></small>

                        <?php if (is_admin($user)): ?>
                        <form method="POST" action="rate.php" class="review-delete-form"
                              data-confirm="<?= e("Delete the review by " . $rv["username"] . "?") ?>"
                              onsubmit="return confirm(this.dataset.confirm)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">
                            <input type="hidden" name="review_user_id" value="<?= (int)$rv["user_id"] ?>">
                            <input type="hidden" name="action" value="delete_review">
                            <button type="submit" class="rate-remove review-delete">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($rv["comment"] !== ""): ?>
                    <p><?= nl2br(e($rv["comment"])) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <h1>Game not found</h1>
            <p class="not-found">Táto hra neexistuje.</p>

        <?php endif; ?>

    </main>

</body>
</html>