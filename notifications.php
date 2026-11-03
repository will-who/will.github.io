<?php

    include("auth.php");

    $user = current_user($conn);
    $isAjax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";

    if (!$user) {
        if ($isAjax) { http_response_code(401); exit; }
        header("Location: login.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_valid() && ($_POST["action"] ?? "") === "read_all") {
        $uid = (int)$user["id"];
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
    }

    if ($isAjax) {
        header("Content-Type: application/json");
        echo json_encode(["ok" => true]);
        exit;
    }

    header("Location: " . safe_back($_POST["back"] ?? "", "track.php"));
    exit;

?>