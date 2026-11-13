<?php

    include("auth.php");
    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $myId   = (int)$user["id"];
    $viewId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) ?: $myId;
    $avatarError = "";

    /* ---------- Profilovka (len vlastný účet) ---------- */

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $dir    = __DIR__ . "/" . AVATAR_DIR;
        $action = $_POST["action"] ?? "";

        if (!csrf_valid()) {
            flash("error", "Invalid form, please try again.");
            header("Location: account.php");
            exit;
        }

        if ($action === "avatar_remove") {

            if (avatar_src($user["avatar"]) !== "") {
                @unlink($dir . "/" . $user["avatar"]);
            }
            $stmt = mysqli_prepare($conn, "UPDATE users SET avatar = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $myId);
            mysqli_stmt_execute($stmt);

            flash("ok", "Profile picture removed.");
            header("Location: account.php");
            exit;
        }

        if ($action === "avatar_upload") {

            $f = $_FILES["avatar"] ?? null;
            $types = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_GIF => "gif", IMAGETYPE_WEBP => "webp"];

            if (!$f || $f["error"] === UPLOAD_ERR_NO_FILE) {
                $avatarError = "Choose an image first.";
            }
            elseif (in_array($f["error"], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f["size"] > 2 * 1024 * 1024) {
                $avatarError = "The image is too large (max 2 MB).";
            }
            elseif ($f["error"] !== UPLOAD_ERR_OK) {
                $avatarError = "Upload failed, try again.";
            }
            else {
                $info = @getimagesize($f["tmp_name"]);

                if (!$info || !isset($types[$info[2]])) {
                    $avatarError = "Only JPG, PNG, GIF or WEBP images are allowed.";
                }
                elseif ($info[0] > 4000 || $info[1] > 4000) {
                    $avatarError = "The image is too big (max 4000 × 4000 px).";
                }
                elseif (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    $avatarError = "Could not create the upload folder.";
                }
                else {
                    $name = $myId . "_" . bin2hex(random_bytes(8)) . "." . $types[$info[2]];

                    if (move_uploaded_file($f["tmp_name"], $dir . "/" . $name)) {
                        if (avatar_src($user["avatar"]) !== "") {
                            @unlink($dir . "/" . $user["avatar"]);
                        }
                        $stmt = mysqli_prepare($conn, "UPDATE users SET avatar = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "si", $name, $myId);
                        mysqli_stmt_execute($stmt);

                        flash("ok", "Profile picture updated.");
                        header("Location: account.php");
                        exit;
                    }
                    $avatarError = "Could not save the image.";
                }
            }
        }

        $viewId = $myId;
    }

    $isMe = $viewId === $myId;

    /* ---------- Načítanie profilu ---------- */

    $stmt = mysqli_prepare($conn,
        "SELECT id, username, role, avatar, created_at FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $viewId);
    mysqli_stmt_execute($stmt);
    $profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$profile) {
        http_response_code(404);
    }

    // Priateľstvo medzi mnou a zobrazeným používateľom
    $fr = null;
    if ($profile && !$isMe) {
        $stmt = mysqli_prepare($conn,
            "SELECT requester_id, status FROM friendships
              WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?) LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iiii", $myId, $viewId, $viewId, $myId);
        mysqli_stmt_execute($stmt);
        $fr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    $reviewCount = 0;
    $reviewAvg   = null;
    $reviews     = [];
    $likeCount   = 0;
    $clips       = [];

    if ($profile) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*), AVG(rating) FROM ratings WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $viewId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        $reviewCount = (int)$row[0];
        $reviewAvg   = $reviewCount ? round((float)$row[1], 1) : null;

        $stmt = mysqli_prepare($conn,
            "SELECT r.game_id, g.name, r.rating, r.comment, r.updated_at
               FROM ratings r
               JOIN games g ON g.id = r.game_id
              WHERE r.user_id = ?
              ORDER BY r.updated_at DESC
              LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $viewId);
        mysqli_stmt_execute($stmt);
        $reviews = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // Klipy používateľa (s počtom lajkov a komentárov) a celkový počet lajkov
        $likeCount = clip_likes_received($conn, $viewId);

        $stmt = mysqli_prepare($conn,
            "SELECT m.id, m.game_id, g.name AS game_name, m.video_id, m.caption,
                    (SELECT COUNT(*) FROM media_likes l WHERE l.media_id = m.id) AS likes,
                    (SELECT COUNT(*) FROM media_comments cm WHERE cm.media_id = m.id) AS comments
               FROM game_media m
               JOIN games g ON g.id = m.game_id
              WHERE m.user_id = ? AND m.type = 'clip'
              ORDER BY m.id DESC
              LIMIT 60");
        mysqli_stmt_bind_param($stmt, "i", $viewId);
        mysqli_stmt_execute($stmt);
        $clips = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    $roleLabels = ["admin" => "Admin", "editor" => "Editor", "user" => "User"];
    $flash = pull_flash();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title><?= $profile ? e($profile["username"]) : "User not found" ?> – LuLo Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="icon.png" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php">HOME</a>
            <a href="friends.php">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <?php if (is_editor($user)): ?>
            <a href="changelog.php" class="notification users-link" title="Change log">📜</a>
            <?php endif; ?>

            <?php if (is_admin($user)): ?>
            <a href="users.php" class="notification users-link" title="All users">👥</a>
            <?= stats_icon_html($conn) ?>
            <?php endif; ?>

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

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <?php if ($profile): ?>

            <div class="profile-card">

                <?= avatar_html($profile["username"], $profile["avatar"], "avatar-lg") ?>

                <div class="profile-info">

                    <div class="profile-name-row">

                        <h1>
                            <?= e($profile["username"]) ?>
                            <span class="role-badge <?= e($profile["role"]) ?>"><?= e($roleLabels[$profile["role"]] ?? $profile["role"]) ?></span>
                        </h1>

                        <?php if (!$isMe): ?>
                        <div class="friend-actions">
                            <?php
                                $frForm = function ($action, $label, $class = "account-button", $confirm = "") use ($viewId) {
                                    return '<form method="POST" action="friend.php"'
                                        . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '') . '>'
                                        . csrf_field() . back_field()
                                        . '<input type="hidden" name="action" value="' . $action . '">'
                                        . '<input type="hidden" name="user_id" value="' . (int)$viewId . '">'
                                        . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
                                };

                                if (!$fr) {
                                    echo $frForm("add", "+ ADD FRIEND");
                                }
                                elseif ($fr["status"] === "pending" && (int)$fr["requester_id"] === $myId) {
                                    echo '<span class="friend-state">REQUEST SENT</span>';
                                    echo $frForm("cancel", "Cancel", "rate-remove");
                                }
                                elseif ($fr["status"] === "pending") {
                                    echo $frForm("accept", "ACCEPT");
                                    echo $frForm("decline", "DECLINE", "rate-remove");
                                }
                                else {
                                    echo '<span class="friend-state">✓ FRIENDS</span>';
                                    echo $frForm("remove", "Remove", "rate-remove", "Remove " . $profile["username"] . " from your friends?");
                                }
                            ?>
                        </div>
                        <?php endif; ?>

                    </div>

                    <div class="profile-meta">
                        Member since <?= e(date("j. n. Y", strtotime($profile["created_at"]))) ?>
                    </div>

                    <div class="profile-stats">
                        <div>
                            <strong><?= $reviewCount ?></strong>
                            <span>REVIEWS ADDED</span>
                        </div>
                        <div>
                            <strong><?= $reviewAvg !== null ? "★ " . e(number_format($reviewAvg, 1)) : "–" ?></strong>
                            <span>AVG RATING</span>
                        </div>
                        <div>
                            <strong>♥ <?= $likeCount ?></strong>
                            <span>LIKES ON CLIPS</span>
                        </div>
                    </div>

                </div>

            </div>


            <?php if ($isMe): ?>
            <div class="settings-form profile-pfp">

                <h2>Profile picture</h2>

                <form method="POST" action="account.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="avatar_upload">

                    <label for="avatar">Choose an image (JPG, PNG, GIF or WEBP, max 2 MB)</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required>

                    <div class="settings-actions profile-pfp-actions">
                        <button type="submit" class="account-button">UPLOAD</button>
                    </div>
                </form>

                <?php if (avatar_src($profile["avatar"]) !== ""): ?>
                <form method="POST" action="account.php" class="profile-pfp-remove">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="avatar_remove">
                    <button type="submit" class="rate-remove">Remove current picture</button>
                </form>
                <?php endif; ?>

                <?php if ($avatarError): ?>
                    <div class="news-form-error"><?= e($avatarError) ?></div>
                <?php endif; ?>

                <p class="rating-meta">Want to change your name, email or password? <a href="settings.php">Open settings</a>.</p>

            </div>
            <?php endif; ?>


            <section class="media profile-clips" id="clips">

                <h2>Clips<?= $clips ? " (" . count($clips) . ")" : "" ?></h2>

                <?php if (!$clips): ?>
                    <p class="rating-meta"><?= $isMe ? "You have not added any clips yet. Add one on a game page." : "No clips yet." ?></p>
                <?php endif; ?>

                <div class="gallery-grid clip-grid">
                    <?php foreach ($clips as $cl): ?>
                    <figure class="media-item">
                        <a class="clip-thumb" href="clip.php?id=<?= (int)$cl["id"] ?>"
                           style="background-image: url('https://i.ytimg.com/vi/<?= e($cl["video_id"]) ?>/hqdefault.jpg');"
                           aria-label="Open clip"><span class="clip-play">▶</span></a>
                        <figcaption>
                            <?php if ($cl["caption"] !== ""): ?>
                            <span class="media-caption"><?= e($cl["caption"]) ?></span>
                            <?php endif; ?>
                            <span class="media-by">
                                <a class="review-user" href="game.php?id=<?= (int)$cl["game_id"] ?>#media"><small>🎮 <?= e($cl["game_name"]) ?></small></a>
                                <small class="media-stats">♥ <?= (int)$cl["likes"] ?> · 💬 <?= (int)$cl["comments"] ?></small>
                            </span>
                        </figcaption>
                    </figure>
                    <?php endforeach; ?>
                </div>

            </section>


            <section class="reviews" id="reviews">

                <h2>Reviews<?= $reviewCount ? " (" . $reviewCount . ")" : "" ?></h2>

                <?php if (!$reviews): ?>
                    <p class="rating-meta"><?= $isMe ? "You have not added any reviews yet." : "No reviews yet." ?></p>
                <?php endif; ?>

                <?php foreach ($reviews as $rv): ?>
                <article class="review">
                    <div class="review-head">
                        <a class="review-user" href="game.php?id=<?= (int)$rv["game_id"] ?>#reviews"><strong><?= e($rv["name"]) ?></strong></a>
                        <span class="review-stars"><?= str_repeat("★", (int)$rv["rating"]) . str_repeat("☆", 5 - (int)$rv["rating"]) ?></span>
                        <small><?= e(date("j. n. Y", strtotime($rv["updated_at"]))) ?></small>
                    </div>
                    <?php if ($rv["comment"] !== ""): ?>
                    <p><?= nl2br(e($rv["comment"])) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <h1>User not found</h1>
            <p class="not-found">This user does not exist.</p>

        <?php endif; ?>

    </main>

</body>
</html>