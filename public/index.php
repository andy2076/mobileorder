<?php
require_once '../config/config.php';
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$store = resolve_store($db);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($store['name']) ?> - モバイルオーダー</title>
    <link rel="stylesheet" href="/mobileorder/css/style.css">
    <link rel="manifest" href="<?= store_url('manifest.php') ?>">
    <meta name="theme-color" content="#4CAF50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/mobileorder/images/icon-192.png">
</head>
<body>
    <header class="header">
        <div class="container header-inner">
            <h1 id="shop-name"><?= htmlspecialchars($store["name"]) ?></h1><div class="header-categories" id="header-categories"></div>
        </div>
    </header>

    <div class="container">
        <div id="message-area"></div>
    </div>

    <div class="container">
        <div class="table-selection" id="table-selection">
            <h2>テーブル番号を入力</h2>
            <input type="number" id="table-number" class="table-input" placeholder="1" min="1" max="99">
            <br>
            <button class="btn btn-primary btn-full" onclick="setTable()">注文を始める</button>
        </div>
    </div>

    <div class="container hidden" id="menu-area">
        <div id="category-buttons" style="display:none">
                <button class="category-btn active" data-category="">すべて</button>
        </div>
        <div class="menu-grid" id="menu-grid">
            <div class="loading">
                <div class="spinner"></div>
                <p>メニューを読み込んでいます...</p>
            </div>
        </div>
    </div>

    <button class="cart-button hidden" id="cart-button" onclick="openCart()">
        🛒
        <span class="cart-count hidden" id="cart-count">0</span>
    </button>

    <div class="cart-modal hidden" id="cart-modal">
        <div class="cart-content">
            <div class="cart-header">
                <span class="cart-title">カート</span>
                <button class="close-cart" onclick="closeCart()">&times;</button>
            </div>
            <div id="cart-items"></div>
            <div class="cart-total">
                <span>合計: </span>
                <span class="cart-total-amount" id="cart-total">¥0</span>
            </div>
            <div class="special-requests">
                <label for="special-requests-input">備考・リクエスト</label>
                <textarea id="special-requests-input" placeholder="アレルギーやご要望があればご記入ください"></textarea>
            </div>
            <button class="btn btn-primary btn-full" id="submit-order-btn" onclick="submitOrder()">注文を確定する</button>
            <button class="btn btn-danger btn-full" style="margin-top: 8px;" onclick="clearCart()">カートを空にする</button>
        </div>
    </div>

    <div class="cart-modal hidden" id="order-complete-modal">
        <div class="cart-content" style="text-align: center;">
            <h2 style="color: #4CAF50; margin-bottom: 16px;">✓ 注文完了</h2>
            <p>ご注文ありがとうございます！</p>
            <div id="order-details"></div>
            <p style="margin-top: 16px; color: #666;">しばらくお待ちください</p>
            <button class="btn btn-primary btn-full" style="margin-top: 24px;" onclick="closeOrderComplete()">閉じる</button>
        </div>
    </div>

    <script>
        window.API_BASE = '<?= store_url("api") ?>';
        window.STORE_SLUG = '<?= $store["slug"] ?>';
    </script>
    <script src="/mobileorder/js/order.js?v=3"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/mobileorder/public/sw.js').catch(err => console.log('SW registration failed:', err));
        }
    </script>
</body>
</html>
