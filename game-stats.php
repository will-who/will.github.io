<?php

    include("auth.php");
    $user = current_user($conn);

    $id   = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $game = null;

    if ($id) {
        $stmt = mysqli_prepare($conn, "SELECT id, name FROM games WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $game = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }

    if (!$game) {
        http_response_code(404);
    }

    $keys    = array_keys(STAT_FIELDS);           // názvy stĺpcov z konštanty (bezpečné do SQL)
    $first   = $keys[0];
    $hasKd   = array_key_exists("kills", STAT_FIELDS) && array_key_exists("deaths", STAT_FIELDS);
    $sums    = "";
    $columns = "";
    foreach ($keys as $k) {
        $sums    .= ", COALESCE(SUM(s.`" . $k . "`), 0) AS `" . $k . "`";
        $columns .= ", s.`" . $k . "`";
    }

    $summary = ["n" => 0];
    $players = [];
    $entries = [];
    $mine    = [];

    if ($game) {
        $gid = (int)$game["id"];

        // súčty a priemery zo schválených záznamov
        $stmt = mysqli_prepare($conn,
            "SELECT COUNT(*) AS n" . $sums . " FROM game_stats s WHERE s.game_id = ? AND s.status = 'approved'");
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // hráči (súčty za všetky schválené záznamy)
        $stmt = mysqli_prepare($conn,
            "SELECT u.id, u.username, u.avatar, COUNT(*) AS n" . $sums . "
               FROM game_stats s
               JOIN users u ON u.id = s.user_id
              WHERE s.game_id = ? AND s.status = 'approved'
              GROUP BY u.id, u.username, u.avatar
              ORDER BY `" . $first . "` DESC, n DESC
              LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $players = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // jednotlivé schválené záznamy
        $stmt = mysqli_prepare($conn,
            "SELECT s.id, u.id AS uid, u.username, s.screenshot, s.created_at" . $columns . "
               FROM game_stats s
               JOIN users u ON u.id = s.user_id
              WHERE s.game_id = ? AND s.status = 'approved'
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT 100");
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $entries = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // moje záznamy (aj čakajúce a zamietnuté)
        if ($user) {
            $uid = (int)$user["id"];
            $stmt = mysqli_prepare($conn,
                "SELECT s.id, s.status, s.screenshot, s.created_at" . $columns . "
                   FROM game_stats s
                  WHERE s.game_id = ? AND s.user_id = ?
                  ORDER BY s.created_at DESC, s.id DESC");
            mysqli_stmt_bind_param($stmt, "ii", $gid, $uid);
            mysqli_stmt_execute($stmt);
            $mine = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        }
    }

    $n     = (int)$summary["n"];
    $flash = $_SESSION["stats_flash"] ?? null;
    unset($_SESSION["stats_flash"]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title><?= $game ? "Stats – " . e($game["name"]) : "Game not found" ?> – LuLo Tracker</title>
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

                <?php if (is_editor($user)): ?>
                <a href="changelog.php" class="notification users-link" title="Change log">📜</a>
                <?php endif; ?>

                <?php if (is_admin($user)): ?>
                <a href="users.php" class="notification users-link" title="All users">👥</a>
                <?= stats_icon_html($conn, false) ?>
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

        <?php if ($game): ?>

            <a href="game.php?id=<?= (int)$game["id"] ?>#stats" class="back-link">← Back to <?= e($game["name"]) ?></a>

            <div class="content-header">
                <h1>Stats – <?= e($game["name"]) ?></h1>
            </div>

            <?php if ($flash): ?>
                <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>

            <!-- Súhrn -->
            <div class="stats-summary">
                <div>
                    <strong><?= $n ?></strong>
                    <span>APPROVED ENTRIES</span>
                </div>
                <?php foreach (STAT_FIELDS as $key => $label): ?>
                <div>
                    <strong><?= (int)$summary[$key] ?></strong>
                    <span>TOTAL <?= e(strtoupper($label)) ?></span>
                    <?php if ($n): ?><small>avg <?= e(number_format($summary[$key] / $n, 1)) ?> per entry</small><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if ($hasKd): ?>
                <div>
                    <strong><?= e(stat_kd((int)$summary["kills"], (int)$summary["deaths"])) ?></strong>
                    <span>K/D</span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$n): ?>
                <p class="rating-meta">No approved stats yet. Add yours on the <a href="game.php?id=<?= (int)$game["id"] ?>#stats">game page</a>.</p>
            <?php endif; ?>


            <!-- Hráči -->
            <?php if ($players): ?>
            <section class="stats-section">
                <h2>Players</h2>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>PLAYER</th>
                                <th>ENTRIES</th>
                                <?php foreach (STAT_FIELDS as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <?php if ($hasKd): ?><th>K/D</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($players as $p): ?>
                            <tr>
                                <td><a class="review-user" href="account.php?id=<?= (int)$p["id"] ?>"><?= e($p["username"]) ?></a></td>
                                <td><?= (int)$p["n"] ?></td>
                                <?php foreach ($keys as $k): ?><td><?= (int)$p[$k] ?></td><?php endforeach; ?>
                                <?php if ($hasKd): ?><td><?= e(stat_kd((int)$p["kills"], (int)$p["deaths"])) ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>


            <!-- Jednotlivé záznamy -->
            <?php if ($entries): ?>
            <section class="stats-section">
                <h2>Entries</h2>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>PLAYER</th>
                                <?php foreach (STAT_FIELDS as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <?php if ($hasKd): ?><th>K/D</th><?php endif; ?>
                                <th>PROOF</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $r): ?>
                            <?php $src = stat_src($r["screenshot"]); ?>
                            <tr>
                                <td><?= e(date("j. n. Y", strtotime($r["created_at"]))) ?></td>
                                <td><a class="review-user" href="account.php?id=<?= (int)$r["uid"] ?>"><?= e($r["username"]) ?></a></td>
                                <?php foreach ($keys as $k): ?><td><?= (int)$r[$k] ?></td><?php endforeach; ?>
                                <?php if ($hasKd): ?><td><?= e(stat_kd((int)$r["kills"], (int)$r["deaths"])) ?></td><?php endif; ?>
                                <td><?php if ($src !== ""): ?><a href="<?= e($src) ?>" target="_blank" rel="noopener">Screenshot ↗</a><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>


            <!-- Moje odoslané staty -->
            <?php if ($user && !is_admin($user)): ?>
            <section class="stats-section" id="mine">
                <h2>Your submissions</h2>

                <?php if (!$mine): ?>
                    <p class="rating-meta">You have not sent any stats for this game yet.</p>
                <?php else: ?>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>DATE</th>
                                <th>STATUS</th>
                                <?php foreach (STAT_FIELDS as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <th>PROOF</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mine as $r): ?>
                            <?php $src = stat_src($r["screenshot"]); ?>
                            <tr>
                                <td><?= e(date("j. n. Y H:i", strtotime($r["created_at"]))) ?></td>
                                <td><span class="status-badge <?= e($r["status"]) ?>"><?= e(ucfirst($r["status"])) ?></span></td>
                                <?php foreach ($keys as $k): ?><td><?= (int)$r[$k] ?></td><?php endforeach; ?>
                                <td><?php if ($src !== ""): ?><a href="<?= e($src) ?>" target="_blank" rel="noopener">Screenshot ↗</a><?php endif; ?></td>
                                <td class="users-action">
                                    <?php if ($r["status"] === "pending"): ?>
                                    <form method="POST" action="stats.php" onsubmit="return confirm('Cancel this submission?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="game_id" value="<?= (int)$game["id"] ?>">
                                        <input type="hidden" name="stat_id" value="<?= (int)$r["id"] ?>">
                                        <button type="submit" class="rate-remove">Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

        <?php else: ?>

            <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>
            <h1>Game not found</h1>
            <p class="not-found">This game does not exist.</p>

        <?php endif; ?>

    </main>

</body>
</html>