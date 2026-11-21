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

    // filter podľa režimu (?mode=quickplay|ranked); hodnota je z whitelistu, takže je bezpečné ju dať do SQL
    $mode = (string)($_GET["mode"] ?? "");
    if (!array_key_exists($mode, STAT_MODES)) {
        $mode = "";
    }
    $modeSql = $mode !== "" ? " AND s.mode = '" . $mode . "'" : "";

    // Staty tejto hry: Ranked má všetky staty z Quick play + staty navyše
    $fieldsQuick  = $game ? stat_fields_for($game["name"], "quickplay") : [];
    $fieldsRanked = $game ? stat_fields_for($game["name"], "ranked") : [];
    $fields       = $mode === "quickplay" ? $fieldsQuick : $fieldsRanked;
    $hasKd        = isset($fields["kills"], $fields["deaths"]);

    // názvy stĺpcov pochádzajú z konštanty (bezpečné do SQL)
    $sums    = "";
    $columns = "";
    foreach (array_keys(STAT_COLUMNS) as $k) {
        $sums    .= ", COALESCE(SUM(s.`" . $k . "`), 0) AS `" . $k . "`";
        $columns .= ", s.`" . $k . "`";
    }
    $counts = ", COALESCE(SUM(s.mode = 'quickplay'), 0) AS nq, COALESCE(SUM(s.mode = 'ranked'), 0) AS nr";

    $summary = ["n" => 0, "nq" => 0, "nr" => 0];
    $players = [];
    $entries = [];
    $mine    = [];

    if ($game) {
        $gid = (int)$game["id"];

        // súčty zo schválených záznamov
        $stmt = mysqli_prepare($conn,
            "SELECT COUNT(*) AS n" . $counts . $sums . " FROM game_stats s WHERE s.game_id = ? AND s.status = 'approved'" . $modeSql);
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // hráči (súčty za všetky schválené záznamy)
        $stmt = mysqli_prepare($conn,
            "SELECT u.id, u.username, u.avatar, COUNT(*) AS n" . $sums . ",
                    SUBSTRING_INDEX(GROUP_CONCAT(s.nickname ORDER BY s.created_at DESC, s.id DESC SEPARATOR '\\n'), '\\n', 1) AS nick
               FROM game_stats s
               JOIN users u ON u.id = s.user_id
              WHERE s.game_id = ? AND s.status = 'approved'" . $modeSql . "
              GROUP BY u.id, u.username, u.avatar
              ORDER BY `kills` DESC, n DESC
              LIMIT 50");
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $players = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // jednotlivé schválené záznamy
        $stmt = mysqli_prepare($conn,
            "SELECT s.id, u.id AS uid, u.username, s.nickname, s.character_name, s.mode, s.screenshot, s.rank_screenshot, s.created_at" . $columns . "
               FROM game_stats s
               JOIN users u ON u.id = s.user_id
              WHERE s.game_id = ? AND s.status = 'approved'" . $modeSql . "
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT 100");
        mysqli_stmt_bind_param($stmt, "i", $gid);
        mysqli_stmt_execute($stmt);
        $entries = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // moje záznamy (aj čakajúce a zamietnuté, všetky režimy)
        if ($user) {
            $uid = (int)$user["id"];
            $stmt = mysqli_prepare($conn,
                "SELECT s.id, s.status, s.nickname, s.character_name, s.mode, s.screenshot, s.rank_screenshot, s.created_at" . $columns . "
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

    // Na koľkých záznamoch sa daný stat vôbec zadával (pre priemery)
    function stat_applicable($summary, $key, $fieldsQuick, $fieldsRanked) {
        return (isset($fieldsQuick[$key]) ? (int)$summary["nq"] : 0) + (isset($fieldsRanked[$key]) ? (int)$summary["nr"] : 0);
    }

    // Bunky so statmi jedného riadku: "—" tam, kde sa stat v danom režime nezadával
    function stat_cells($row, $fields, $gameName) {
        $rowFields = stat_fields_for($gameName, $row["mode"]);
        $html = "";
        foreach ($fields as $k => $label) {
            $html .= "<td>" . (isset($rowFields[$k]) ? e(stat_value($k, $row[$k])) : "—") . "</td>";
        }
        return $html;
    }

    // Odkazy na screenshoty (staty + voliteľne postup v ranku)
    function stat_proof($row) {
        $html = "";
        $a = stat_src($row["screenshot"]);
        $b = stat_src($row["rank_screenshot"] ?? "");
        if ($a !== "") { $html .= '<a href="' . e($a) . '" target="_blank" rel="noopener">Stats ↗</a>'; }
        if ($b !== "") { $html .= ' · <a href="' . e($b) . '" target="_blank" rel="noopener">Rank ↗</a>'; }
        return $html;
    }

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

            <div class="mode-tabs">
                <a class="<?= $mode === "" ? "active" : "" ?>" href="game-stats.php?id=<?= (int)$game["id"] ?>">All</a>
                <?php foreach (STAT_MODES as $mk => $mLabel): ?>
                <a class="<?= $mode === $mk ? "active" : "" ?>" href="game-stats.php?id=<?= (int)$game["id"] ?>&amp;mode=<?= e($mk) ?>"><?= e($mLabel) ?></a>
                <?php endforeach; ?>
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
                <?php foreach ($fields as $key => $label): ?>
                <?php $cnt = stat_applicable($summary, $key, $fieldsQuick, $fieldsRanked); ?>
                <div>
                    <strong><?= e(stat_value($key, $summary[$key])) ?></strong>
                    <span>TOTAL <?= e(strtoupper($label)) ?></span>
                    <?php if ($cnt): ?><small>avg <?= e(number_format($summary[$key] / $cnt, 1)) ?> per entry</small><?php endif; ?>
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
                                <th>NICK</th>
                                <th>ENTRIES</th>
                                <?php foreach ($fields as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <?php if ($hasKd): ?><th>K/D</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($players as $p): ?>
                            <tr>
                                <td><a class="review-user" href="account.php?id=<?= (int)$p["id"] ?>"><?= e($p["username"]) ?></a></td>
                                <td><?= $p["nick"] !== "" ? e($p["nick"]) : "—" ?></td>
                                <td><?= (int)$p["n"] ?></td>
                                <?php foreach ($fields as $k => $label): ?><td><?= e(stat_value($k, $p[$k])) ?></td><?php endforeach; ?>
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
                                <th>NICK</th>
                                <th>CHARACTER</th>
                                <th>MODE</th>
                                <?php foreach ($fields as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <?php if ($hasKd): ?><th>K/D</th><?php endif; ?>
                                <th>PROOF</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $r): ?>
                            <tr>
                                <td><?= e(date("j. n. Y", strtotime($r["created_at"]))) ?></td>
                                <td><a class="review-user" href="account.php?id=<?= (int)$r["uid"] ?>"><?= e($r["username"]) ?></a></td>
                                <td><?= $r["nickname"] !== "" ? e($r["nickname"]) : "—" ?></td>
                                <td><?= $r["character_name"] !== "" ? e($r["character_name"]) : "—" ?></td>
                                <td><?= e(STAT_MODES[$r["mode"]] ?? $r["mode"]) ?></td>
                                <?= stat_cells($r, $fields, $game["name"]) ?>
                                <?php if ($hasKd): ?><td><?= e(stat_kd((int)$r["kills"], (int)$r["deaths"])) ?></td><?php endif; ?>
                                <td><?= stat_proof($r) ?></td>
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
                                <th>NICK</th>
                                <th>CHARACTER</th>
                                <th>MODE</th>
                                <?php foreach ($fieldsRanked as $label): ?><th><?= e(strtoupper($label)) ?></th><?php endforeach; ?>
                                <th>PROOF</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mine as $r): ?>
                            <tr>
                                <td><?= e(date("j. n. Y H:i", strtotime($r["created_at"]))) ?></td>
                                <td><span class="status-badge <?= e($r["status"]) ?>"><?= e(ucfirst($r["status"])) ?></span></td>
                                <td><?= $r["nickname"] !== "" ? e($r["nickname"]) : "—" ?></td>
                                <td><?= $r["character_name"] !== "" ? e($r["character_name"]) : "—" ?></td>
                                <td><?= e(STAT_MODES[$r["mode"]] ?? $r["mode"]) ?></td>
                                <?= stat_cells($r, $fieldsRanked, $game["name"]) ?>
                                <td><?= stat_proof($r) ?></td>
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