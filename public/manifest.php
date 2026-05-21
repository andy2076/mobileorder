<?php
require_once '../config/config.php';
require_once '../config/database.php';
header('Content-Type: application/manifest+json');
$database = new Database();
$db = $database->getConnection();
$store = resolve_store($db);
echo json_encode([
    'name' => $store['name'] . ' - モバイルオーダー',
    'short_name' => $store['name'],
    'start_url' => '/s/' . $store['slug'] . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#4CAF50',
    'icons' => [
        ['src' => '/mobileorder/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/mobileorder/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
