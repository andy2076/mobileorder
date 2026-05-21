<?php
// 店舗解決レイヤー - 全リクエストで店舗を特定する

function resolve_store($db) {
    $slug = $_GET['_store_slug'] ?? null;

    if (!$slug) {
        // セッションにあればそれを使う（管理画面のリダイレクト等）
        if (!empty($_SESSION['store_slug'])) {
            return [
                'id' => $_SESSION['store_id'],
                'slug' => $_SESSION['store_slug'],
                'name' => $_SESSION['store_name'],
                'is_active' => 1
            ];
        }
        http_response_code(404);
        echo '店舗が見つかりません';
        exit;
    }

    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));

    $stmt = $db->prepare("SELECT id, slug, name, is_active FROM stores WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $store = $stmt->fetch();

    if (!$store || !$store['is_active']) {
        http_response_code(404);
        echo '店舗が見つかりません';
        exit;
    }

    $_SESSION['store_id'] = (int)$store['id'];
    $_SESSION['store_slug'] = $store['slug'];
    $_SESSION['store_name'] = $store['name'];

    return $store;
}

function get_store_id() {
    return $_SESSION['store_id'] ?? null;
}

function get_store_slug() {
    return $_SESSION['store_slug'] ?? '';
}

function get_store_name() {
    return $_SESSION['store_name'] ?? 'モバイルオーダー';
}

function store_url($path = '') {
    $slug = get_store_slug();
    return "/s/{$slug}/{$path}";
}

function get_base_url() {
    return '/s/' . get_store_slug();
}
