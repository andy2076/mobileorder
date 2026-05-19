<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f0f2f5; }
        .login-box { background: #fff; border-radius: 12px; padding: 40px 32px; width: 100%; max-width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .login-box h1 { text-align: center; font-size: 22px; margin-bottom: 8px; color: #333; }
        .login-box p { text-align: center; color: #888; font-size: 14px; margin-bottom: 28px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #4CAF50; }
        .btn-login { width: 100%; padding: 13px; background: #4CAF50; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .btn-login:hover { background: #43A047; }
        .error-msg { background: #ffebee; color: #c62828; border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; display: none; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🍽️ 管理者ログイン</h1>
        <p>モバイルオーダー 店舗管理</p>
        <div class="error-msg" id="error-msg"></div>
        <div class="form-group">
            <label>ユーザー名</label>
            <input type="text" id="username" placeholder="admin" autocomplete="username">
        </div>
        <div class="form-group">
            <label>パスワード</label>
            <input type="password" id="password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <button class="btn-login" onclick="login()">ログイン</button>
    </div>
    <script>
        document.addEventListener('keypress', e => { if (e.key === 'Enter') login(); });
        async function login() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorMsg = document.getElementById('error-msg');
            errorMsg.style.display = 'none';
            if (!username || !password) {
                errorMsg.textContent = 'ユーザー名とパスワードを入力してください';
                errorMsg.style.display = 'block';
                return;
            }
            try {
                const res = await fetch('../api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.textContent = data.error;
                    errorMsg.style.display = 'block';
                }
            } catch (e) {
                errorMsg.textContent = 'ログインに失敗しました';
                errorMsg.style.display = 'block';
            }
        }
    </script>
</body>
</html>
