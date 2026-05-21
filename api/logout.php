<?php
require_once '../config/config.php';

$slug = $_SESSION['store_slug'] ?? '';
session_destroy();
if ($slug) {
    header('Location: /s/' . $slug . '/admin/login.php');
} else {
    header('Location: /');
}
exit;
