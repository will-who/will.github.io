<?php

    include("auth.php");

    // Odhlásenie len cez POST s CSRF tokenom (aby to nikto nevyvolal cudzím odkazom)
    if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_valid()) {
        destroy_login_session($conn);
        $_SESSION = [];
        session_destroy();
    }

    header("Location: track.php");
    exit;

?>