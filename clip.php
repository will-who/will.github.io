<?php

    include("auth.php");
    $user = current_user($conn);

    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

    // Načítanie klipu (len typ "clip")
    $clip = null;
    if ($id) {
        $stmt = mysqli_prepare($conn,
            "SELECT m.id, m.game_id, m.user_id, m.video_id, m.caption, m.created_at,
                    u.username, u.avatar, g.name AS game_name
               FROM game_media m
               JOIN users u ON u.id = m.user_id
               JOIN games g ON g.id = m.game_id
              WHERE m.id = ? AND m.type = 'clip' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $clip = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$clip) {
        http_response_code(404);
    }

    $selfUrl = $clip ? "clip.php?id=" . (int)$clip["id"] : "clip.php";

    /* ---------- Akcie (POST): lajk, komentár, zmazanie komentára ---------- */

    if ($clip && $_SERVER["REQUEST_METHOD"] === "POST") {

        $action = $_POST["action"] ?? "";
        $mid    = (int)$clip["id"];
        $owner  = (int)$clip["user_id"];
        $anchor = "#likes";

        if (!$user) {
            header("Location: login.php");
            exit;
        }
        $uid = (int)$user["id"];

        if (!csrf_valid()) {
            flash("error", "Invalid form, please try again.");
        }
        elseif ($action === "like") {

            // ak už lajk existuje, zmaže sa, inak sa pridá
            $stmt = mysqli_prepare($conn, "DELETE FROM media_likes WHERE media_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $mid, $uid);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                drop_clip_notification($conn, $owner, $uid, "clip_like", $mid);
            } else {
                try {
                    $stmt = mysqli_prepare($conn, "INSERT INTO media_likes (media_id, user_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, "ii", $mid, $uid);
                    mysqli_stmt_execute($stmt);
                    notify_clip($conn, $owner, $uid, "clip_like", $mid);
                }
                catch (mysqli_sql_exception $ex) {
                    // dvojklik – lajk už existuje
                }
            }
        }
        elseif ($action === "comment") {

            $anchor  = "#comments";
            $comment = trim((string)($_POST["comment"] ?? ""));

            if ($comment === "") {
                flash("error", "Write a comment first.");
            }
            elseif (mb_strlen($comment, "UTF-8") > 500) {
                flash("error", "Comment is too long (max 500 characters).");
            }
            else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO media_comments (media_id, user_id, comment) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iis", $mid, $uid, $comment);
                mysqli_stmt_execute($stmt);
                notify_clip($conn, $owner, $uid, "clip_comment", $mid);
                flash("ok", "Comment added.");
            }
        }
        elseif ($action === "delete_comment") {

            $anchor = "#comments";
            $cid    = filter_input(INPUT_POST, "comment_id", FILTER_VALIDATE_INT);

            // zmazať môže autor komentára alebo editor / admin
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM media_comments WHERE id = ? AND media_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ii", $cid, $mid);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$row) {
                flash("error", "Comment not found.");
            }
            elseif ((int)$row["user_id"] !== $uid && !is_editor($user)) {
                flash("error", "You cannot delete this comment.");
            }
            else {
                $stmt = mysqli_prepare($conn, "DELETE FROM media_comments WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $cid);
                mysqli_stmt_execute($stmt);
                flash("ok", "Comment deleted.");
            }
        }

        header("Location: " . $selfUrl . $anchor);
        exit;
    }

    /* ---------- Lajky a komentáre ---------- */

    $likeCount = 0;
    $liked     = false;
    $comments  = [];

    if ($clip) {
        $mid = (int)$clip["id"];

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM media_likes WHERE media_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $mid);
        mysqli_stmt_execute($stmt);
        $likeCount = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

        if ($user) {
            $uid = (int)$user["id"];
            $stmt = mysqli_prepare($conn, "SELECT 1 FROM media_likes WHERE media_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $mid, $uid);
            mysqli_stmt_execute($stmt);
            $liked = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        }

        $stmt = mysqli_prepare($conn,
            "SELECT c.id, c.user_id, c.comment, c.created_at, u.username, u.avatar
               FROM media_comments c
               JOIN users u ON u.id = c.user_id
              WHERE c.media_id = ?
              ORDER BY c.created_at ASC, c.id ASC
              LIMIT 200");
        mysqli_stmt_bind_param($stmt, "i", $mid);
        mysqli_stmt_execute($stmt);
        $comments = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    $flash = pull_flash();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title><?= $clip ? e($clip["caption"] !== "" ? $clip["caption"] : "Clip") . " – " . e($clip["game_name"]) : "Clip not found" ?> – LuLo Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="logo.jpg" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php" class="active">HOME</a>
            <a href="friends.php">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <?php if ($user): ?>

                <?php if (is_editor($user)): ?>
                <a href="changelog.php" class="notification users-link" title="Change log">📜</a>
                <?php endif; ?>

                <?php if (is_admin($user)): ?>
                <a href="users.php" class="notification users-link" title="All users">👥</a>
                <?php endif; ?>

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

        <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <?php if ($clip): ?>

            <article class="clip-page">

                <div class="clip-player">
                    <iframe src="https://www.youtube-nocookie.com/embed/<?= e(rawurlencode($clip["video_id"])) ?>"
                            title="<?= e($clip["caption"] !== "" ? $clip["caption"] : "Clip") ?>"
                            allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                </div>

                <h1><?= e($clip["caption"] !== "" ? $clip["caption"] : "Clip of " . $clip["game_name"]) ?></h1>

                <div class="clip-meta">
                    <a class="review-user" href="account.php?id=<?= (int)$clip["user_id"] ?>#clips">
                        <?= avatar_html($clip["username"], $clip["avatar"], "avatar-xs") ?>
                        <strong><?= e($clip["username"]) ?></strong>
                    </a>
                    <span>·</span>
                    <a href="game.php?id=<?= (int)$clip["game_id"] ?>#media">🎮 <?= e($clip["game_name"]) ?></a>
                    <span>·</span>
                    <span><?= e(date("j. n. Y H:i", strtotime($clip["created_at"]))) ?></span>
                </div>

                <!-- Lajk -->
                <div class="clip-actions" id="likes">

                    <?php if ($user): ?>
                    <form method="POST" action="<?= e($selfUrl) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="like">
                        <button type="submit" class="like-btn<?= $liked ? " liked" : "" ?>"
                                title="<?= $liked ? "Remove your like" : "Like this clip" ?>">
                            <?= $liked ? "♥" : "♡" ?> <?= $likeCount ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="like-btn static">♡ <?= $likeCount ?></span>
                    <span class="rating-meta"><a href="login.php">Log in</a> to like and comment.</span>
                    <?php endif; ?>

                </div>

                <!-- Komentáre -->
                <section class="clip-comments" id="comments">

                    <h2>Comments<?= $comments ? " (" . count($comments) . ")" : "" ?></h2>

                    <?php if ($user): ?>
                    <form method="POST" action="<?= e($selfUrl) ?>" class="review-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="comment">
                        <textarea name="comment" maxlength="500" required
                                  placeholder="Write a comment…"></textarea>
                        <div class="review-actions">
                            <button type="submit" class="account-button">POST COMMENT</button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php if (!$comments): ?>
                        <p class="rating-meta">No comments yet.</p>
                    <?php endif; ?>

                    <?php foreach ($comments as $cm): ?>
                    <div class="clip-comment">
                        <a href="account.php?id=<?= (int)$cm["user_id"] ?>"><?= avatar_html($cm["username"], $cm["avatar"], "avatar-sm") ?></a>
                        <div class="clip-comment-body">
                            <div class="review-head">
                                <a class="review-user" href="account.php?id=<?= (int)$cm["user_id"] ?>"><strong><?= e($cm["username"]) ?></strong></a>
                                <small><?= e(date("j. n. Y H:i", strtotime($cm["created_at"]))) ?></small>

                                <?php if ($user && ((int)$user["id"] === (int)$cm["user_id"] || is_editor($user))): ?>
                                <form method="POST" action="<?= e($selfUrl) ?>" class="review-delete-form"
                                      onsubmit="return confirm('Delete this comment?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?= (int)$cm["id"] ?>">
                                    <button type="submit" class="rate-remove review-delete">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <p><?= nl2br(e($cm["comment"])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </section>

            </article>

        <?php else: ?>

            <h1>Clip not found</h1>
            <p class="not-found">This clip does not exist.</p>

        <?php endif; ?>

    </main>

</body>
</html>