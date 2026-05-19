<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メニュー管理</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', sans-serif; }
        .header { background: #333; color: #fff; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { font-size: 18px; }
        .container { padding: 16px; max-width: 800px; margin: 0 auto; }
        .btn-add { background: #4CAF50; color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .menu-table { width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden; margin-top: 16px; }
        .menu-table table { width: 100%; border-collapse: collapse; }
        .menu-table th { background: #f5f5f5; padding: 12px 16px; text-align: left; font-size: 13px; color: #666; font-weight: 600; }
        .menu-table td { padding: 12px 16px; border-top: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
        .status-on  { background: #E8F5E9; color: #2E7D32; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-off { background: #FAFAFA; color: #999; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .btn-edit   { background: #2196F3; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; margin-right: 4px; }
        .btn-delete { background: #fff; color: #f44336; border: 1px solid #f44336; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; }
        .btn-toggle { background: #fff; color: #FF9800; border: 1px solid #FF9800; border-radius: 6px; padding: 6px 12px; font-size: 12px; cursor: pointer; }

        /* モーダル */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .modal { background: #fff; border-radius: 12px; padding: 24px; width: 90%; max-width: 480px; }
        .modal h2 { margin-bottom: 20px; font-size: 18px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        .form-group textarea { height: 80px; resize: vertical; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-save   { background: #4CAF50; color: #fff; border: none; border-radius: 8px; padding: 11px 20px; font-size: 14px; font-weight: 600; cursor: pointer; flex: 1; }
        .btn-cancel { background: #f5f5f5; color: #666; border: none; border-radius: 8px; padding: 11px 20px; font-size: 14px; cursor: pointer; }
        .hidden { display: none; }
        a.back { color: #ccc; font-size: 13px; text-decoration: none; }
    </style>
</head>
<body>
<?php
require_once '../config/config.php';
require_admin_login();
?>
    <div class="header">
        <h1>🥗 メニュー管理</h1>
        <div style="display:flex;gap:12px;align-items:center">
            <button class="btn-add" onclick="openModal()">＋ 追加</button>
            <a href="dashboard.php" class="back">← 注文管理</a>
        </div>
    </div>

    <div class="container">
        <div class="menu-table">
            <table>
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>カテゴリ</th>
                        <th>価格</th>
                        <th>状態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="menu-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- 追加/編集モーダル -->
    <div class="modal-overlay hidden" id="modal">
        <div class="modal">
            <h2 id="modal-title">メニュー追加</h2>
            <input type="hidden" id="edit-id">
            <div class="form-group">
                <label>カテゴリ</label>
                <select id="edit-category">
                    <option value="">カテゴリなし</option>
                </select>
            </div>
            <div class="form-group">
                <label>商品名 <span style="color:red">*</span></label>
                <input type="text" id="edit-name" placeholder="例: ラーメン">
            </div>
            <div class="form-group">
                <label>説明</label>
                <textarea id="edit-desc" placeholder="例: 醤油ベースのあっさり味"></textarea>
            </div>
            <div class="form-group">
                <label>価格（円） <span style="color:red">*</span></label>
                <input type="number" id="edit-price" placeholder="例: 750" min="0">
            </div>
            <div class="form-group">
                <label>画像URL</label>
                <input type="text" id="edit-image" placeholder="https://example.com/image.jpg">
            </div>
            <div class="form-group">
                <label>販売状態</label>
                <select id="edit-available">
                    <option value="1">販売中</option>
                    <option value="0">売り切れ</option>
                </select>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">キャンセル</button>
                <button class="btn-save" onclick="saveMenu()">保存する</button>
            </div>
        </div>
    </div>

    <script>
        let categories = [];
        let menuItems  = [];

        async function loadMenu() {
            const res  = await fetch('../api/menu.php');
            const data = await res.json();
            if (!data.success) return;
            menuItems  = data.items;
            categories = data.categories;

            // カテゴリセレクト更新
            const sel = document.getElementById('edit-category');
            sel.innerHTML = '<option value="">カテゴリなし</option>';
            categories.forEach(c => {
                sel.innerHTML += `<option value="${c}">${c}</option>`;
            });

            // テーブル描画
            const tbody = document.getElementById('menu-tbody');
            if (menuItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#aaa;padding:24px">メニューがありません</td></tr>';
                return;
            }
            tbody.innerHTML = menuItems.map(item => `
                <tr>
                    <td>
                        <strong>${item.name}</strong>
                        ${item.description ? `<div style="font-size:12px;color:#999;margin-top:2px">${item.description}</div>` : ''}
                    </td>
                    <td>${item.category || '-'}</td>
                    <td>¥${item.price.toLocaleString()}</td>
                    <td><span class="${item.is_available ? 'status-on' : 'status-off'}">${item.is_available ? '販売中' : '売切'}</span></td>
                    <td>
                        <button class="btn-edit" onclick='openModal(${JSON.stringify(item)})'>編集</button>
                        <button class="btn-toggle" onclick="toggleAvailable(${item.id}, ${item.is_available ? 0 : 1})">${item.is_available ? '売切' : '再開'}</button>
                        <button class="btn-delete" onclick="deleteMenu(${item.id}, '${item.name}')">削除</button>
                    </td>
                </tr>
            `).join('');
        }

        function openModal(item = null) {
            document.getElementById('modal-title').textContent = item ? 'メニュー編集' : 'メニュー追加';
            document.getElementById('edit-id').value      = item ? item.id : '';
            document.getElementById('edit-name').value    = item ? item.name : '';
            document.getElementById('edit-desc').value    = item ? (item.description || '') : '';
            document.getElementById('edit-price').value   = item ? item.price : '';
            document.getElementById('edit-image').value   = item ? (item.image_url || '') : '';
            document.getElementById('edit-available').value = item ? (item.is_available ? '1' : '0') : '1';
            if (item && item.category) {
                document.getElementById('edit-category').value = item.category;
            }
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        async function getCategoryId(name) {
            // カテゴリ名からIDを取得（簡易: APIを通じてメニュー取得時に含める改善余地あり）
            // ここではカテゴリ名をそのまま送信し、サーバー側で解決
            return name;
        }

        async function saveMenu() {
            const id        = document.getElementById('edit-id').value;
            const catName   = document.getElementById('edit-category').value;
            const name      = document.getElementById('edit-name').value.trim();
            const desc      = document.getElementById('edit-desc').value.trim();
            const price     = parseInt(document.getElementById('edit-price').value);
            const image_url = document.getElementById('edit-image').value.trim();
            const available = document.getElementById('edit-available').value;

            if (!name || isNaN(price)) {
                alert('商品名と価格は必須です');
                return;
            }

            // カテゴリIDを解決
            let category_id = null;
            if (catName) {
                const res2 = await fetch('../api/menu.php');
                const d2 = await res2.json();
                // カテゴリIDは別途取得APIが必要だが、ここではPHP側でカテゴリ名で検索するよう拡張
                category_id = catName; // PHP側で名前からIDに変換
            }

            const payload = { name, description: desc, price, image_url, is_available: parseInt(available), category_name: catName };
            if (id) payload.id = parseInt(id);

            const method = id ? 'PUT' : 'POST';
            const res = await fetch('../api/menu.php', {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                closeModal();
                loadMenu();
            } else {
                alert(data.error || '保存に失敗しました');
            }
        }

        async function toggleAvailable(id, newVal) {
            const item = menuItems.find(m => m.id === id);
            if (!item) return;
            const payload = { ...item, id, is_available: newVal, category_name: item.category || '' };
            await fetch('../api/menu.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            loadMenu();
        }

        async function deleteMenu(id, name) {
            if (!confirm(`「${name}」を削除しますか？`)) return;
            await fetch('../api/menu.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            loadMenu();
        }

        loadMenu();
    </script>
</body>
</html>
