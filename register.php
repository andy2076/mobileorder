<?php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invite_code = trim($_POST['invite_code'] ?? '');
    $store_name  = trim($_POST['store_name'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';

    // バリデーション
    if (!$invite_code || !$store_name || !$slug || !$username || !$password) {
        $error = 'すべての項目を入力してください';
    } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $slug)) {
        $error = 'スラッグは3〜30文字の英小文字・数字・ハイフンで、先頭と末尾にハイフンは使えません';
    } elseif (in_array($slug, ['admin', 'api', 'register', 'static', 'images', 'css', 'js', 'config', 'sql', 'mobileorder', 'public'])) {
        $error = 'そのスラッグは予約語のため使用できません';
    } elseif (strlen($password) < 6) {
        $error = 'パスワードは6文字以上にしてください';
    } else {
        try {
            // 招待コード確認
            $stmt = $db->prepare("SELECT id FROM invite_codes WHERE code = :code AND used_by_store_id IS NULL");
            $stmt->execute([':code' => $invite_code]);
            $invite = $stmt->fetch();

            if (!$invite) {
                $error = '招待コードが無効または使用済みです';
            } else {
                // slug重複チェック
                $stmt = $db->prepare("SELECT id FROM stores WHERE slug = :slug");
                $stmt->execute([':slug' => $slug]);
                if ($stmt->fetch()) {
                    $error = 'そのスラッグは既に使われています';
                } else {
                    $db->beginTransaction();

                    // 店舗作成
                    $stmt = $db->prepare("INSERT INTO stores (slug, name) VALUES (:slug, :name)");
                    $stmt->execute([':slug' => $slug, ':name' => sanitize_input($store_name)]);
                    $store_id = $db->lastInsertId();

                    // 管理者作成
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO admins (store_id, username, password, shop_name) VALUES (:store_id, :username, :password, :shop_name)");
                    $stmt->execute([
                        ':store_id'  => $store_id,
                        ':username'  => sanitize_input($username),
                        ':password'  => $hashed,
                        ':shop_name' => sanitize_input($store_name)
                    ]);

                    // 招待コード使用済みに
                    $stmt = $db->prepare("UPDATE invite_codes SET used_by_store_id = :store_id WHERE id = :id");
                    $stmt->execute([':store_id' => $store_id, ':id' => $invite['id']]);

                    // 画像ディレクトリ作成
                    $img_dir = __DIR__ . '/images/stores/' . $slug . '/menu/';
                    if (!is_dir($img_dir)) {
                        mkdir($img_dir, 0755, true);
                    }

                    $db->commit();
                    $success = true;
                }
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log($e->getMessage());
            $error = '登録に失敗しました。もう一度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店舗登録 - machiorder</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Sans', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; padding: 40px 32px; width: 100%; max-width: 420px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { text-align: center; font-size: 22px; margin-bottom: 6px; color: #333; }
        .subtitle { text-align: center; color: #888; font-size: 14px; margin-bottom: 28px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .form-group input:focus { outline: none; border-color: #4CAF50; }
        .form-group small { font-size: 12px; color: #999; margin-top: 3px; display: block; }
        .btn { width: 100%; padding: 13px; background: #4CAF50; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .btn:hover { background: #43A047; }
        .error { background: #ffebee; color: #c62828; border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
        .success-box { text-align: center; }
        .success-box h2 { color: #4CAF50; margin-bottom: 12px; }
        .success-box a { display: inline-block; margin-top: 16px; background: #4CAF50; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .slug-preview { font-size: 12px; color: #4CAF50; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="card">
<?php if ($success): ?>
        <div class="success-box">
            <h2>登録完了!</h2>
            <p>店舗「<?= htmlspecialchars($store_name) ?>」を登録しました。</p>
            <p style="margin-top:12px;color:#666;font-size:14px;">お客様用URL:<br><strong>https://machiorder.com/s/<?= htmlspecialchars($slug) ?>/</strong></p>
            <a href="/s/<?= htmlspecialchars($slug) ?>/admin/login.php">管理画面にログイン</a>
        </div>
<?php else: ?>
        <h1>店舗登録</h1>
        <p class="subtitle">招待コードをお持ちの方のみ登録できます</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>招待コード <span style="color:red">*</span></label>
                <input type="text" name="invite_code" placeholder="XXXX-XXXX-XXXX" value="<?= htmlspecialchars($invite_code ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>店舗名 <span style="color:red">*</span></label>
                <input type="text" name="store_name" placeholder="例: さくらカフェ" value="<?= htmlspecialchars($store_name ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>スラッグ（URL用ID） <span style="color:red">*</span></label>
                <input type="text" name="slug" id="slug" placeholder="例: sakura-cafe" pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]" value="<?= htmlspecialchars($slug ?? '') ?>" required oninput="updatePreview()">
                <small>英小文字・数字・ハイフン（3〜30文字）</small>
                <div class="slug-preview" id="slug-preview"></div>
            </div>
            <div class="form-group">
                <label>管理者ユーザー名 <span style="color:red">*</span></label>
                <input type="text" name="username" placeholder="例: admin" value="<?= htmlspecialchars($username ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>管理者パスワード <span style="color:red">*</span></label>
                <input type="password" name="password" placeholder="6文字以上" minlength="6" required>
            </div>
            <button type="submit" class="btn">店舗を登録する</button>
        </form>
<?php endif; ?>
    </div>
    <script>
        function updatePreview() {
            const slug = document.getElementById('slug').value;
            const preview = document.getElementById('slug-preview');
            if (slug) {
                preview.textContent = 'URL: https://machiorder.com/s/' + slug + '/';
            } else {
                preview.textContent = '';
            }
        }
        updatePreview();
    </script>
</body>
</html>
