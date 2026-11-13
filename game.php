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
    $gallery     = [];
    $shots       = [];
    $clips       = [];

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
            "SELECT r.user_id, u.username, u.avatar, r.rating, r.comment, r.updated_at
               FROM ratings r
               JOIN users u ON u.id = r.user_id
              WHERE r.game_id = ? AND u.role <> 'admin'
              ORDER BY r.updated_at DESC
              LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        $reviews = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // Galéria obrázkov hry
        $stmt = mysqli_prepare($conn, "SELECT id, image FROM game_images WHERE game_id = ? ORDER BY id");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        $gallery = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // Screenshoty a klipy od používateľov
        $stmt = mysqli_prepare($conn,
            "SELECT m.id, m.user_id, m.type, m.file, m.video_id, m.caption, u.username, u.avatar,
                    (SELECT COUNT(*) FROM media_likes l WHERE l.media_id = m.id) AS likes,
                    (SELECT COUNT(*) FROM media_comments cm WHERE cm.media_id = m.id) AS comments
               FROM game_media m
               JOIN users u ON u.id = m.user_id
              WHERE m.game_id = ?
              ORDER BY m.id DESC");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $m) {
            if ($m["type"] === "clip") { $clips[] = $m; } else { $shots[] = $m; }
        }
    }

    // Rozpísaná recenzia, ktorá sa nepodarila uložiť (chýbal komentár...), sa vráti do formulára
    $draft = $_SESSION["review_draft"] ?? null;
    unset($_SESSION["review_draft"]);
    if ($game && $draft && (int)$draft["game_id"] === (int)$game["id"]) {
        $myRating  = (int)$draft["rating"];
        $myComment = (string)$draft["comment"];
    }

    $flash = pull_flash();

    $mediaFlash = $_SESSION["media_flash"] ?? null;
    unset($_SESSION["media_flash"]);

    // Staty: schválené záznamy, moje čakajúce a (pre admina) všetky čakajúce
    $statsFlash = $_SESSION["stats_flash"] ?? null;
    unset($_SESSION["stats_flash"]);
    $approvedStats   = 0;
    $myPendingStats  = 0;
    $pendingStatsAll = 0;

    if ($game) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM game_stats WHERE game_id = ? AND status = 'approved'");
        mysqli_stmt_bind_param($stmt, "i", $game["id"]);
        mysqli_stmt_execute($stmt);
        $approvedStats = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

        if ($user && !is_admin($user)) {
            $stmt = mysqli_prepare($conn,
                "SELECT COUNT(*) FROM game_stats WHERE game_id = ? AND user_id = ? AND status = 'pending'");
            mysqli_stmt_bind_param($stmt, "ii", $game["id"], $user["id"]);
            mysqli_stmt_execute($stmt);
            $myPendingStats = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
        }
        if (is_admin($user)) {
            $pendingStatsAll = pending_stats_count($conn);
        }
    }

    $imgBack  = $game ? img_url($game["image_back"])  : "";
    $imgFront = $game ? img_url($game["image_front"]) : "";

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title><?= $game ? e($game["name"]) : "Game not found" ?> – Game Launcher</title>
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

            <?php if ($user): ?>

                <?= bell_html($conn, $user) ?>
            <a href="settings.php" class="notification users-link" title="Settings">⚙</a>

                <a href="account.php" class="topbar-user" title="<?= e($user["username"]) ?>"><?= avatar_html($user["username"], $user["avatar"] ?? null, "avatar-sm") ?></a>

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
                                <a class="tag-chip" href="<?= e(track_url("home", "az", $t["id"])) ?>"><?= e($t["name"]) ?></a>
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


            <?php if ($gallery || is_editor($user)): ?>
            <section class="gallery" id="gallery">

                <h2>Gallery<?= $gallery ? " (" . count($gallery) . ")" : "" ?></h2>

                <?php if ($gallery): ?>
                <div class="gallery-grid">
                    <?php foreach ($gallery as $gi): ?>
                    <?php $gSrc = img_url($gi["image"]); ?>
                    <button type="button" class="gallery-thumb" data-full="<?= e($gSrc) ?>"
                            style="background-image: url('<?= e($gSrc) ?>');" aria-label="Open image"></button>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="rating-meta">No images yet. Add some in <a href="edit-game.php?id=<?= (int)$game["id"] ?>#gallery">Edit game</a>.</p>
                <?php endif; ?>

            </section>
            <?php endif; ?>


            <section class="stats-box" id="stats">

                <div class="stats-head">
                    <h2>Stats</h2>

                    <a class="account-button" href="game-stats.php?id=<?= (int)$game["id"] ?>">VIEW STATS (<?= $approvedStats ?>)</a>

                    <?php if (is_admin($user)): ?>
                    <a class="account-button" href="stats-review.php">REVIEW STATS<?= $pendingStatsAll ? " (" . $pendingStatsAll . ")" : "" ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($statsFlash): ?>
                    <div class="flash-msg <?= $statsFlash[0] === "ok" ? "ok" : "error" ?>"><?= e($statsFlash[1]) ?></div>
                <?php endif; ?>

                <?php if (!$user): ?>

                    <p class="rating-meta"><a href="login.php">Log in</a> to add your stats.</p>

                <?php elseif (is_admin($user)): ?>

                    <p class="rating-meta">Admin accounts review stats, they cannot add them.</p>

                <?php else: ?>

                    <?php if ($myPendingStats): ?>
                    <p class="rating-meta">You have <?= $myPendingStats ?> stat<?= $myPendingStats === 1 ? "" : "s" ?> waiting for admin approval.
                        <a href="game-stats.php?id=<?= (int)$game["id"] ?>#mine">See your submissions</a></p>
                    <?php endif; ?>

                    <details class="media-add">
                        <summary class="account-button">+ ADD YOUR STATS</summary>

                        <form method="POST" action="stats.php" enctype="multipart/form-data" class="stats-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">

                            <div class="stats-inputs">
                                <?php foreach (STAT_FIELDS as $key => $label): ?>
                                <label for="stat_<?= e($key) ?>"><?= e($label) ?>
                                    <input type="number" id="stat_<?= e($key) ?>" name="stat[<?= e($key) ?>]"
                                           min="0" max="99999" step="1" value="0" required>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <label class="file-label" for="stat_shot">Screenshot as proof (required, JPG, PNG, GIF or WEBP, max 5 MB)</label>
                            <input type="file" id="stat_shot" name="screenshot"
                                   accept="image/jpeg,image/png,image/gif,image/webp" required>

                            <p class="rating-meta">An admin has to approve your stats before they show up.</p>

                            <button type="submit" class="account-button">SUBMIT STATS</button>
                        </form>
                    </details>

                <?php endif; ?>

            </section>


            <section class="media" id="media">

                <h2>Community screenshots &amp; clips<?= ($shots || $clips) ? " (" . (count($shots) + count($clips)) . ")" : "" ?></h2>

                <?php if ($mediaFlash): ?>
                    <div class="flash-msg <?= $mediaFlash[0] === "ok" ? "ok" : "error" ?>"><?= e($mediaFlash[1]) ?></div>
                <?php endif; ?>

                <?php if ($user): ?>
                <details class="media-add">
                    <summary class="account-button">+ ADD SCREENSHOT OR CLIP</summary>

                    <div class="media-forms">

                        <form method="POST" action="media.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">
                            <input type="hidden" name="action" value="add_screenshot">

                            <h3>Screenshot</h3>
                            <input type="file" name="screenshot" accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <input type="text" name="caption" maxlength="150" placeholder="Caption (optional)" autocomplete="off">
                            <small class="rating-meta">JPG, PNG, GIF or WEBP, max 5 MB</small>
                            <button type="submit" class="account-button">UPLOAD</button>
                        </form>

                        <form method="POST" action="media.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">
                            <input type="hidden" name="action" value="add_clip">

                            <h3>Clip</h3>
                            <input type="text" name="clip_url" maxlength="300" placeholder="https://www.youtube.com/watch?v=…" autocomplete="off" required>
                            <input type="text" name="caption" maxlength="150" placeholder="Caption (optional)" autocomplete="off">
                            <small class="rating-meta">Paste a YouTube link</small>
                            <button type="submit" class="account-button">ADD CLIP</button>
                        </form>

                    </div>
                </details>
                <?php else: ?>
                <p class="rating-meta"><a href="login.php">Log in</a> to add your own screenshots and clips.</p>
                <?php endif; ?>

                <?php if (!$shots && !$clips): ?>
                    <p class="rating-meta">Nothing here yet. Be the first to add something!</p>
                <?php endif; ?>

                <?php
                    // zdieľaný riadok pod položkou: autor, popis a prípadné zmazanie
                    $mediaMeta = function ($m) use ($user, $game) {
                        $h  = '<figcaption>';
                        if ($m["caption"] !== "") {
                            $h .= '<span class="media-caption">' . e($m["caption"]) . '</span>';
                        }
                        $h .= '<span class="media-by"><a class="review-user" href="account.php?id=' . (int)$m["user_id"] . '">'
                            . avatar_html($m["username"], $m["avatar"], "avatar-xs") . '<small>' . e($m["username"]) . '</small></a>';
                        if ($user && ((int)$user["id"] === (int)$m["user_id"] || is_editor($user))) {
                            $h .= '<form method="POST" action="media.php" onsubmit="return confirm(\'Remove this item?\')">'
                                . csrf_field()
                                . '<input type="hidden" name="game_id" value="' . (int)$game["id"] . '">'
                                . '<input type="hidden" name="action" value="delete">'
                                . '<input type="hidden" name="media_id" value="' . (int)$m["id"] . '">'
                                . '<button type="submit" class="rate-remove">Remove</button></form>';
                        }
                        return $h . '</span></figcaption>';
                    };
                ?>

                <?php if ($shots): ?>
                <h3 class="media-sub">Screenshots</h3>
                <div class="gallery-grid">
                    <?php foreach ($shots as $m): ?>
                    <?php $src = media_src($m["file"]); ?>
                    <?php if ($src === "") { continue; } ?>
                    <figure class="media-item">
                        <button type="button" class="gallery-thumb" data-full="<?= e($src) ?>"
                                style="background-image: url('<?= e($src) ?>');" aria-label="Open screenshot"></button>
                        <?= $mediaMeta($m) ?>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($clips): ?>
                <h3 class="media-sub">Clips</h3>
                <div class="gallery-grid clip-grid">
                    <?php foreach ($clips as $m): ?>
                    <figure class="media-item">
                        <a class="clip-thumb" href="clip.php?id=<?= (int)$m["id"] ?>"
                           style="background-image: url('https://i.ytimg.com/vi/<?= e($m["video_id"]) ?>/hqdefault.jpg');"
                           aria-label="Open clip"><span class="clip-play">▶</span></a>
                        <?= $mediaMeta($m) ?>
                        <small class="media-stats">♥ <?= (int)$m["likes"] ?> · 💬 <?= (int)$m["comments"] ?> · <a href="clip.php?id=<?= (int)$m["id"] ?>#comments">Open</a></small>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </section>


            <section class="reviews" id="reviews">

                <h2>Reviews<?= $ratingCount ? " (" . $ratingCount . ")" : "" ?></h2>

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


                <?php if (!$reviews): ?>
                    <p class="rating-meta">No reviews yet.</p>
                <?php endif; ?>

                <?php foreach ($reviews as $rv): ?>
                <article class="review">
                    <div class="review-head">
                        <a class="review-user" href="account.php?id=<?= (int)$rv["user_id"] ?>"><?= avatar_html($rv["username"], $rv["avatar"], "avatar-xs") ?><strong><?= e($rv["username"]) ?></strong></a>
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

    <div class="lightbox" id="lightbox" hidden>
        <button type="button" class="lightbox-close" aria-label="Close">×</button>
        <button type="button" class="lightbox-nav lightbox-prev" aria-label="Previous">‹</button>
        <img src="" alt="">
        <button type="button" class="lightbox-nav lightbox-next" aria-label="Next">›</button>
    </div>

    <script>
        // Galéria: klik na obrázok ho zväčší, šípky / Esc fungujú aj z klávesnice
        (function () {
            var thumbs = [].slice.call(document.querySelectorAll(".gallery-thumb"));
            var box = document.getElementById("lightbox");
            if (!box || !thumbs.length) { return; }
            var img = box.querySelector("img");
            var idx = 0;

            function show(i) {
                idx = (i + thumbs.length) % thumbs.length;
                img.src = thumbs[idx].getAttribute("data-full");
                box.hidden = false;
                document.body.style.overflow = "hidden";
            }
            function close() {
                box.hidden = true;
                img.src = "";
                document.body.style.overflow = "";
            }

            thumbs.forEach(function (t, i) {
                t.addEventListener("click", function () { show(i); });
            });
            box.addEventListener("click", function (e) {
                var c = e.target.classList;
                if (e.target === box || c.contains("lightbox-close")) { close(); }
                else if (c.contains("lightbox-prev")) { show(idx - 1); }
                else if (c.contains("lightbox-next")) { show(idx + 1); }
            });
            document.addEventListener("keydown", function (e) {
                if (box.hidden) { return; }
                if (e.key === "Escape")     { close(); }
                if (e.key === "ArrowLeft")  { show(idx - 1); }
                if (e.key === "ArrowRight") { show(idx + 1); }
            });
        })();
    </script>

</body>
</html>