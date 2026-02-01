<?php
// app/controllers/get_user.php
// Return current logged-in user's full_name from session (JSON). Moved from public/get_user.php

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['full_name'])) {
    $name = $_SESSION['full_name'];
    session_write_close();
    echo json_encode(['status' => 'success', 'name' => $name]);
} else {
    session_write_close();
    echo json_encode(['status' => 'error', 'message' => 'No user in session']);
}
