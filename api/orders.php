<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$store = resolve_store($db);
$store_id = (int)$store['id'];

require_admin_login();

try {
    $status_filter = $_GET['status'] ?? 'active';

    if ($status_filter === 'active') {
        $sql = "SELECT o.id, o.table_number, o.status, o.total_amount, o.special_requests, o.created_at, o.updated_at
                FROM orders o
                WHERE o.store_id = :store_id AND o.status NOT IN ('paid','cancelled')
                ORDER BY o.created_at ASC";
    } else {
        $sql = "SELECT o.id, o.table_number, o.status, o.total_amount, o.special_requests, o.created_at, o.updated_at
                FROM orders o
                WHERE o.store_id = :store_id
                ORDER BY o.created_at DESC LIMIT 50";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([':store_id' => $store_id]);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $order['id'] = (int)$order['id'];
        $order['table_number'] = (int)$order['table_number'];
        $order['total_amount'] = (int)$order['total_amount'];

        $item_stmt = $db->prepare("SELECT id, item_name, item_price, quantity, cancelled_qty, is_served, is_cancelled FROM order_items WHERE order_id = :order_id");
        $item_stmt->execute([':order_id' => $order['id']]);
        $order['items'] = $item_stmt->fetchAll();

        foreach ($order['items'] as &$item) {
            $item['id']            = (int)$item['id'];
            $item['item_price']    = (int)$item['item_price'];
            $item['quantity']      = (int)$item['quantity'];
            $item['cancelled_qty'] = (int)$item['cancelled_qty'];
            $item['is_served']     = (int)$item['is_served'];
            $item['is_cancelled']  = (int)$item['is_cancelled'];
        }
    }

    send_json_response(['success' => true, 'orders' => $orders]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => '注文の取得に失敗しました'], 500);
}
