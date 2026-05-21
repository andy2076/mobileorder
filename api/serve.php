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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'error' => 'POSTのみ対応'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$item_id = (int)($input['item_id'] ?? 0);
$action  = $input['action'] ?? 'toggle_serve'; // toggle_serve | cancel | cancel_one

if (!$item_id) {
    send_json_response(['success' => false, 'error' => '無効なリクエストです'], 400);
}

try {
    // item が自店舗の注文に属するか確認
    $stmt = $db->prepare(
        "SELECT oi.id, oi.order_id, oi.quantity, oi.cancelled_qty, oi.is_served, oi.is_cancelled, o.store_id, o.status
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE oi.id = :item_id AND o.store_id = :store_id"
    );
    $stmt->execute([':item_id' => $item_id, ':store_id' => $store_id]);
    $item = $stmt->fetch();

    if (!$item) {
        send_json_response(['success' => false, 'error' => 'アイテムが見つかりません'], 404);
    }

    $order_id = (int)$item['order_id'];
    $quantity = (int)$item['quantity'];
    $cancelled_qty = (int)$item['cancelled_qty'];
    $active_qty = $quantity - $cancelled_qty;

    if ($action === 'cancel') {
        // 全キャンセル（提供済みは不可）
        if ($item['is_served']) {
            send_json_response(['success' => false, 'error' => '提供済みの品目はキャンセルできません'], 400);
        }
        // is_cancelled をトグル
        $new_cancelled = $item['is_cancelled'] ? 0 : 1;
        if ($new_cancelled) {
            // 全キャンセル: cancelled_qty = quantity
            $stmt = $db->prepare("UPDATE order_items SET is_cancelled = 1, cancelled_qty = quantity WHERE id = :id");
        } else {
            // キャンセル解除: cancelled_qty = 0
            $stmt = $db->prepare("UPDATE order_items SET is_cancelled = 0, cancelled_qty = 0 WHERE id = :id");
        }
        $stmt->execute([':id' => $item_id]);

    } elseif ($action === 'cancel_one') {
        // 1個キャンセル（提供済みは不可）
        if ($item['is_served']) {
            send_json_response(['success' => false, 'error' => '提供済みの品目はキャンセルできません'], 400);
        }
        if ($active_qty <= 0) {
            send_json_response(['success' => false, 'error' => 'これ以上キャンセルできません'], 400);
        }
        $new_cancelled_qty = $cancelled_qty + 1;
        $new_is_cancelled = ($new_cancelled_qty >= $quantity) ? 1 : 0;
        $stmt = $db->prepare("UPDATE order_items SET cancelled_qty = :cq, is_cancelled = :ic WHERE id = :id");
        $stmt->execute([':cq' => $new_cancelled_qty, ':ic' => $new_is_cancelled, ':id' => $item_id]);

    } else {
        // toggle_serve: 提供済みトグル（キャンセル済みは操作不可）
        if ($item['is_cancelled']) {
            send_json_response(['success' => false, 'error' => 'キャンセル済みの品目です'], 400);
        }
        $new_served = $item['is_served'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE order_items SET is_served = :served WHERE id = :id");
        $stmt->execute([':served' => $new_served, ':id' => $item_id]);
    }

    // 注文の合計金額を再計算（キャンセル分を除外）
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(item_price * (quantity - cancelled_qty)), 0) as new_total
         FROM order_items WHERE order_id = :order_id AND is_cancelled = 0"
    );
    $stmt->execute([':order_id' => $order_id]);
    $partial_total = (int)$stmt->fetch()['new_total'];

    // 全キャンセル品(is_cancelled=1)は合計に含まない → partial_total が正しい合計
    $stmt = $db->prepare("UPDATE orders SET total_amount = :total WHERE id = :id AND store_id = :store_id");
    $stmt->execute([':total' => $partial_total, ':id' => $order_id, ':store_id' => $store_id]);

    // 残っている品目（キャンセルでない）が全て提供済みか確認
    $stmt = $db->prepare(
        "SELECT COUNT(*) as total, SUM(is_served) as served
         FROM order_items WHERE order_id = :order_id AND is_cancelled = 0"
    );
    $stmt->execute([':order_id' => $order_id]);
    $counts = $stmt->fetch();

    $remaining = (int)$counts['total'];
    $served = (int)$counts['served'];

    // 全品キャンセル → 注文自体をキャンセル
    if ($remaining === 0) {
        $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', total_amount = 0 WHERE id = :id AND store_id = :store_id");
        $stmt->execute([':id' => $order_id, ':store_id' => $store_id]);
        send_json_response(['success' => true, 'item_id' => $item_id, 'order_cancelled' => true]);
        return;
    }

    $all_served = ($remaining === $served);

    // 全品提供済み → completed
    if ($all_served && $item['status'] !== 'completed') {
        $stmt = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = :id AND store_id = :store_id");
        $stmt->execute([':id' => $order_id, ':store_id' => $store_id]);
    }
    // まだ残りあり → preparing
    if (!$all_served && $item['status'] === 'completed') {
        $stmt = $db->prepare("UPDATE orders SET status = 'preparing' WHERE id = :id AND store_id = :store_id");
        $stmt->execute([':id' => $order_id, ':store_id' => $store_id]);
    }

    send_json_response(['success' => true, 'item_id' => $item_id, 'all_served' => $all_served]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => '更新に失敗しました'], 500);
}
