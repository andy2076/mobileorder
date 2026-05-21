<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$store = resolve_store($db);
$store_id = (int)$store['id'];

require_admin_login();

$input = json_decode(file_get_contents('php://input'), true);
$order_id  = (int)($input['order_id'] ?? 0);
$new_status = $input['status'] ?? '';

$valid_statuses = array_keys(ORDER_STATUS);
if (!$order_id || !in_array($new_status, $valid_statuses)) {
    send_json_response(['success' => false, 'error' => '無効なリクエストです'], 400);
}

try {
    $stmt = $db->prepare("UPDATE orders SET status = :status WHERE id = :id AND store_id = :store_id");
    $stmt->execute([':status' => $new_status, ':id' => $order_id, ':store_id' => $store_id]);

    send_json_response(['success' => true, 'order_id' => $order_id, 'status' => $new_status]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => 'ステータス更新に失敗しました'], 500);
}
