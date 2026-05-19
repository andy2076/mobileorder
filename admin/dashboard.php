<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文管理ダッシュボード</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', sans-serif; }
        .header { background: #333; color: #fff; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 18px; }
        .header-right { display: flex; align-items: center; gap: 12px; font-size: 13px; }
        .badge-live { background: #4CAF50; border-radius: 4px; padding: 3px 8px; font-size: 11px; font-weight: bold; }
        .badge-new { background: #f44336; color: #fff; border-radius: 12px; padding: 2px 8px; font-size: 12px; font-weight: bold; }
        .tabs { background: #fff; border-bottom: 2px solid #eee; display: flex; padding: 0 16px; }
        .tab { padding: 14px 20px; cursor: pointer; font-size: 14px; font-weight: 600; color: #888; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #4CAF50; border-bottom-color: #4CAF50; }
        .container { padding: 16px; max-width: 1000px; margin: 0 auto; }
        .orders-grid { display: grid; gap: 12px; }
        .order-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); border-left: 5px solid #ddd; }
        .order-card.pending  { border-left-color: #FF9800; }
        .order-card.preparing{ border-left-color: #2196F3; }
        .order-card.ready    { border-left-color: #4CAF50; }
        .order-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .order-table { font-size: 20px; font-weight: 800; color: #333; }
        .order-time { font-size: 12px; color: #999; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-pending   { background: #FFF3E0; color: #E65100; }
        .status-preparing { background: #E3F2FD; color: #1565C0; }
        .status-ready     { background: #E8F5E9; color: #2E7D32; }
        .order-items { font-size: 14px; color: #555; margin: 10px 0; line-height: 1.8; }
        .order-item-row { display: flex; justify-content: space-between; }
        .order-total { font-weight: 700; font-size: 16px; color: #333; text-align: right; margin: 8px 0; }
        .special-req { background: #FFF8E1; border-radius: 6px; padding: 8px 10px; font-size: 13px; color: #795548; margin-top: 8px; }
        .action-buttons { display: flex; gap: 8px; margin-top: 12px; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; flex: 1; }
        .btn-start    { background: #2196F3; color: #fff; }
        .btn-complete { background: #4CAF50; color: #fff; }
        .btn-done     { background: #9E9E9E; color: #fff; }
        .btn-cancel   { background: #fff; color: #f44336; border: 1px solid #f44336; flex: 0; padding: 10px 14px; }
        .btn:hover { opacity: 0.85; }
        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; font-size: 16px; }
        .empty-state span { font-size: 48px; display: block; margin-bottom: 12px; }
        .refresh-bar { text-align: center; padding: 8px; font-size: 12px; color: #aaa; }
        .hidden { display: none; }
        a.logout { color: #ccc; font-size: 13px; text-decoration: none; }
        a.logout:hover { color: #fff; }
    </style>
</head>
<body>
<?php
require_once '../config/config.php';
require_admin_login();
?>
    <div class="header">
        <h1>🍽️ 注文管理</h1>
        <div class="header-right">
            <span class="badge-live">● LIVE</span>
            <span id="new-count" class="badge-new hidden">新着</span>
            <a href="../admin/menu.php">メニュー管理</a>
            <a href="../api/logout.php" class="logout">ログアウト</a>
        </div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('active')">受付中</div>
        <div class="tab" onclick="switchTab('all')">全履歴</div>
    </div>

    <div class="container">
        <div id="orders-container" class="orders-grid"></div>
        <div class="refresh-bar" id="refresh-bar">3秒ごとに自動更新</div>
    </div>

    <script>
        const STATUS_LABELS = {
            pending:   '注文受付',
            preparing: '調理中',
            ready:     '完成',
            completed: '受渡完了',
            cancelled: 'キャンセル'
        };

        let currentTab = 'active';
        let prevOrderIds = new Set();
        let audio = null;

        function formatTime(dateStr) {
            const d = new Date(dateStr);
            return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('ja-JP', { style:'currency', currency:'JPY' }).format(price);
        }

        function elapsedMin(dateStr) {
            const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
            return diff < 1 ? 'たった今' : diff + '分前';
        }

        async function loadOrders() {
            try {
                const res = await fetch(`../api/orders.php?status=${currentTab}`);
                const data = await res.json();
                if (!data.success) return;

                const orders = data.orders;
                const container = document.getElementById('orders-container');

                // 新着チェック
                const newIds = new Set(orders.map(o => o.id));
                const hasNew = orders.some(o => o.status === 'pending' && !prevOrderIds.has(o.id));
                if (hasNew && prevOrderIds.size > 0) {
                    document.getElementById('new-count').classList.remove('hidden');
                    setTimeout(() => document.getElementById('new-count').classList.add('hidden'), 5000);
                }
                prevOrderIds = newIds;

                if (orders.length === 0) {
                    container.innerHTML = `<div class="empty-state"><span>✅</span>現在受付中の注文はありません</div>`;
                    return;
                }

                container.innerHTML = orders.map(order => `
                    <div class="order-card ${order.status}" id="order-${order.id}">
                        <div class="order-top">
                            <div>
                                <span class="order-table">テーブル ${order.table_number}</span>
                                <span style="font-size:12px;color:#999;margin-left:8px;">#${order.id}</span>
                            </div>
                            <div style="text-align:right">
                                <span class="status-badge status-${order.status}">${STATUS_LABELS[order.status]}</span>
                                <div class="order-time">${formatTime(order.created_at)} (${elapsedMin(order.created_at)})</div>
                            </div>
                        </div>
                        <div class="order-items">
                            ${order.items.map(item => `
                                <div class="order-item-row">
                                    <span>${item.item_name} × ${item.quantity}</span>
                                    <span>${formatPrice(item.item_price * item.quantity)}</span>
                                </div>
                            `).join('')}
                        </div>
                        <div class="order-total">${formatPrice(order.total_amount)}</div>
                        ${order.special_requests ? `<div class="special-req">📝 ${order.special_requests}</div>` : ''}
                        <div class="action-buttons">
                            ${order.status === 'pending' ? `
                                <button class="btn btn-start" onclick="updateStatus(${order.id},'preparing')">🔥 調理開始</button>
                                <button class="btn btn-cancel" onclick="updateStatus(${order.id},'cancelled')">✕</button>
                            ` : ''}
                            ${order.status === 'preparing' ? `
                                <button class="btn btn-complete" onclick="updateStatus(${order.id},'ready')">✅ 調理完了</button>
                            ` : ''}
                            ${order.status === 'ready' ? `
                                <button class="btn btn-done" onclick="updateStatus(${order.id},'completed')">🙆 受渡完了</button>
                            ` : ''}
                        </div>
                    </div>
                `).join('');

            } catch (e) {
                console.error(e);
            }
        }

        async function updateStatus(orderId, status) {
            try {
                const res = await fetch('../api/status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, status })
                });
                const data = await res.json();
                if (data.success) loadOrders();
            } catch (e) { console.error(e); }
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab').forEach((t, i) => {
                t.classList.toggle('active', (i === 0 && tab === 'active') || (i === 1 && tab === 'all'));
            });
            loadOrders();
        }

        loadOrders();
        setInterval(loadOrders, 3000);
    </script>
</body>
</html>
