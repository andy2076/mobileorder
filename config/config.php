<?php
session_start();

define('BASE_URL', '/mobileorder'); // XAMPP用: http://localhost/mobileorder
define('UPLOAD_PATH', __DIR__ . '/../images/');
define('UPLOAD_URL', BASE_URL . '/images/');

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

define('ORDER_STATUS', [
    'pending' => '注文受付',
    'preparing' => '調理中',
    'ready' => '完成',
    'completed' => '受渡完了',
    'cancelled' => 'キャンセル'
]);

function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function format_price($price) {
    return number_format($price) . '円';
}

function send_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

set_error_handler(function($severity, $message, $file, $line) {
    error_log("Error [$severity]: $message in $file on line $line");
});

date_default_timezone_set('Asia/Tokyo');
?>