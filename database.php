<?php

    $db_server = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "tracker";

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name);
        mysqli_set_charset($conn, "utf8mb4");
    }
    catch (mysqli_sql_exception $e) {
        http_response_code(500);
        die("Nepodarilo sa pripojiť k databáze.");
    }

?>