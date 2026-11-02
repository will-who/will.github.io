<?php

    include("auth.php");
    $user = current_user($conn);

    // Načítanie novinky podľa ?id= (aj s hrou, ak je priradená)
    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $n  = null;

    if ($id) {
        $stmt = mysqli_prepare($conn,
            "SELECT n.id, n.title, n.body, n.image, n.created_at, n.game_id,
                    g.name AS game_name, g.image_back, g.image_size, g.image_position
               FROM news n
               LEFT JOIN games g ON g.id = n.game_id
              WHERE n.id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $n = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$n) {
        http_response_code(404);
    }

    $img     = $n ? img_url($n["image"]) : "";
    $gameImg = ($n && $n["game_id"]) ? img_url($n["image_back"]) : "";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title><?= $n ? e($n["title"]) : "News not found" ?> – LuLo Tracker</title>
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

        <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>

        <?php if ($n): ?>

            <article class="news-article">

                <?php if ($img): ?>
                <div class="news-article-image" style="background-image: url('<?= e($img) ?>');"></div>
                <?php endif; ?>

                <div class="content-header">
                    <h1><?= e($n["title"]) ?></h1>

                    <?php if (is_editor($user)): ?>
                    <a href="news.php?edit=<?= (int)$n["id"] ?>" class="account-button">✎ EDIT NEWS</a>
                    <?php endif; ?>
                </div>

                <div class="news-article-meta">
                    <?= e(date("j. n. Y H:i", strtotime($n["created_at"]))) ?>
                </div>

                <?php if (trim($n["body"]) !== ""): ?>
                <p class="news-article-text"><?= nl2br(e($n["body"])) ?></p>
                <?php endif; ?>

                <?php if ($n["game_id"]): ?>
                <a class="news-article-game" href="game.php?id=<?= (int)$n["game_id"] ?>">
                    <div class="news-article-game-img"<?php if ($gameImg): ?> style="background-image: url('<?= e($gameImg) ?>'); background-size: <?= e(css_val($n["image_size"], "cover")) ?>; background-position: <?= e(css_val($n["image_position"], "center")) ?>;"<?php endif; ?>></div>
                    <div class="news-article-game-info">
                        <small>RELATED GAME</small>
                        <strong><?= e($n["game_name"]) ?></strong>
                        <span>View game page →</span>
                    </div>
                </a>
                <?php endif; ?>

            </article>

        <?php else: ?>

            <h1>News not found</h1>
            <p class="not-found">This news does not exist.</p>

        <?php endif; ?>

    </main>

</body>
</html>