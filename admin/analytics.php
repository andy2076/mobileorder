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
    <title>売上分析 - <?= htmlspecialchars($store['name']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', sans-serif; }

        .header { background: #333; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .header h1 { font-size: 17px; font-weight: 700; }
        .header-right { display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .header-right a { color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.15); }
        .header-right a:visited { color: #fff; }
        .header-right a:hover { background: rgba(255,255,255,0.25); }

        .container { max-width: 1000px; margin: 0 auto; padding: 16px; }

        /* 期間セレクタ */
        .period-bar { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .period-btn { padding: 8px 16px; border: 2px solid #ddd; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; color: #666; }
        .period-btn.active { border-color: #4CAF50; background: #E8F5E9; color: #2E7D32; }
        .period-btn:hover { border-color: #999; }
        .date-input { padding: 7px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 13px; }

        /* サマリーカード */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .summary-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .summary-label { font-size: 12px; color: #999; font-weight: 600; margin-bottom: 6px; }
        .summary-value { font-size: 28px; font-weight: 800; color: #333; }
        .summary-value.revenue { color: #4CAF50; }
        .summary-sub { font-size: 11px; color: #bbb; margin-top: 4px; }

        /* チャートエリア */
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .chart-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .chart-card.full { grid-column: 1 / -1; }
        .chart-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 16px; }
        .chart-wrap { position: relative; height: 280px; }

        /* ランキングテーブル */
        .rank-table { width: 100%; border-collapse: collapse; }
        .rank-table th { text-align: left; font-size: 12px; color: #999; padding: 8px 0; border-bottom: 2px solid #eee; }
        .rank-table td { padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
        .rank-num { width: 36px; font-weight: 800; color: #999; }
        .rank-num.top { color: #FF9800; font-size: 16px; }
        .rank-bar { height: 6px; background: #4CAF50; border-radius: 3px; margin-top: 4px; transition: width 0.5s; }
        .rank-name { font-weight: 600; }
        .rank-qty { text-align: right; font-weight: 700; color: #333; }
        .rank-rev { text-align: right; color: #999; font-size: 13px; }

        .loading { text-align: center; padding: 60px; color: #aaa; }

        @media (max-width: 767px) {
            .chart-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .summary-value { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($store['name']) ?> - 売上分析</h1>
        <div class="header-right">
            <a href="<?= store_url('admin/dashboard.php') ?>">注文管理</a>
            <a href="<?= store_url('admin/menu.php') ?>">メニュー管理</a>
        </div>
    </div>

    <div class="container">
        <div class="period-bar">
            <button class="period-btn active" onclick="setPeriod('today')">今日</button>
            <button class="period-btn" onclick="setPeriod('week')">7日間</button>
            <button class="period-btn" onclick="setPeriod('month')">30日間</button>
            <span style="color:#aaa;font-size:13px;margin-left:8px;">カスタム:</span>
            <input type="date" class="date-input" id="date-from">
            <span style="color:#aaa">〜</span>
            <input type="date" class="date-input" id="date-to">
            <button class="period-btn" onclick="setPeriod('custom')">適用</button>
        </div>

        <div class="summary-grid" id="summary-grid">
            <div class="loading">読み込み中...</div>
        </div>

        <div class="chart-grid">
            <div class="chart-card full">
                <div class="chart-title">日別売上推移</div>
                <div class="chart-wrap"><canvas id="chart-daily"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">時間帯別注文数</div>
                <div class="chart-wrap"><canvas id="chart-hourly"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">人気メニュー TOP10</div>
                <div id="rank-container"></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">曜日別売上</div>
                <div class="chart-wrap"><canvas id="chart-weekday"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-title">テーブル別売上</div>
                <div id="table-container"></div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '<?= $api_base ?>';
        let currentPeriod = 'today';
        let dailyChart = null, hourlyChart = null, weekdayChart = null;

        function formatPrice(p) { return new Intl.NumberFormat('ja-JP',{style:'currency',currency:'JPY'}).format(p); }

        function setPeriod(p) {
            currentPeriod = p;
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            loadAnalytics();
        }

        async function loadAnalytics() {
            let url = `${API_BASE}/analytics.php?period=${currentPeriod}`;
            if (currentPeriod === 'custom') {
                const from = document.getElementById('date-from').value;
                const to = document.getElementById('date-to').value;
                if (!from || !to) { alert('日付を選択してください'); return; }
                url += `&from=${from}&to=${to}`;
            }

            try {
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) return;

                renderSummary(data.summary);
                renderDailyChart(data.daily);
                renderHourlyChart(data.hourly);
                renderRanking(data.popular);
                renderWeekdayChart(data.weekday);
                renderTableRanking(data.tables);
            } catch(e) { console.error(e); }
        }

        function renderSummary(s) {
            document.getElementById('summary-grid').innerHTML = `
                <div class="summary-card">
                    <div class="summary-label">売上</div>
                    <div class="summary-value revenue">${formatPrice(s.revenue)}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">注文数</div>
                    <div class="summary-value">${s.order_count}</div>
                    <div class="summary-sub">キャンセル: ${s.cancelled}件</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">客単価</div>
                    <div class="summary-value">${formatPrice(s.avg_order)}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">販売個数</div>
                    <div class="summary-value">${s.total_items}</div>
                </div>
            `;
        }

        function renderDailyChart(daily) {
            if (dailyChart) dailyChart.destroy();
            const ctx = document.getElementById('chart-daily').getContext('2d');
            dailyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: daily.map(d => { const dt = new Date(d.date); return (dt.getMonth()+1)+'/'+dt.getDate(); }),
                    datasets: [{
                        label: '売上',
                        data: daily.map(d => d.revenue),
                        backgroundColor: 'rgba(76,175,80,0.6)',
                        borderRadius: 6,
                        yAxisID: 'y'
                    },{
                        label: '注文数',
                        data: daily.map(d => d.orders),
                        type: 'line',
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33,150,243,0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { position: 'left', ticks: { callback: v => '¥'+v.toLocaleString() } },
                        y1: { position: 'right', grid: { display: false }, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        function renderHourlyChart(hourly) {
            if (hourlyChart) hourlyChart.destroy();
            // 全時間帯を埋める (0-23)
            const full = Array.from({length:24}, (_,i) => {
                const found = hourly.find(h => h.hour === i);
                return { hour: i, orders: found ? found.orders : 0, revenue: found ? found.revenue : 0 };
            });

            const ctx = document.getElementById('chart-hourly').getContext('2d');
            hourlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: full.map(h => h.hour + '時'),
                    datasets: [{
                        label: '注文数',
                        data: full.map(h => h.orders),
                        backgroundColor: full.map(h => h.orders > 0 ? 'rgba(33,150,243,0.6)' : 'rgba(200,200,200,0.3)'),
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { ticks: { stepSize: 1 } } }
                }
            });
        }

        function renderRanking(popular) {
            const container = document.getElementById('rank-container');
            if (popular.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#bbb">データがありません</div>';
                return;
            }
            const maxQty = popular[0].total_qty;
            container.innerHTML = `<table class="rank-table">
                <thead><tr><th></th><th>商品名</th><th style="text-align:right">数量</th><th style="text-align:right">売上</th></tr></thead>
                <tbody>${popular.map((p, i) => `
                    <tr>
                        <td class="rank-num ${i < 3 ? 'top' : ''}">${i + 1}</td>
                        <td>
                            <div class="rank-name">${p.item_name}</div>
                            <div class="rank-bar" style="width:${Math.round(p.total_qty/maxQty*100)}%"></div>
                        </td>
                        <td class="rank-qty">${p.total_qty}個</td>
                        <td class="rank-rev">${formatPrice(p.total_revenue)}</td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
        }

        function renderWeekdayChart(weekday) {
            if (weekdayChart) weekdayChart.destroy();
            const DOW_LABELS = ['','日','月','火','水','木','金','土'];
            const DOW_ORDER = [2,3,4,5,6,7,1]; // 月〜日
            const full = DOW_ORDER.map(d => {
                const found = weekday.find(w => w.dow === d);
                return { label: DOW_LABELS[d], revenue: found ? found.revenue : 0, orders: found ? found.orders : 0, avg: found ? found.avg_order : 0 };
            });
            const ctx = document.getElementById('chart-weekday').getContext('2d');
            const colors = full.map(f => f.revenue > 0 ? 'rgba(255,152,0,0.6)' : 'rgba(200,200,200,0.3)');
            weekdayChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: full.map(f => f.label),
                    datasets: [{
                        label: '売上',
                        data: full.map(f => f.revenue),
                        backgroundColor: colors,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { afterLabel: (ctx) => {
                            const d = full[ctx.dataIndex];
                            return '注文数: ' + d.orders + '\n客単価: ' + formatPrice(d.avg);
                        }}}
                    },
                    scales: { y: { ticks: { callback: v => '¥'+v.toLocaleString() } } }
                }
            });
        }

        function renderTableRanking(tables) {
            const container = document.getElementById('table-container');
            if (tables.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#bbb">データがありません</div>';
                return;
            }
            const maxRev = tables[0].revenue;
            container.innerHTML = `<table class="rank-table">
                <thead><tr><th>テーブル</th><th>注文数</th><th style="text-align:right">売上</th><th style="text-align:right">客単価</th></tr></thead>
                <tbody>${tables.map(t => `
                    <tr>
                        <td><span class="rank-name">T${t.table_number}</span>
                            <div class="rank-bar" style="width:${Math.round(t.revenue/maxRev*100)}%;background:#9C27B0"></div>
                        </td>
                        <td>${t.orders}件</td>
                        <td class="rank-qty">${formatPrice(t.revenue)}</td>
                        <td class="rank-rev">${formatPrice(t.avg_order)}</td>
                    </tr>
                `).join('')}</tbody>
            </table>`;
        }

        // 初期日付セット
        const today = new Date();
        document.getElementById('date-to').value = today.toISOString().split('T')[0];
        const weekAgo = new Date(today); weekAgo.setDate(weekAgo.getDate() - 7);
        document.getElementById('date-from').value = weekAgo.toISOString().split('T')[0];

        loadAnalytics();
    </script>
</body>
</html>
