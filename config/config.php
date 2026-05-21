<?php
session_start();

require_once __DIR__ . '/store.php';

define('MAX_UPLOAD_SIZE', 30 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm']);
define('ALLOWED_MEDIA_TYPES', array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES));
define('MAX_VIDEO_DURATION', 10); // seconds

define('ORDER_STATUS', [
    'pending' => '注文受付',
    'preparing' => '調理中',
    'ready' => '完成',
    'completed' => '受渡完了',
    'cancelled' => 'キャンセル',
    'paid' => '会計済み'
]);

function get_upload_path() {
    $slug = get_store_slug();
    $path = __DIR__ . '/../images/stores/' . $slug . '/menu/';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

function get_upload_url() {
    return '/s/' . get_store_slug() . '/images/menu/';
}

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
        header('Location: ' . get_base_url() . '/admin/login.php');
        exit;
    }
    // 管理者が現在の店舗に所属しているか確認
    $store_id = get_store_id();
    if ($store_id && isset($_SESSION['admin_store_id']) && $_SESSION['admin_store_id'] !== $store_id) {
        session_destroy();
        session_start();
        header('Location: ' . get_base_url() . '/admin/login.php');
        exit;
    }
}

set_error_handler(function($severity, $message, $file, $line) {
    error_log("Error [$severity]: $message in $file on line $line");
});

date_default_timezone_set('Asia/Tokyo');
?>
