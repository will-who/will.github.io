<?php
    include("auth.php");
    $user = current_user($conn);

    // Hry z databázy
    $games = mysqli_query($conn, "SELECT id, name, genre, image FROM games ORDER BY name");
    $totalGames = mysqli_num_rows($games);
    
?>


<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Launcher</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>


    <!-- =========================
         TOP NAVIGATION
    ========================== -->

    <header class="topbar">

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

                <div class="menu-item selected">

                    <span>
                        <?= $user ? "Your Library" : "Most Used" ?>
                    </span>

                    <small>
                        3
                    </small>

                </div>


                <?php if ($user): ?>

                <div class="menu-item">

                    <span>
                        Favorites
                    </span>

                    <small>
                        1
                    </small>

                </div>

                <?php endif; ?>


                <hr>


                <div class="menu-item">

                    <span>
                        All Our Games
                    </span>

                    <small>
                        <?= $totalGames ?>
                    </small>

                </div>

            </div>

        </aside>



        <!-- =====================
             MAIN CONTENT
        ====================== -->

        <main class="content">


            <div class="content-header">

                <h1>
                    <?= $user ? "Your Games" : "Most Used" ?>
                </h1>


                <?php if ($user): ?>

                <div class="sort">

                    Sort by:

                    <button>
                        Last Opened⌄
                    </button>

                </div>

                <?php endif; ?>

            </div>



            <!-- =====================
                 GAME CARDS
            ====================== -->

            <div class="games">


                <?php while ($game = mysqli_fetch_assoc($games)): ?>

                <?php
                    // bezpečná cesta k obrázku (zakóduje aj úvodzovky a pod.)
                    $img = $game["image"]
                        ? str_replace("%2F", "/", rawurlencode($game["image"]))
                        : "";
                ?>

                <a class="game-card" href="game.php?id=<?= (int)$game["id"] ?>">

                    <div
                        class="game-image"
                        <?php if ($img): ?>
                        style="background-image: url('<?= $img ?>');"
                        <?php endif; ?>
                    >

                        <?php if ($user): ?>
                        <span class="star empty">
                            ☆
                        </span>
                        <?php endif; ?>

                        <div class="game-title">
                            <?= e(mb_strtoupper($game["name"], "UTF-8")) ?>
                        </div>

                    </div>


                    <h3>
                        <?= e($game["name"]) ?>
                    </h3>


                    <?php if ($game["genre"]): ?>
                    <p>
                        <?= e($game["genre"]) ?>
                    </p>
                    <?php endif; ?>

                </a>

                <?php endwhile; ?>


            </div>

        </main>

    </div>



    </body>

</html>