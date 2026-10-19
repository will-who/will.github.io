<?php
    include("auth.php");
    $user = current_user($conn);
    
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
                        2
                    </small>

                </div>

                <?php endif; ?>


                <hr>


                <div class="menu-item">

                    <span>
                        All Our Games
                    </span>

                    <small>
                        36
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


                <div class="game-card">

                    <div class="game-image overwatch">

                        <?php if ($user): ?>
                        <span class="star">
                            ★
                        </span>
                        <?php endif; ?>

                        <div class="game-title">
                            OVERWATCH
                        </div>

                    </div>

                </div>

                <div class="game-card">

                    <div class="game-image deadlock">

                        <?php if ($user): ?>
                        <span class="star">
                            ★
                        </span>
                        <?php endif; ?>

                        <div class="game-title">
                            DEADLOCK
                        </div>

                    </div>

                </div>

                <div class="game-card">

                    <div class="game-image rivals">

                        <?php if ($user): ?>
                        <span class="star">
                            ☆
                        </span>
                        <?php endif; ?>

                        <div class="game-title">
                            MARVEL RIVALS
                        </div>

                    </div>

                </div>

                <div class="game-card">

                </div>
            </div>

        </main>

    </div>



    </body>

</html>