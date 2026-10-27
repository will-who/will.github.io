<?php

    include("auth.php");
    $user = current_user($conn);

    // Načítanie hry podľa ?id=
    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $game = null;

    if ($id) {
        $stmt = mysqli_prepare($conn,
            "SELECT id, name, genre, image_back, image_front, image_size, image_position, description FROM games WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $game = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$game) {
        http_response_code(404);
    }

    $imgBack  = $game ? img_url($game["image_back"])  : "";
    $imgFront = $game ? img_url($game["image_front"]) : "";

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $game ? e($game["name"]) : "Game not found" ?> – Game Launcher</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <nav>
            <a href="track.php" class="active">HOME</a>
            <a href="#">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <?php if ($user): ?>

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

        <a href="track.php" class="back-link">← Back to games</a>

        <?php if ($game): ?>

            <div class="game-detail">

                <div
                    class="game-detail-image"
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

                    <h1><?= e($game["name"]) ?></h1>

                    <?php if ($game["genre"]): ?>
                        <div class="game-detail-genre"><?= e($game["genre"]) ?></div>
                    <?php endif; ?>

                    <h2>About this game</h2>

                    <p class="game-description">
                        <?= $game["description"]
                            ? nl2br(e($game["description"]))
                            : "No description yet." ?>
                    </p>

                </div>

            </div>

        <?php else: ?>

            <h1>Game not found</h1>
            <p class="not-found">Táto hra neexistuje.</p>

        <?php endif; ?>

    </main>

</body>
</html>