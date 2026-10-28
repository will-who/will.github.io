<?php

    include("auth.php");
    $user = require_admin($conn);

    /* ---------- Zmena roly (POST) ---------- */

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $targetId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
        $action   = $_POST["action"] ?? "";

        if (!csrf_valid()) {
            flash("error", "Invalid form, please reload the page.");
        }
        elseif (!$targetId || $targetId === (int)$user["id"]) {
            flash("error", "You cannot change your own role.");
        }
        elseif ($action === "promote") {
            // bežného používateľa urobí editorom (môže spravovať hry)
            $stmt = mysqli_prepare($conn,
                "UPDATE users SET role = 'editor' WHERE id = ? AND role = 'user'");
            mysqli_stmt_bind_param($stmt, "i", $targetId);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                flash("ok", "User is now an editor.");
            } else {
                flash("error", "User not found or is already an editor.");
            }
        }
        elseif ($action === "demote") {
            // editorovi vezme práva (z editora bude bežný používateľ)
            $stmt = mysqli_prepare($conn,
                "UPDATE users SET role = 'user' WHERE id = ? AND role = 'editor'");
            mysqli_stmt_bind_param($stmt, "i", $targetId);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                flash("ok", "Editor rights removed.");
            } else {
                flash("error", "User not found or is not an editor.");
            }
        }

        header("Location: users.php");
        exit;
    }

    $flash = pull_flash();

    $users = mysqli_fetch_all(mysqli_query($conn,
        "SELECT id, username, email, role, is_active, created_at, last_login
           FROM users
          ORDER BY FIELD(role, 'admin', 'editor', 'user'), username"), MYSQLI_ASSOC);

    $roleLabels = ["admin" => "Admin", "editor" => "Editor", "user" => "User"];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All users – Game Launcher</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>

    <header class="topbar">

        <nav>
            <a href="track.php">HOME</a>
            <a href="#">FRIENDS</a>
            <a href="#">GLOBAL CHATROOM</a>
        </nav>

        <div class="topbar-right">

            <a href="users.php" class="notification users-link active" title="All users">👥</a>

            <span class="username"><?= e($user["username"]) ?></span>

            <form method="POST" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="account-button">LOGOUT</button>
            </form>

        </div>

    </header>


    <main class="content">

        <a href="track.php" class="back-link">← Back to games</a>

        <div class="content-header">
            <h1>All users</h1>
        </div>

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash[0] === "ok" ? "ok" : "error" ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <div class="users-table-wrap">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>USER</th>
                        <th>EMAIL</th>
                        <th>ROLE</th>
                        <th>JOINED</th>
                        <th>LAST LOGIN</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $u): ?>

                    <?php $isMe = (int)$u["id"] === (int)$user["id"]; ?>

                    <tr class="<?= $isMe ? "me" : "" ?>">

                        <td>
                            <?= e($u["username"]) ?>
                            <?php if ($isMe): ?><span class="you-label">(you)</span><?php endif; ?>
                            <?php if (!$u["is_active"]): ?><span class="you-label">(disabled)</span><?php endif; ?>
                        </td>

                        <td><?= e($u["email"]) ?></td>

                        <td>
                            <span class="role-badge <?= e($u["role"]) ?>"><?= e($roleLabels[$u["role"]] ?? $u["role"]) ?></span>
                        </td>

                        <td><?= e(date("j. n. Y", strtotime($u["created_at"]))) ?></td>

                        <td><?= $u["last_login"] ? e(date("j. n. Y H:i", strtotime($u["last_login"]))) : "—" ?></td>

                        <td class="users-action">

                            <?php if (!$isMe && $u["role"] === "user"): ?>

                            <form method="POST" action="users.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u["id"] ?>">
                                <input type="hidden" name="action" value="promote">
                                <button type="submit" class="account-button">MAKE EDITOR</button>
                            </form>

                            <?php elseif (!$isMe && $u["role"] === "editor"): ?>

                            <form method="POST" action="users.php"
                                  data-confirm="<?= e("Remove editor rights from " . $u["username"] . "?") ?>"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u["id"] ?>">
                                <input type="hidden" name="action" value="demote">
                                <button type="submit" class="account-button danger">REMOVE EDITOR</button>
                            </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </main>

</body>
</html>