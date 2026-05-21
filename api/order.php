<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'error' => 'POSTのみ対応'], 405);
}

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$store = resolve_store($db);
$store_id = (int)$store['id'];

$input = json_decode(file_get_contents('php://input'), true);

$table_number    = (int)($input['table_number'] ?? 0);
$items           = $input['items'] ?? [];
$special_requests = sanitize_input($input['special_requests'] ?? '');

if (!$table_number || $table_number < 1 || $table_number > 99) {
    send_json_response(['success' => false, 'error' => 'テーブル番号が無効です'], 400);
}
if (empty($items)) {
    send_json_response(['success' => false, 'error' => '注文商品がありません'], 400);
}

try {
    $db->beginTransaction();

    $total_amount = 0;
    $order_items = [];

    foreach ($items as $item) {
        $item_id  = (int)($item['id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 1);
        if (!$item_id || $quantity < 1) continue;

        $stmt = $db->prepare("SELECT id, name, price, is_available FROM menu_items WHERE id = :id AND store_id = :store_id");
        $stmt->execute([':id' => $item_id, ':store_id' => $store_id]);
        $menu_item = $stmt->fetch();

        if (!$menu_item || !$menu_item['is_available']) {
            $db->rollBack();
            $name = $menu_item['name'] ?? '不明な商品';
            send_json_response(['success' => false, 'error' => "「{$name}」は現在注文できません"], 400);
        }

        $total_amount += $menu_item['price'] * $quantity;
        $order_items[] = [
            'id'    => $item_id,
            'name'  => $menu_item['name'],
            'price' => $menu_item['price'],
            'qty'   => $quantity,
        ];
    }

    $stmt = $db->prepare("INSERT INTO orders (store_id, table_number, status, total_amount, special_requests)
                          VALUES (:store_id, :table_number, 'pending', :total_amount, :special_requests)");
    $stmt->execute([
        ':store_id'        => $store_id,
        ':table_number'    => $table_number,
        ':total_amount'    => $total_amount,
        ':special_requests'=> $special_requests,
    ]);
    $order_id = $db->lastInsertId();

    foreach ($order_items as $oi) {
        $stmt = $db->prepare("INSERT INTO order_items (order_id, menu_item_id, item_name, item_price, quantity)
                              VALUES (:order_id, :menu_item_id, :item_name, :item_price, :quantity)");
        $stmt->execute([
            ':order_id'     => $order_id,
            ':menu_item_id' => $oi['id'],
            ':item_name'    => $oi['name'],
            ':item_price'   => $oi['price'],
            ':quantity'     => $oi['qty'],
        ]);
    }

    $db->commit();

    send_json_response([
        'success'      => true,
        'order_id'     => (int)$order_id,
        'table_number' => $table_number,
        'total_amount' => $total_amount,
    ]);

} catch (PDOException $e) {
    $db->rollBack();
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => '注文の登録に失敗しました'], 500);
}
