<?php
require_once '../config/config.php';
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$store = resolve_store($db);
require_admin_login();
$api_base = store_url('api');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文管理 - <?= htmlspecialchars($store['name']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', sans-serif; height: 100vh; overflow: hidden; }

        .header { background: #333; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 17px; font-weight: 700; }
        .header-right { display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .header-right a { color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.15); }
        .header-right a:visited { color: #fff; }
        .header-right a:hover { background: rgba(255,255,255,0.25); }
        .badge-live { background: #4CAF50; border-radius: 4px; padding: 3px 8px; font-size: 11px; font-weight: bold; }
        .btn-sound { background: rgba(255,255,255,0.15); border: none; color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; }
        .btn-sound:hover { background: rgba(255,255,255,0.25); }
        .btn-sound.on { background: #4CAF50; }
        a.logout { color: #ccc; font-size: 13px; text-decoration: none; }

        /* モバイルタブ */
        .mobile-tabs { display: none; background: #fff; border-bottom: 2px solid #eee; }
        .mobile-tabs .m-tab { flex: 1; text-align: center; padding: 12px 0; font-size: 13px; font-weight: 600; color: #888; border-bottom: 3px solid transparent; cursor: pointer; }
        .mobile-tabs .m-tab.active { color: #333; border-bottom-color: #4CAF50; }
        .m-tab .m-badge { background: #f44336; color: #fff; border-radius: 10px; padding: 1px 6px; font-size: 11px; margin-left: 2px; }

        /* カンバン 3列 */
        .kanban { display: flex; gap: 12px; padding: 12px; height: calc(100vh - 56px); }
        .kanban-column { flex: 1; background: #e8eaed; border-radius: 12px; display: flex; flex-direction: column; min-width: 0; }
        .kanban-header { padding: 14px 16px; font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .kanban-header .count { background: rgba(0,0,0,0.12); border-radius: 10px; padding: 2px 8px; font-size: 13px; font-weight: 600; }
        .col-pending .kanban-header { color: #E65100; }
        .col-preparing .kanban-header { color: #1565C0; }
        .col-completed .kanban-header { color: #6A1B9A; }
        .kanban-cards { flex: 1; overflow-y: auto; padding: 0 10px 10px; }
        .kanban-cards::-webkit-scrollbar { width: 4px; }
        .kanban-cards::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 2px; }

        /* カード共通 */
        .order-card { background: #fff; border-radius: 10px; padding: 14px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-left: 4px solid #ddd; transition: background 0.3s; }
        .order-card.pending   { border-left-color: #FF9800; }
        .order-card.preparing { border-left-color: #2196F3; }
        .order-card.warn-soft { background: #FFF8E1; }
        .order-card.warn-hard { background: #FFEBEE; border-left-color: #f44336; }
        .order-card.warn-hard .card-elapsed { color: #f44336; font-weight: 700; }
        .order-card.warn-soft .card-elapsed { color: #E65100; font-weight: 600; }

        .card-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .card-table { font-size: 20px; font-weight: 800; color: #333; }
        .card-id { font-size: 12px; color: #999; margin-left: 6px; }
        .card-time { font-size: 12px; color: #999; }
        .card-elapsed { font-size: 11px; color: #bbb; }
        .card-total { font-weight: 700; font-size: 15px; color: #333; text-align: right; margin: 6px 0; padding-top: 6px; border-top: 1px solid #f0f0f0; }
        .card-special { background: #FFF8E1; border-radius: 6px; padding: 6px 8px; font-size: 12px; color: #795548; margin: 8px 0; }
        .card-actions { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
        .btn { padding: 10px 0; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; flex: 1; }
        .btn:active { transform: scale(0.97); }
        .btn-start    { background: #2196F3; color: #fff; }
        .btn-paid     { background: #6A1B9A; color: #fff; }
        .btn:hover { opacity: 0.9; }
        .cancel-link { color: #ccc; font-size: 11px; cursor: pointer; margin-left: auto; padding: 4px 8px; }
        .cancel-link:hover { color: #f44336; }

        /* 受付カラムの品目リスト */
        .card-items { font-size: 13px; color: #555; line-height: 1.7; margin: 6px 0; }
        .card-item-row { display: flex; justify-content: space-between; }

        /* 調理中カラムの品目チェックリスト */
        .serve-item { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; cursor: pointer; user-select: none; -webkit-user-select: none; transition: opacity 0.2s; }
        .serve-item:last-child { border-bottom: none; }
        .serve-item:active { opacity: 0.6; }
        .serve-check { width: 24px; height: 24px; border: 2px solid #ddd; border-radius: 6px; margin-right: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; transition: all 0.2s; }
        .serve-item.served .serve-check { background: #4CAF50; border-color: #4CAF50; color: #fff; }
        .serve-item.served .serve-name { text-decoration: line-through; color: #bbb; }
        .serve-item.served .serve-price { color: #bbb; }
        .serve-item.cancelled { opacity: 0.4; cursor: default; }
        .serve-item.cancelled .serve-check { background: #f44336; border-color: #f44336; color: #fff; font-size: 12px; }
        .serve-item.cancelled .serve-name { text-decoration: line-through; color: #ccc; }
        .serve-item.cancelled .serve-price { color: #ccc; text-decoration: line-through; }
        .serve-name { flex: 1; font-size: 14px; font-weight: 500; }
        .serve-qty { color: #999; font-size: 13px; margin-right: 8px; }
        .serve-price { font-size: 13px; color: #666; font-weight: 600; }
        .item-cancel { color: #ddd; font-size: 11px; cursor: pointer; padding: 2px 6px; margin-left: 4px; flex-shrink: 0; }
        .item-cancel:hover { color: #f44336; }
        .serve-progress { font-size: 12px; color: #999; text-align: right; margin-top: 4px; }
        .serve-progress .done { color: #4CAF50; font-weight: 700; }

        /* 会計カード */
        .bill-card { background: #fff; border-radius: 10px; padding: 14px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-left: 4px solid #9C27B0; }
        .bill-table { font-size: 22px; font-weight: 800; color: #333; margin-bottom: 10px; }
        .bill-order { margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #eee; }
        .bill-order:last-of-type { border-bottom: none; }
        .bill-order-header { font-size: 11px; color: #aaa; margin-bottom: 2px; }
        .bill-item-row { display: flex; justify-content: space-between; font-size: 13px; color: #555; line-height: 1.6; }
        .bill-subtotal { text-align: right; font-size: 13px; color: #999; }
        .bill-grand-total { font-weight: 800; font-size: 20px; color: #333; text-align: right; margin: 10px 0; padding-top: 8px; border-top: 2px solid #333; }
        .bill-actions { margin-top: 10px; }

        /* 新着ハイライト */
        @keyframes pulse-new { 0%,100%{box-shadow:0 1px 4px rgba(0,0,0,0.08)} 50%{box-shadow:0 0 0 6px rgba(255,152,0,0.35)} }
        .card-new { animation: pulse-new 0.8s ease-in-out 4; }

        .empty-col { text-align: center; padding: 40px 16px; color: #bbb; font-size: 14px; }
        .empty-col span { font-size: 36px; display: block; margin-bottom: 8px; }

        /* 全履歴モーダル */
        .history-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .history-modal { background: #fff; border-radius: 12px; width: 90%; max-width: 700px; max-height: 80vh; display: flex; flex-direction: column; }
        .history-header { padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 700; }
        .history-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #999; }
        .history-body { overflow-y: auto; padding: 16px; flex: 1; }
        .history-card { padding: 12px; border-bottom: 1px solid #f5f5f5; }
        .history-card:last-child { border-bottom: none; }
        .h-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .h-table { font-weight: 700; }
        .h-status { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .h-status.completed,.h-status.paid { background: #E8F5E9; color: #2E7D32; }
        .h-status.cancelled { background: #FFEBEE; color: #C62828; }
        .h-status.pending { background: #FFF3E0; color: #E65100; }
        .h-status.preparing { background: #E3F2FD; color: #1565C0; }
        .h-items { font-size: 13px; color: #777; }
        .h-time { font-size: 12px; color: #aaa; }
        .hidden { display: none !important; }

        @media (max-width: 767px) {
            .kanban { flex-direction: column; padding: 0; height: calc(100vh - 56px - 48px); }
            .kanban-column { border-radius: 0; display: none; flex: none; height: 100%; }
            .kanban-column.active-col { display: flex; }
            .kanban-header { display: none; }
            .kanban-cards { padding: 12px; }
            .mobile-tabs { display: flex; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($store['name']) ?></h1>
        <div class="header-right">
            <span class="badge-live">● LIVE</span>
            <button class="btn-sound" id="btn-sound" onclick="toggleSound()">🔇 通知音OFF</button>
            <a href="#" onclick="showHistory();return false;">全履歴</a>
            <a href="<?= store_url('admin/analytics.php') ?>">売上分析</a>
            <a href="<?= store_url('admin/menu.php') ?>">メニュー管理</a>
            <a href="<?= store_url('qr-generator.php') ?>">QR生成</a>
            <a href="<?= store_url('api/logout.php') ?>" class="logout">ログアウト</a>
        </div>
    </div>

    <div class="mobile-tabs">
        <div class="m-tab active" data-status="pending" onclick="switchMobileTab('pending')">受付<span class="m-badge" id="m-cnt-pending">0</span></div>
        <div class="m-tab" data-status="preparing" onclick="switchMobileTab('preparing')">調理中<span class="m-badge" id="m-cnt-preparing">0</span></div>
        <div class="m-tab" data-status="completed" onclick="switchMobileTab('completed')">会計<span class="m-badge" id="m-cnt-completed">0</span></div>
    </div>

    <div class="kanban">
        <div class="kanban-column col-pending active-col" id="col-pending">
            <div class="kanban-header">受付 <span class="count" id="cnt-pending">0</span></div>
            <div class="kanban-cards" id="cards-pending"></div>
        </div>
        <div class="kanban-column col-preparing" id="col-preparing">
            <div class="kanban-header">調理中 <span class="count" id="cnt-preparing">0</span></div>
            <div class="kanban-cards" id="cards-preparing"></div>
        </div>
        <div class="kanban-column col-completed" id="col-completed">
            <div class="kanban-header">会計 <span class="count" id="cnt-completed">0</span></div>
            <div class="kanban-cards" id="cards-completed"></div>
        </div>
    </div>

    <div class="history-overlay hidden" id="history-overlay" onclick="if(event.target===this)closeHistory()">
        <div class="history-modal">
            <div class="history-header">全履歴（直近50件）<button class="history-close" onclick="closeHistory()">&times;</button></div>
            <div class="history-body" id="history-body"></div>
        </div>
    </div>

    <script>
        const STATUS_LABELS = { pending:'注文受付', preparing:'調理中', ready:'完成', completed:'受渡完了', cancelled:'キャンセル', paid:'会計済み' };
        const API_BASE = '<?= $api_base ?>';
        let prevOrderIds = new Set();
        let mobileTab = 'pending';

        // === 通知音 ===
        let soundEnabled = false, audioCtx = null;
        function toggleSound() {
            soundEnabled = !soundEnabled;
            const btn = document.getElementById('btn-sound');
            if (soundEnabled) {
                btn.textContent = '🔔 通知音ON'; btn.classList.add('on');
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                playNotifySound();
            } else { btn.textContent = '🔇 通知音OFF'; btn.classList.remove('on'); }
        }
        function playNotifySound() {
            if (!soundEnabled || !audioCtx) return;
            [0, 0.15].forEach((delay, i) => {
                const osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'sine'; osc.frequency.value = i === 0 ? 880 : 1100;
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime + delay);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + delay + 0.3);
                osc.start(audioCtx.currentTime + delay); osc.stop(audioCtx.currentTime + delay + 0.3);
            });
        }

        // === ユーティリティ ===
        function formatTime(d) { const t=new Date(d); return t.getHours().toString().padStart(2,'0')+':'+t.getMinutes().toString().padStart(2,'0'); }
        function formatPrice(p) { return new Intl.NumberFormat('ja-JP',{style:'currency',currency:'JPY'}).format(p); }
        function elapsedMin(d) { return Math.floor((Date.now()-new Date(d))/60000); }
        function elapsedText(d) { const m=elapsedMin(d); return m<1?'たった今':m+'分前'; }
        function warnClass(order) {
            const m = elapsedMin(order.created_at);
            if (order.status==='pending') { if(m>=10) return ' warn-hard'; if(m>=5) return ' warn-soft'; }
            if (order.status==='preparing') { if(m>=15) return ' warn-hard'; if(m>=10) return ' warn-soft'; }
            return '';
        }

        // === 品目表示ヘルパー ===
        function itemQtyLabel(i) {
            // 部分キャンセル: "ラーメン ×2（1杯キャンセル）"
            if (i.cancelled_qty > 0 && !i.is_cancelled) {
                const active = i.quantity - i.cancelled_qty;
                return `${i.item_name} ×${active}<span style="color:#f44336;font-size:11px;margin-left:4px">（${i.cancelled_qty}杯取消）</span>`;
            }
            return `${i.item_name} ×${i.quantity}`;
        }
        function itemActivePrice(i) {
            const active = i.is_cancelled ? 0 : (i.quantity - i.cancelled_qty);
            return i.item_price * active;
        }

        // === 受付カード ===
        function renderPendingCard(order, isNew) {
            return `<div class="order-card pending${warnClass(order)}${isNew?' card-new':''}" id="order-${order.id}">
                <div class="card-top">
                    <div><span class="card-table">T${order.table_number}</span><span class="card-id">#${order.id}</span></div>
                    <div style="text-align:right"><div class="card-time">${formatTime(order.created_at)}</div><div class="card-elapsed">${elapsedText(order.created_at)}</div></div>
                </div>
                <div class="card-items">${order.items.map(i=>{
                    if(i.is_cancelled) return `<div class="card-item-row" style="text-decoration:line-through;color:#ccc"><span>${i.item_name} ×${i.quantity}</span><span>${formatPrice(i.item_price*i.quantity)}</span></div>`;
                    if(i.cancelled_qty > 0) return `<div class="card-item-row"><span>${itemQtyLabel(i)}</span><span>${formatPrice(itemActivePrice(i))}</span></div>`;
                    return `<div class="card-item-row"><span>${i.item_name} ×${i.quantity}</span><span>${formatPrice(i.item_price*i.quantity)}</span></div>`;
                }).join('')}</div>
                <div class="card-total">${formatPrice(order.total_amount)}</div>
                ${order.special_requests?`<div class="card-special">${order.special_requests}</div>`:''}
                <div class="card-actions">
                    <button class="btn btn-start" onclick="updateStatus(${order.id},'preparing')">調理開始</button>
                    <span class="cancel-link" onclick="cancelOrder(${order.id})">キャンセル</span>
                </div>
            </div>`;
        }

        // === 調理中カード（品目チェックリスト） ===
        function renderPreparingCard(order) {
            const active = order.items.filter(i=>!i.is_cancelled);
            const cancelledItems = order.items.filter(i=>i.is_cancelled);
            const partialCancelCount = order.items.reduce((s,i)=>s+i.cancelled_qty, 0);
            const served = active.filter(i=>i.is_served).length;
            const total = active.length;

            const itemsHtml = order.items.map(i => {
                if (i.is_cancelled) {
                    return `<div class="serve-item cancelled">
                        <div class="serve-check">✕</div>
                        <span class="serve-name">${i.item_name}</span>
                        <span class="serve-qty">×${i.quantity}</span>
                        <span class="serve-price">${formatPrice(i.item_price*i.quantity)}</span>
                    </div>`;
                }
                const activeQty = i.quantity - i.cancelled_qty;
                const qtyDisp = i.cancelled_qty > 0
                    ? `×${activeQty}<span style="color:#f44336;font-size:10px"> (${i.cancelled_qty}取消)</span>`
                    : `×${i.quantity}`;
                const cancelBtn = !i.is_served
                    ? (i.quantity > 1 && activeQty > 1
                        ? `<span class="item-cancel" onclick="event.stopPropagation();cancelOneItem(${i.id})" title="1個キャンセル">−1</span><span class="item-cancel" onclick="event.stopPropagation();cancelItem(${i.id})" title="全キャンセル">✕</span>`
                        : `<span class="item-cancel" onclick="event.stopPropagation();cancelItem(${i.id})" title="キャンセル">✕</span>`)
                    : '';
                return `<div class="serve-item${i.is_served?' served':''}" onclick="toggleServe(${i.id})">
                    <div class="serve-check">${i.is_served?'✓':''}</div>
                    <span class="serve-name">${i.item_name}</span>
                    <span class="serve-qty">${qtyDisp}</span>
                    <span class="serve-price">${formatPrice(i.item_price*activeQty)}</span>
                    ${cancelBtn}
                </div>`;
            }).join('');

            let cancelNote = '';
            if (cancelledItems.length > 0 || partialCancelCount > 0) {
                const totalCancelled = cancelledItems.length + (partialCancelCount - cancelledItems.reduce((s,i)=>s+i.cancelled_qty,0));
                cancelNote = totalCancelled > 0 ? `（${partialCancelCount}個取消）` : '';
            }
            const progressText = `<span class="${served===total?'done':''}">${served}/${total}</span> 提供済み${cancelNote}`;

            return `<div class="order-card preparing${warnClass(order)}" id="order-${order.id}">
                <div class="card-top">
                    <div><span class="card-table">T${order.table_number}</span><span class="card-id">#${order.id}</span></div>
                    <div style="text-align:right"><div class="card-time">${formatTime(order.created_at)}</div><div class="card-elapsed">${elapsedText(order.created_at)}</div></div>
                </div>
                ${itemsHtml}
                <div class="serve-progress">${progressText}</div>
                ${order.special_requests?`<div class="card-special">${order.special_requests}</div>`:''}
            </div>`;
        }

        // === 会計カード（テーブル単位） ===
        function renderBillCard(tableNum, orders) {
            const grandTotal = orders.reduce((s,o)=>s+o.total_amount, 0);
            const orderIds = orders.map(o=>o.id);

            const ordersHtml = orders.map(o => `<div class="bill-order">
                <div class="bill-order-header">#${o.id} ${formatTime(o.created_at)}</div>
                ${o.items.map(i=>{
                    if(i.is_cancelled) return `<div class="bill-item-row" style="text-decoration:line-through;color:#ccc"><span>${i.item_name} ×${i.quantity}</span><span>${formatPrice(i.item_price*i.quantity)}</span></div>`;
                    if(i.cancelled_qty > 0) {
                        const activeQty = i.quantity - i.cancelled_qty;
                        return `<div class="bill-item-row"><span>${i.item_name} ×${activeQty}</span><span>${formatPrice(i.item_price*activeQty)}</span></div>`
                            + `<div class="bill-item-row" style="text-decoration:line-through;color:#ccc;font-size:11px"><span>　└ ${i.cancelled_qty}個キャンセル</span><span>-${formatPrice(i.item_price*i.cancelled_qty)}</span></div>`;
                    }
                    return `<div class="bill-item-row"><span>${i.item_name} ×${i.quantity}</span><span>${formatPrice(i.item_price*i.quantity)}</span></div>`;
                }).join('')}
                <div class="bill-subtotal">小計 ${formatPrice(o.total_amount)}</div>
            </div>`).join('');

            return `<div class="bill-card">
                <div class="bill-table">T${tableNum}</div>
                ${ordersHtml}
                <div class="bill-grand-total">${formatPrice(grandTotal)}</div>
                <div class="bill-actions"><button class="btn btn-paid" onclick="markPaid([${orderIds.join(',')}])">会計済み</button></div>
            </div>`;
        }

        function emptyHtml(icon,text) { return `<div class="empty-col"><span>${icon}</span>${text}</div>`; }

        // === API呼び出し ===
        async function toggleServe(itemId) {
            try {
                await fetch(`${API_BASE}/serve.php`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({item_id:itemId})
                });
                loadOrders();
            } catch(e) { console.error(e); }
        }

        async function updateStatus(orderId, status) {
            try {
                await fetch(`${API_BASE}/status.php`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({order_id:orderId, status})
                });
                loadOrders();
            } catch(e) { console.error(e); }
        }

        async function markPaid(orderIds) {
            try {
                for (const id of orderIds) {
                    await fetch(`${API_BASE}/status.php`, {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({order_id:id, status:'paid'})
                    });
                }
                loadOrders();
            } catch(e) { console.error(e); }
        }

        function cancelOrder(id) { if(confirm('この注文をキャンセルしますか？')) updateStatus(id,'cancelled'); }

        async function cancelItem(itemId) {
            if(!confirm('この品目を全てキャンセルしますか？')) return;
            try {
                await fetch(`${API_BASE}/serve.php`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({item_id:itemId, action:'cancel'})
                });
                loadOrders();
            } catch(e) { console.error(e); }
        }

        async function cancelOneItem(itemId) {
            if(!confirm('1個キャンセルしますか？')) return;
            try {
                await fetch(`${API_BASE}/serve.php`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({item_id:itemId, action:'cancel_one'})
                });
                loadOrders();
            } catch(e) { console.error(e); }
        }

        // === データ読み込み ===
        async function loadOrders() {
            try {
                const res = await fetch(`${API_BASE}/orders.php?status=active`);
                const data = await res.json();
                if (!data.success) return;

                const groups = { pending:[], preparing:[], completed:[] };
                data.orders.forEach(o => { if(groups[o.status]) groups[o.status].push(o); });

                // 新着検出
                const newPending = data.orders.filter(o=>o.status==='pending'&&!prevOrderIds.has(o.id));
                const newSet = new Set(newPending.map(o=>o.id));
                if (newPending.length>0 && prevOrderIds.size>0) playNotifySound();

                // 受付
                const pendingCards = document.getElementById('cards-pending');
                document.getElementById('cnt-pending').textContent = groups.pending.length;
                document.getElementById('m-cnt-pending').textContent = groups.pending.length;
                pendingCards.innerHTML = groups.pending.length === 0
                    ? emptyHtml('☕','注文を待っています')
                    : groups.pending.map(o=>renderPendingCard(o, newSet.has(o.id))).join('');

                // 調理中
                const prepCards = document.getElementById('cards-preparing');
                document.getElementById('cnt-preparing').textContent = groups.preparing.length;
                document.getElementById('m-cnt-preparing').textContent = groups.preparing.length;
                prepCards.innerHTML = groups.preparing.length === 0
                    ? emptyHtml('🍳','調理中の注文はありません')
                    : groups.preparing.map(renderPreparingCard).join('');

                // 会計（テーブルごとグループ化）
                const billCards = document.getElementById('cards-completed');
                const tableGroups = {};
                groups.completed.forEach(o => {
                    if(!tableGroups[o.table_number]) tableGroups[o.table_number]=[];
                    tableGroups[o.table_number].push(o);
                });
                const tableNums = Object.keys(tableGroups).sort((a,b)=>a-b);
                document.getElementById('cnt-completed').textContent = tableNums.length;
                document.getElementById('m-cnt-completed').textContent = tableNums.length;
                billCards.innerHTML = tableNums.length === 0
                    ? emptyHtml('💰','会計待ちはありません')
                    : tableNums.map(t=>renderBillCard(t, tableGroups[t])).join('');

                prevOrderIds = new Set(data.orders.map(o=>o.id));
            } catch(e) { console.error(e); }
        }

        function switchMobileTab(status) {
            mobileTab = status;
            document.querySelectorAll('.m-tab').forEach(t=>t.classList.toggle('active',t.dataset.status===status));
            document.querySelectorAll('.kanban-column').forEach(c=>c.classList.remove('active-col'));
            document.getElementById('col-'+status).classList.add('active-col');
        }

        async function showHistory() {
            document.getElementById('history-overlay').classList.remove('hidden');
            try {
                const res = await fetch(`${API_BASE}/orders.php?status=all`);
                const data = await res.json();
                if(!data.success) return;
                const body = document.getElementById('history-body');
                if(data.orders.length===0){ body.innerHTML='<div style="text-align:center;padding:40px;color:#aaa">履歴がありません</div>'; return; }
                body.innerHTML = data.orders.map(o=>`
                    <div class="history-card">
                        <div class="h-top"><span><span class="h-table">T${o.table_number}</span> <span style="color:#aaa;font-size:12px">#${o.id}</span></span><span class="h-status ${o.status}">${STATUS_LABELS[o.status]}</span></div>
                        <div class="h-items">${o.items.map(i=>i.item_name+' ×'+i.quantity).join('、')} — ${formatPrice(o.total_amount)}</div>
                        <div class="h-time">${formatTime(o.created_at)} (${elapsedText(o.created_at)})</div>
                    </div>
                `).join('');
            } catch(e){ console.error(e); }
        }
        function closeHistory(){ document.getElementById('history-overlay').classList.add('hidden'); }

        loadOrders();
        setInterval(loadOrders, 3000);
    </script>
</body>
</html>
