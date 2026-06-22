<?php

$conn = mysqli_connect(
    "localhost:8081",
    "root",
    "Admin@123",
    "nutrivision_db"
);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

?>
