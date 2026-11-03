<?php

    include("auth.php");

    $user = current_user($conn);

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    $me     = (int)$user["id"];
    $target = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";
    $back   = safe_back($_POST["back"] ?? "", $target ? "account.php?id=" . $target : "track.php");

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !$target || $target === $me || !csrf_valid()) {
        header("Location: " . $back);
        exit;
    }

    // cieľový používateľ musí existovať a byť aktívny
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $target);
    mysqli_stmt_execute($stmt);
    if (!mysqli_fetch_row(mysqli_stmt_get_result($stmt))) {
        header("Location: " . $back);
        exit;
    }

    // existujúce priateľstvo / žiadosť v ktoromkoľvek smere
    $stmt = mysqli_prepare($conn,
        "SELECT id, requester_id, addressee_id, status FROM friendships
          WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?) LIMIT 1");
    mysqli_stmt_bind_param($stmt, "iiii", $me, $target, $target, $me);
    mysqli_stmt_execute($stmt);
    $fr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    function notify($conn, $to, $from, $type) {
        $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, from_user_id, type) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $to, $from, $type);
        mysqli_stmt_execute($stmt);
    }

    function drop_notifications($conn, $to, $from, $type) {
        $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE user_id = ? AND from_user_id = ? AND type = ?");
        mysqli_stmt_bind_param($stmt, "iis", $to, $from, $type);
        mysqli_stmt_execute($stmt);
    }

    // prijatie žiadnosti (použité pri "accept" aj keď si obaja pošlete žiadosť naraz)
    function accept_friend($conn, $frId, $me, $target) {
        $stmt = mysqli_prepare($conn, "UPDATE friendships SET status = 'accepted' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $frId);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($conn,
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND from_user_id = ? AND type = 'friend_request'");
        mysqli_stmt_bind_param($stmt, "ii", $me, $target);
        mysqli_stmt_execute($stmt);

        drop_notifications($conn, $target, $me, "friend_accepted");
        notify($conn, $target, $me, "friend_accepted");
    }

    try {
        if ($action === "add") {

            if (!$fr) {
                $stmt = mysqli_prepare($conn, "INSERT INTO friendships (requester_id, addressee_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ii", $me, $target);
                mysqli_stmt_execute($stmt);

                drop_notifications($conn, $target, $me, "friend_request");
                notify($conn, $target, $me, "friend_request");
            }
            elseif ($fr["status"] === "pending" && (int)$fr["requester_id"] === $target) {
                // druhá strana už žiadosť poslala – rovno sa prijme
                accept_friend($conn, (int)$fr["id"], $me, $target);
            }
        }
        elseif ($action === "cancel") {

            if ($fr && $fr["status"] === "pending" && (int)$fr["requester_id"] === $me) {
                $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $fr["id"]);
                mysqli_stmt_execute($stmt);
                drop_notifications($conn, $target, $me, "friend_request");
            }
        }
        elseif ($action === "accept") {

            if ($fr && $fr["status"] === "pending" && (int)$fr["addressee_id"] === $me) {
                accept_friend($conn, (int)$fr["id"], $me, $target);
            }
        }
        elseif ($action === "decline") {

            if ($fr && $fr["status"] === "pending" && (int)$fr["addressee_id"] === $me) {
                $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $fr["id"]);
                mysqli_stmt_execute($stmt);
                drop_notifications($conn, $me, $target, "friend_request");
            }
        }
        elseif ($action === "remove") {

            if ($fr && $fr["status"] === "accepted") {
                $stmt = mysqli_prepare($conn, "DELETE FROM friendships WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $fr["id"]);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    catch (mysqli_sql_exception $ex) {
        // napr. dvojklik – nič sa nestane
        error_log("[tracker friend] " . $ex->getMessage());
    }

    header("Location: " . $back);
    exit;

?>