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

$period = $_GET['period'] ?? 'today';
$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));

switch ($period) {
    case 'today':
        $from = $now->format('Y-m-d 00:00:00');
        $to   = $now->format('Y-m-d 23:59:59');
        break;
    case 'week':
        $from = (clone $now)->modify('-6 days')->format('Y-m-d 00:00:00');
        $to   = $now->format('Y-m-d 23:59:59');
        break;
    case 'month':
        $from = (clone $now)->modify('-29 days')->format('Y-m-d 00:00:00');
        $to   = $now->format('Y-m-d 23:59:59');
        break;
    case 'custom':
        $from = ($_GET['from'] ?? $now->format('Y-m-d')) . ' 00:00:00';
        $to   = ($_GET['to'] ?? $now->format('Y-m-d')) . ' 23:59:59';
        break;
    default:
        $from = $now->format('Y-m-d 00:00:00');
        $to   = $now->format('Y-m-d 23:59:59');
}

try {
    $paid_statuses = "('completed','paid')";

    // サマリー
    $stmt = $db->prepare("
        SELECT COUNT(*) as order_count,
               COALESCE(SUM(total_amount),0) as revenue,
               COALESCE(AVG(total_amount),0) as avg_order
        FROM orders
        WHERE store_id = :store_id
          AND status IN {$paid_statuses}
          AND created_at BETWEEN :from AND :to
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $summary = $stmt->fetch();
    $summary['order_count'] = (int)$summary['order_count'];
    $summary['revenue'] = (int)$summary['revenue'];
    $summary['avg_order'] = (int)$summary['avg_order'];

    // 合計品数
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(oi.quantity),0) as total_items
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.store_id = :store_id
          AND o.status IN {$paid_statuses}
          AND o.created_at BETWEEN :from AND :to
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $summary['total_items'] = (int)$stmt->fetch()['total_items'];

    // 日別売上
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date,
               COUNT(*) as orders,
               SUM(total_amount) as revenue
        FROM orders
        WHERE store_id = :store_id
          AND status IN {$paid_statuses}
          AND created_at BETWEEN :from AND :to
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $daily = $stmt->fetchAll();
    foreach ($daily as &$d) {
        $d['orders'] = (int)$d['orders'];
        $d['revenue'] = (int)$d['revenue'];
    }

    // 人気メニュー TOP10
    $stmt = $db->prepare("
        SELECT oi.item_name,
               SUM(oi.quantity) as total_qty,
               SUM(oi.item_price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.store_id = :store_id
          AND o.status IN {$paid_statuses}
          AND o.created_at BETWEEN :from AND :to
        GROUP BY oi.item_name
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $popular = $stmt->fetchAll();
    foreach ($popular as &$p) {
        $p['total_qty'] = (int)$p['total_qty'];
        $p['total_revenue'] = (int)$p['total_revenue'];
    }

    // 時間帯別注文数
    $stmt = $db->prepare("
        SELECT HOUR(created_at) as hour,
               COUNT(*) as orders,
               SUM(total_amount) as revenue
        FROM orders
        WHERE store_id = :store_id
          AND status IN {$paid_statuses}
          AND created_at BETWEEN :from AND :to
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $hourly = $stmt->fetchAll();
    foreach ($hourly as &$h) {
        $h['hour'] = (int)$h['hour'];
        $h['orders'] = (int)$h['orders'];
        $h['revenue'] = (int)$h['revenue'];
    }

    // 曜日別売上
    $stmt = $db->prepare("
        SELECT DAYOFWEEK(created_at) as dow,
               COUNT(*) as orders,
               COALESCE(SUM(total_amount),0) as revenue,
               COALESCE(AVG(total_amount),0) as avg_order
        FROM orders
        WHERE store_id = :store_id
          AND status IN {$paid_statuses}
          AND created_at BETWEEN :from AND :to
        GROUP BY DAYOFWEEK(created_at)
        ORDER BY dow
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $weekday_raw = $stmt->fetchAll();
    $weekday = [];
    foreach ($weekday_raw as $w) {
        $weekday[] = [
            'dow' => (int)$w['dow'],
            'orders' => (int)$w['orders'],
            'revenue' => (int)$w['revenue'],
            'avg_order' => (int)$w['avg_order']
        ];
    }

    // テーブル別売上
    $stmt = $db->prepare("
        SELECT table_number,
               COUNT(*) as orders,
               COALESCE(SUM(total_amount),0) as revenue,
               COALESCE(AVG(total_amount),0) as avg_order
        FROM orders
        WHERE store_id = :store_id
          AND status IN {$paid_statuses}
          AND created_at BETWEEN :from AND :to
        GROUP BY table_number
        ORDER BY revenue DESC
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $tables = $stmt->fetchAll();
    foreach ($tables as &$t) {
        $t['table_number'] = (int)$t['table_number'];
        $t['orders'] = (int)$t['orders'];
        $t['revenue'] = (int)$t['revenue'];
        $t['avg_order'] = (int)$t['avg_order'];
    }

    // キャンセル数
    $stmt = $db->prepare("
        SELECT COUNT(*) as cancelled
        FROM orders
        WHERE store_id = :store_id
          AND status = 'cancelled'
          AND created_at BETWEEN :from AND :to
    ");
    $stmt->execute([':store_id' => $store_id, ':from' => $from, ':to' => $to]);
    $summary['cancelled'] = (int)$stmt->fetch()['cancelled'];

    send_json_response([
        'success' => true,
        'period'  => ['from' => $from, 'to' => $to],
        'summary' => $summary,
        'daily'   => $daily,
        'popular' => $popular,
        'hourly'  => $hourly,
        'weekday' => $weekday,
        'tables'  => $tables
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_json_response(['success' => false, 'error' => 'データ取得に失敗しました'], 500);
}
