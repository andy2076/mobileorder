<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$method = $_SERVER['REQUEST_METHOD'];

function get_or_create_category($db, $name) {
    if (empty(trim($name))) return null;
    $name = trim($name);
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = :name");
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetch();
    if ($row) return $row['id'];
    $stmt = $db->prepare("INSERT INTO categories (name, sort_order) VALUES (:name, 99)");
    $stmt->execute([':name' => $name]);
    return $db->lastInsertId();
}

if ($method === 'GET') {
    $available_only = isset($_GET['available_only']) && $_GET['available_only'] === 'true';
    try {
        $cat_stmt = $db->prepare("SELECT name FROM categories ORDER BY sort_order ASC");
        $cat_stmt->execute();
        $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

        $sql = "SELECT m.id, m.name, m.description, m.price, m.image_url, m.is_available, c.name AS category
                FROM menu_items m LEFT JOIN categories c ON m.category_id = c.id";
        if ($available_only) $sql .= " WHERE m.is_available = 1";
        $sql .= " ORDER BY c.sort_order ASC, m.sort_order ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['price'] = (int)$item['price'];
            $item['is_available'] = (bool)$item['is_available'];
            $item['id'] = (int)$item['id'];
        }
        send_json_response(['success' => true, 'items' => $items, 'categories' => $categories]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        send_json_response(['success' => false, 'error' => 'メニューの取得に失敗しました'], 500);
    }
}

if (in_array($method, ['POST','PUT','DELETE'])) {
    require_admin_login();
    $input = json_decode(file_get_contents('php://input'), true);

    if ($method === 'POST') {
        $category_id = get_or_create_category($db, $input['category_name'] ?? '');
        $stmt = $db->prepare("INSERT INTO menu_items (category_id, name, description, price, image_url, is_available, sort_order) VALUES (:cid,:name,:desc,:price,:img,:avail,:sort)");
        $stmt->execute([':cid'=>$category_id,':name'=>sanitize_input($input['name']??''),':desc'=>sanitize_input($input['description']??''),':price'=>(int)($input['price']??0),':img'=>sanitize_input($input['image_url']??''),':avail'=>(int)($input['is_available']??1),':sort'=>(int)($input['sort_order']??0)]);
        send_json_response(['success'=>true,'id'=>$db->lastInsertId()]);
    }
    if ($method === 'PUT') {
        $id = (int)($input['id']??0);
        if (!$id) send_json_response(['success'=>false,'error'=>'IDが必要です'],400);
        $category_id = get_or_create_category($db, $input['category_name'] ?? '');
        $stmt = $db->prepare("UPDATE menu_items SET category_id=:cid,name=:name,description=:desc,price=:price,image_url=:img,is_available=:avail WHERE id=:id");
        $stmt->execute([':id'=>$id,':cid'=>$category_id,':name'=>sanitize_input($input['name']??''),':desc'=>sanitize_input($input['description']??''),':price'=>(int)($input['price']??0),':img'=>sanitize_input($input['image_url']??''),':avail'=>(int)($input['is_available']??1)]);
        send_json_response(['success'=>true]);
    }
    if ($method === 'DELETE') {
        $id = (int)($input['id']??$_GET['id']??0);
        if (!$id) send_json_response(['success'=>false,'error'=>'IDが必要です'],400);
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        send_json_response(['success'=>true]);
    }
}
