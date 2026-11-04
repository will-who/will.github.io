<?php

    include("auth.php");
    $user = require_editor($conn);      // editor aj admin; stránka je len na čítanie

    $entityLabels = ["game" => "Game", "news" => "News", "user" => "User"];

    // Zmeny týkajúce sa používateľov (roly...) vidí len admin, editor ich nevidí
    if (!is_admin($user)) {
        unset($entityLabels["user"]);
    }

    $filter = $_GET["type"] ?? "";
    if (!isset($entityLabels[$filter])) {
        $filter = "";
    }

    $where = "";
    if ($filter) {
        $where = "WHERE entity = ? ";
    } elseif (!is_admin($user)) {
        $where = "WHERE entity <> 'user' ";
    }

    $sql = "SELECT username, action, entity, entity_name, details, created_at FROM changelog "
         . $where
         . "ORDER BY created_at DESC, id DESC LIMIT 500";

    $stmt = mysqli_prepare($conn, $sql);
    if ($filter) {
        mysqli_stmt_bind_param($stmt, "s", $filter);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title>Change log – Game Launcher</title>
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

            <a href="changelog.php" class="notification users-link active" title="Change log">📜</a>

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

        </div>

    </header>


    <main class="content">

        <a href="<?= e(back_url()) ?>" class="back-link">← Back to games</a>

        <div class="content-header">
            <h1>Change log</h1>

            <div class="header-actions">
                <div class="sort">
                    Show:
                    <details class="dropdown">
                        <summary><?= $filter ? e($entityLabels[$filter]) : "Everything" ?></summary>
                        <div class="dropdown-menu">
                            <a class="<?= $filter === "" ? "active" : "" ?>" href="changelog.php">Everything</a>
                            <?php foreach ($entityLabels as $k => $label): ?>
                            <a class="<?= $filter === $k ? "active" : "" ?>" href="changelog.php?type=<?= e($k) ?>"><?= e($label) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <div class="users-table-wrap">

            <table class="users-table changelog-table">

                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>TIME</th>
                        <th>BY</th>
                        <th>ACTION</th>
                        <th>WHAT</th>
                        <th>DETAILS</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($rows as $r): ?>
                    <?php $ts = strtotime($r["created_at"]); ?>
                    <tr>
                        <td class="when"><?= e(date("j. n. Y", $ts)) ?></td>
                        <td class="when"><?= e(date("H:i:s", $ts)) ?></td>
                        <td><?= e($r["username"]) ?></td>
                        <td><span class="action-badge <?= e($r["action"]) ?>"><?= e(ucfirst($r["action"])) ?></span></td>
                        <td><?= e(($entityLabels[$r["entity"]] ?? $r["entity"]) . ": " . $r["entity_name"]) ?></td>
                        <td class="details"><?= e((string)$r["details"]) ?></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>

            </table>

            <?php if (!$rows): ?>
                <p class="empty-state">No changes recorded yet.</p>
            <?php endif; ?>

        </div>

    </main>

</body>
</html>