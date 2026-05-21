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

$store = resolve_store($db);
$store_id = (int)$store['id'];

$stmt = $db->prepare("SELECT id, username, password, store_id FROM admins WHERE username = :username AND store_id = :store_id");
$stmt->execute([':username' => $username, ':store_id' => $store_id]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_store_id'] = (int)$admin['store_id'];
    send_json_response(['success' => true, 'redirect' => store_url('admin/dashboard.php')]);
} else {
    send_json_response(['success' => false, 'error' => 'ユーザー名またはパスワードが違います'], 401);
}
