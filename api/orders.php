<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
require_admin_login();

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

try {
    // アクティブな注文（完了・キャンセル以外）
    $status_filter = $_GET['status'] ?? 'active';

    if ($status_filter === 'active') {
        $sql = "SELECT o.id, o.table_number, o.status, o.total_amount, o.special_requests, o.created_at, o.updated_at
                FROM orders o
                WHERE o.status NOT IN ('completed','cancelled')
                ORDER BY o.created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    } else {
        $sql = "SELECT o.id, o.table_number, o.status, o.total_amount, o.special_requests, o.created_at, o.updated_at
                FROM orders o
                ORDER BY o.created_at DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }

    $orders = $stmt->fetchAll();

    // 各注文の明細を取得
    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['table_number'] = (int)$order['table_number'];
        $order['total_amount'] = (int)$order['total_amount'];

        $item_stmt = $db->prepare("SELECT item_name, item_price, quantity FROM order_items WHERE order_id = :order_id");
        $item_stmt->execute([':order_id' => $order['id']]);
        $order['items'] = $item_stmt->fetchAll();

        foreach ($order['items'] as &$item) {
            $item['item_price'] = (int)$item['item_price'];
            $item['quantity']   = (int)$item['quantity'];
        }
    }

    send_json_response(['success' => true, 'orders' => $orders]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => '注文の取得に失敗しました'], 500);
}
