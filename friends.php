<?php

    include("auth.php");
    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $me = (int)$user["id"];

    // Hľadanie používateľov podľa mena
    $q = trim($_GET["q"] ?? "");
    $found = [];
    if ($q !== "") {
        $like = "%" . addcslashes(mb_substr($q, 0, 50, "UTF-8"), "%_\\") . "%";
        $stmt = mysqli_prepare($conn,
            "SELECT id, username, avatar FROM users
              WHERE is_active = 1 AND id <> ? AND username LIKE ?
              ORDER BY username LIMIT 20");
        mysqli_stmt_bind_param($stmt, "is", $me, $like);
        mysqli_stmt_execute($stmt);
        $found = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    // Prijaté žiadosti čakajúce na moju odpoveď
    $stmt = mysqli_prepare($conn,
        "SELECT u.id, u.username, u.avatar
           FROM friendships f JOIN users u ON u.id = f.requester_id
          WHERE f.addressee_id = ? AND f.status = 'pending' AND u.is_active = 1
          ORDER BY f.created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $me);
    mysqli_stmt_execute($stmt);
    $incoming = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Moji priatelia
    $stmt = mysqli_prepare($conn,
        "SELECT u.id, u.username, u.avatar
           FROM friendships f
           JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
          WHERE f.status = 'accepted' AND (f.requester_id = ? OR f.addressee_id = ?) AND u.is_active = 1
          ORDER BY u.username");
    mysqli_stmt_bind_param($stmt, "iii", $me, $me, $me);
    mysqli_stmt_execute($stmt);
    $friends = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Žiadosti, ktoré som poslal ja
    $stmt = mysqli_prepare($conn,
        "SELECT u.id, u.username, u.avatar
           FROM friendships f JOIN users u ON u.id = f.addressee_id
          WHERE f.requester_id = ? AND f.status = 'pending' AND u.is_active = 1
          ORDER BY f.created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $me);
    mysqli_stmt_execute($stmt);
    $sent = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Malý formulár s jedným tlačidlom (akcia na friend.php)
    function friend_button($action, $label, $userId, $class = "account-button", $confirm = "") {
        return '<form method="POST" action="friend.php"'
             . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '') . '>'
             . csrf_field() . back_field()
             . '<input type="hidden" name="action" value="' . $action . '">'
             . '<input type="hidden" name="user_id" value="' . (int)$userId . '">'
             . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="icon.png">
    <title>Friends – LuLo Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <a href="track.php" class="logo-link"><img src="icon.png" alt="Logo" class="topbar-logo"></a>

        <nav>
            <a href="track.php">HOME</a>
            <a href="friends.php" class="active">FRIENDS</a>
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

        <h1>Friends</h1>

        <!-- Hľadanie používateľov -->
        <form method="GET" action="friends.php" class="friend-search">
            <input type="text" name="q" maxlength="50" value="<?= e($q) ?>"
                   placeholder="Find people by username…" autocomplete="off">
            <button type="submit" class="account-button">SEARCH</button>
        </form>

        <?php if ($q !== ""): ?>
        <section class="friend-section">
            <h2>Search results</h2>

            <?php if (!$found): ?>
                <p class="rating-meta">Nobody found for “<?= e($q) ?>”.</p>
            <?php endif; ?>

            <div class="friend-list">
                <?php foreach ($found as $p): ?>
                <div class="friend-row">
                    <a class="friend-who" href="account.php?id=<?= (int)$p["id"] ?>">
                        <?= avatar_html($p["username"], $p["avatar"], "avatar-sm") ?>
                        <strong><?= e($p["username"]) ?></strong>
                    </a>
                    <a class="account-button friend-view" href="account.php?id=<?= (int)$p["id"] ?>">VIEW PROFILE</a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>


        <?php if ($incoming): ?>
        <section class="friend-section">
            <h2>Friend requests (<?= count($incoming) ?>)</h2>

            <div class="friend-list">
                <?php foreach ($incoming as $p): ?>
                <div class="friend-row">
                    <a class="friend-who" href="account.php?id=<?= (int)$p["id"] ?>">
                        <?= avatar_html($p["username"], $p["avatar"], "avatar-sm") ?>
                        <strong><?= e($p["username"]) ?></strong>
                    </a>
                    <div class="friend-row-actions">
                        <?= friend_button("accept", "ACCEPT", $p["id"]) ?>
                        <?= friend_button("decline", "DECLINE", $p["id"], "rate-remove") ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>


        <section class="friend-section">
            <h2>My friends<?= $friends ? " (" . count($friends) . ")" : "" ?></h2>

            <?php if (!$friends): ?>
                <p class="rating-meta">You have no friends yet. Search for someone above, or click a name in a game review and press “Add friend”.</p>
            <?php endif; ?>

            <div class="friend-list">
                <?php foreach ($friends as $p): ?>
                <div class="friend-row">
                    <a class="friend-who" href="account.php?id=<?= (int)$p["id"] ?>">
                        <?= avatar_html($p["username"], $p["avatar"], "avatar-sm") ?>
                        <strong><?= e($p["username"]) ?></strong>
                    </a>
                    <div class="friend-row-actions">
                        <?= friend_button("remove", "Remove", $p["id"], "rate-remove", "Remove " . $p["username"] . " from your friends?") ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>


        <?php if ($sent): ?>
        <section class="friend-section">
            <h2>Sent requests (<?= count($sent) ?>)</h2>

            <div class="friend-list">
                <?php foreach ($sent as $p): ?>
                <div class="friend-row">
                    <a class="friend-who" href="account.php?id=<?= (int)$p["id"] ?>">
                        <?= avatar_html($p["username"], $p["avatar"], "avatar-sm") ?>
                        <strong><?= e($p["username"]) ?></strong>
                    </a>
                    <div class="friend-row-actions">
                        <span class="friend-state">PENDING</span>
                        <?= friend_button("cancel", "Cancel", $p["id"], "rate-remove") ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

</body>
</html>