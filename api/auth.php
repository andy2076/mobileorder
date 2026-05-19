<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'error' => 'POSTのみ対応'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = sanitize_input($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    send_json_response(['success' => false, 'error' => 'ユーザー名とパスワードを入力してください'], 400);
}

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$stmt = $db->prepare("SELECT id, username, password FROM admins WHERE username = :username");
$stmt->execute([':username' => $username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    send_json_response(['success' => true, 'redirect' => BASE_URL . '/admin/dashboard.php']);
} else {
    send_json_response(['success' => false, 'error' => 'ユーザー名またはパスワードが違います'], 401);
}
