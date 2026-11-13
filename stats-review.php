<?php

    include("auth.php");
    $user = require_admin($conn);

    $keys    = array_keys(STAT_FIELDS);
    $columns = "";
    foreach ($keys as $k) {
        $columns .= ", s.`" . $k . "`";
    }

    /* ---------- Schválenie / zamietnutie (POST) ---------- */

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $sid    = filter_input(INPUT_POST, "stat_id", FILTER_VALIDATE_INT);
        $action = $_POST["action"] ?? "";

        if (!csrf_valid()) {
            flash("error", "Invalid form, please reload the page.");
        }
        elseif (!$sid || !in_array($action, ["approve", "reject"], true)) {
            flash("error", "Invalid request.");
        }
        else {
            $stmt = mysqli_prepare($conn,
                "SELECT s.id, s.game_id, g.name AS game_name, u.username" . $columns . "
                   FROM game_stats s
                   JOIN games g ON g.id = s.game_id
                   JOIN users u ON u.id = s.user_id
                  WHERE s.id = ? AND s.status = 'pending' LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $sid);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$row) {
                flash("error", "This submission was already reviewed or no longer exists.");
            } else {
                $status = $action === "approve" ? "approved" : "rejected";
                $adminId = (int)$user["id"];

                $stmt = mysqli_prepare($conn,
                    "UPDATE game_stats SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                      WHERE id = ? AND status = 'pending'");
                mysqli_stmt_bind_param($stmt, "sii", $status, $adminId, $sid);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    log_change($conn, $user, $status, "game", $row["game_name"],
                               "stats by " . $row["username"] . ": " . stat_line($row));
                    flash("ok", "Stats " . $status . ".");
                }
            }
        }

        header("Location: stats-review.php");
        exit;
    }

    $flash = pull_flash();

    $pending = mysqli_fetch_all(mysqli_query($conn,
        "SELECT s.id, s.game_id, s.screenshot, s.created_at, g.name AS game_name, u.id AS uid, u.username" . $columns . "
           FROM game_stats s
           JOIN games g ON g.id = s.game_id
           JOIN users u ON u.id = s.user_id
          WHERE s.status = 'pending'
          ORDER BY s.created_at ASC, s.id ASC
          LIMIT 200"), MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title>Stats review – LuLo Tracker</title>
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
                <?= stats_icon_html($conn, true) ?>
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

        <div class="content-header">
            <h1>Stats review<?= $pending ? " (" . count($pending) . ")" : "" ?></h1>
        </div>

        <p class="rating-meta">Check the screenshot, then approve or reject. Approved stats show up on the game's stats page.</p>

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <?php if (!$pending): ?>
            <p class="empty-state">Nothing is waiting for approval.</p>
        <?php else: ?>

        <div class="users-table-wrap">
            <table class="users-table">

                <thead>
                    <tr>
                        <th>SENT</th>
                        <th>PLAYER</th>
                        <th>GAME</th>
                        <?php foreach (STAT_FIELDS as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                        <th>SCREENSHOT</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($pending as $r): ?>
                    <?php $src = stat_src($r["screenshot"]); ?>
                    <tr>
                        <td><?= e(date("j. n. Y H:i", strtotime($r["created_at"]))) ?></td>
                        <td><a class="review-user" href="account.php?id=<?= (int)$r["uid"] ?>"><?= e($r["username"]) ?></a></td>
                        <td><a class="review-user" href="game.php?id=<?= (int)$r["game_id"] ?>#stats"><?= e($r["game_name"]) ?></a></td>
                        <?php foreach ($keys as $k): ?><td><?= (int)$r[$k] ?></td><?php endforeach; ?>
                        <td>
                            <?php if ($src !== ""): ?>
                            <a href="<?= e($src) ?>" target="_blank" rel="noopener" title="Open full size">
                                <img class="shot-thumb" src="<?= e($src) ?>" alt="Screenshot">
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="users-action stats-actions">
                            <form method="POST" action="stats-review.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="stat_id" value="<?= (int)$r["id"] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="account-button">APPROVE</button>
                            </form>
                            <form method="POST" action="stats-review.php"
                                  onsubmit="return confirm('Reject these stats?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="stat_id" value="<?= (int)$r["id"] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="account-button danger">REJECT</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>

        <?php endif; ?>

    </main>

</body>
</html>