<?php
    include("auth.php");
    $user = current_user($conn);

    // Filter (all / favorites) a triedenie (az / updated)
    $sort = ($_GET["sort"] ?? "az") === "updated" ? "updated" : "az";
    $view = ($user && ($_GET["view"] ?? "all") === "favorites") ? "favorites" : "all";
    $uid  = $user ? (int)$user["id"] : 0;

    // Tag z ľavého panela (?tag=ID) – platí len ak taký tag existuje
    $allTags = all_tags($conn);
    $tag     = 0;
    $tagName = "";
    $reqTag  = filter_input(INPUT_GET, "tag", FILTER_VALIDATE_INT);
    foreach ($allTags as $t) {
        if ($reqTag && (int)$t["id"] === $reqTag) {
            $tag     = (int)$t["id"];
            $tagName = $t["name"];
        }
    }

    // Zapamätá si posledný zoznam (filter, tag, triedenie) pre "Back to games"
    $_SESSION["list_url"] = track_url($view, $sort, $tag);

    $orderBy = $sort === "updated" ? "g.updated_at DESC, g.name ASC" : "g.name ASC";
    // obľúbené hry prihláseného používateľa idú vždy na začiatok (filter a triedenie platia ďalej)
    if ($user) {
        $orderBy = "is_fav DESC, " . $orderBy;
    }

    // Hry z databázy (is_fav = 1, ak ich má prihlásený používateľ medzi obľúbenými)
    $where = [];
    if ($view === "favorites") {
        $where[] = "f.user_id IS NOT NULL";
    }
    if ($tag) {
        $where[] = "EXISTS (SELECT 1 FROM game_tags gt WHERE gt.game_id = g.id AND gt.tag_id = ?)";
    }

    $sql = "SELECT g.id, g.name, g.image_back, g.image_front, g.image_size, g.image_position,
                   (f.user_id IS NOT NULL) AS is_fav,
                   (SELECT ROUND(AVG(r.rating), 1) FROM ratings r JOIN users ru ON ru.id = r.user_id
                     WHERE r.game_id = g.id AND ru.role <> 'admin') AS avg_rating,
                   (SELECT COUNT(*) FROM ratings r2 JOIN users ru2 ON ru2.id = r2.user_id
                     WHERE r2.game_id = g.id AND ru2.role <> 'admin') AS rating_count,
                   (SELECT GROUP_CONCAT(t.name ORDER BY t.id SEPARATOR ', ')
                      FROM game_tags gt2 JOIN tags t ON t.id = gt2.tag_id
                     WHERE gt2.game_id = g.id) AS tag_names
              FROM games g
              LEFT JOIN favorites f ON f.game_id = g.id AND f.user_id = ? "
         . ($where ? "WHERE " . implode(" AND ", $where) . " " : "")
         . "ORDER BY " . $orderBy;

    $stmt = mysqli_prepare($conn, $sql);
    if ($tag) {
        mysqli_stmt_bind_param($stmt, "ii", $uid, $tag);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $uid);
    }
    mysqli_stmt_execute($stmt);
    $games = mysqli_stmt_get_result($stmt);
    $shownGames = mysqli_num_rows($games);

    // Počty pre ľavý panel
    $totalGames = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM games"))[0];
    $favCount = 0;
    if ($user) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        $favCount = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    }

    // Novinky (posledných 20, na hlavnej stránke sa posúvajú do strán)
    $news = mysqli_fetch_all(mysqli_query($conn,
        "SELECT n.id, n.title, n.body, n.image, n.created_at, n.game_id, g.name AS game_name
           FROM news n
           LEFT JOIN games g ON g.id = n.game_id
          ORDER BY n.created_at DESC, n.id DESC LIMIT 20"), MYSQLI_ASSOC);

?>


<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="fav.jpg">
    <title>LuLo Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>


    <!-- =========================
         TOP NAVIGATION
    ========================== -->

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="logo.jpg" alt="Logo" class="topbar-logo"></a>

        <nav>

            <a href="#" class="active">
                HOME
            </a>

            <a href="#">
                FRIENDS
            </a>

            <a href="#">
                GLOBAL CHATROOM
            </a>

        </nav>


        <div class="topbar-right">

            <?php if (is_editor($user)): ?>
            <a href="changelog.php" class="notification users-link" title="Change log">📜</a>
            <?php endif; ?>

            <?php if (is_admin($user)): ?>
            <a href="users.php" class="notification users-link" title="All users">👥</a>
            <?php endif; ?>

            <button class="notification">
                🕭
            </button>

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



    <!-- =========================
         GAME LIBRARY
    ========================== -->

    <div class="layout">


        <!-- =====================
             SIDEBAR
        ====================== -->

        <aside class="sidebar">

            <div class="search">

                <input
                    type="text"
                    placeholder="Search games"
                >

                <span>⌕</span>

            </div>


            <div class="menu">

                <a class="menu-item <?= ($view === "all" && !$tag) ? "selected" : "" ?>"
                   href="<?= e(track_url("all", $sort)) ?>">

                    <span>
                        All Games
                    </span>

                    <small>
                        <?= $totalGames ?>
                    </small>

                </a>


                <?php if ($user): ?>

                <a class="menu-item <?= ($view === "favorites" && !$tag) ? "selected" : "" ?>"
                   href="<?= e(track_url("favorites", $sort)) ?>">

                    <span>
                        Favorites
                    </span>

                    <small>
                        <?= $favCount ?>
                    </small>

                </a>

                <?php endif; ?>


                </div>


            <?php if ($allTags): ?>

            <div class="menu tag-menu">

                <div class="menu-title">TAGS</div>

                <?php foreach ($allTags as $t): ?>

                <a class="menu-item <?= $tag === (int)$t["id"] ? "selected" : "" ?>"
                   href="<?= e(track_url($view, $sort, $tag === (int)$t["id"] ? 0 : $t["id"])) ?>">

                    <span>
                        <?= e($t["name"]) ?>
                    </span>

                    <small>
                        <?= (int)$t["games"] ?>
                    </small>

                </a>

                <?php endforeach; ?>

            </div>

            <?php endif; ?>

        </aside>



        <!-- =====================
             MAIN CONTENT
        ====================== -->

        <main class="content">


            <?php if ($view === "all" && !$tag): ?>
            <section class="news">

                <div class="news-header">
                    <h2>What's new</h2>
                    <?php if (is_editor($user)): ?>
                    <a href="news.php" class="news-manage">✎ Manage news</a>
                    <?php endif; ?>
                </div>

                <div class="news-row">

                <?php if ($news): ?>

                    <?php foreach ($news as $n): ?>
                    <?php $nImg = img_url($n["image"]); ?>
                    <<?= $n["game_id"] ? 'a href="game.php?id=' . (int)$n["game_id"] . '"' : 'article' ?> class="news-card<?= $n["game_id"] ? " has-game" : "" ?>">
                        <div class="news-image"<?php if ($nImg): ?> style="background-image: url('<?= e($nImg) ?>');"<?php endif; ?>></div>
                        <div class="news-body">
                            <h3><?= e($n["title"]) ?></h3>
                            <p><?= e($n["body"]) ?></p>
                            <?php if ($n["game_id"]): ?>
                            <span class="news-game">🎮 <?= e($n["game_name"]) ?></span>
                            <?php endif; ?>
                            <small><?= e(date("j. n. Y H:i", strtotime($n["created_at"]))) ?></small>
                        </div>
                    </<?= $n["game_id"] ? 'a' : 'article' ?>>
                    <?php endforeach; ?>

                <?php else: ?>

                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <article class="news-card placeholder">
                        <div class="news-image"></div>
                        <div class="news-body">
                            <div class="ph-line ph-title"></div>
                            <div class="ph-line"></div>
                            <div class="ph-line ph-short"></div>
                        </div>
                    </article>
                    <?php endfor; ?>

                <?php endif; ?>

                </div>

            </section>
            <?php endif; ?>

            <div class="content-header">

                <h1>
                    <?php if ($tag): ?>
                        <?= $view === "favorites" ? "Favorites – " : "" ?><?= e($tagName) ?>
                    <?php elseif ($view === "favorites"): ?>Favorites<?php else: ?>All Games<?php endif; ?>
                </h1>


                <div class="header-actions">

                    <?php if (is_editor($user)): ?>
                    <a href="add-game.php" class="account-button add-game-button">+ ADD GAME</a>
                    <?php endif; ?>

                    <div class="sort">

                        Sort by:

                        <details class="dropdown">

                            <summary><?= $sort === "updated" ? "Last updated" : "A-Z" ?></summary>

                            <div class="dropdown-menu">

                                <a class="<?= $sort === "az" ? "active" : "" ?>"
                                   href="<?= e(track_url($view, "az", $tag)) ?>">A-Z</a>

                                <a class="<?= $sort === "updated" ? "active" : "" ?>"
                                   href="<?= e(track_url($view, "updated", $tag)) ?>">Last updated</a>

                            </div>

                        </details>

                    </div>

                </div>

            </div>



            <!-- =====================
                 GAME CARDS
            ====================== -->

            <div class="games">


                <?php while ($game = mysqli_fetch_assoc($games)): ?>

                <?php
                    $imgBack  = img_url($game["image_back"]);
                    $imgFront = img_url($game["image_front"]);
                ?>

                <div class="game-card-wrap" id="game-<?= (int)$game["id"] ?>">

                <?php if ($user && !is_admin($user)): ?>
                <form method="POST" action="favorite.php" class="star-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">
                    <input type="hidden" name="view" value="<?= e($view) ?>">
                    <input type="hidden" name="sort" value="<?= e($sort) ?>">
                    <input type="hidden" name="tag" value="<?= (int)$tag ?>">
                    <button type="submit"
                            class="star <?= $game["is_fav"] ? "" : "empty" ?>"
                            title="<?= $game["is_fav"] ? "Remove from favorites" : "Add to favorites" ?>">
                        <?= $game["is_fav"] ? "★" : "☆" ?>
                    </button>
                </form>
                <?php endif; ?>

                <a class="game-card" href="game.php?id=<?= (int)$game["id"] ?>">

                    <div
                        class="game-image"
                        <?php if ($imgBack): ?>
                        style="background-image: url('<?= e($imgBack) ?>'); background-size: <?= e(css_val($game["image_size"], "cover")) ?>; background-position: <?= e(css_val($game["image_position"], "center")) ?>;"
                        <?php endif; ?>
                    >

                        <div class="rating-badge <?= $game["rating_count"] ? "" : "none" ?>"
                             title="<?= $game["rating_count"]
                                 ? e(number_format((float)$game["avg_rating"], 1) . " average from " . $game["rating_count"] . " rating(s)")
                                 : "No ratings yet" ?>">
                            ★ <?= $game["rating_count"] ? e(number_format((float)$game["avg_rating"], 1)) : "–" ?>
                        </div>

                        <?php if ($imgFront): ?>
                        <div class="game-title">
                            <img class="game-logo" src="<?= e($imgFront) ?>" alt="<?= e($game["name"]) ?>">
                        </div>
                        <?php endif; ?>

                    </div>


                    <h3>
                        <?= e($game["name"]) ?>
                    </h3>


                    <?php if ($game["tag_names"]): ?>
                    <p>
                        <?= e($game["tag_names"]) ?>
                    </p>
                    <?php endif; ?>

                </a>

                <?php if (is_editor($user)): ?>
                <a class="edit-button" href="edit-game.php?id=<?= (int)$game["id"] ?>" title="Edit game">✎ Edit</a>
                <?php endif; ?>

                </div>

                <?php endwhile; ?>

                <?php if ($shownGames === 0): ?>
                <p class="empty-state">
                    <?= $tag
                        ? "No games with this tag."
                        : ($view === "favorites"
                            ? "You have no favorite games yet. Click the star on a game to add it."
                            : "No games yet.") ?>
                </p>
                <?php endif; ?>


            </div>

        </main>

    </div>



    </body>

</html>