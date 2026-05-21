# モバイルオーダーシステム セットアップガイド

## 🚀 クイックスタート（開発環境）

### 必要な環境
- PHP 7.4 以上
- MySQL または MariaDB
- Web サーバー（Apache/Nginx）

### 1. ファイルの配置
```bash
# Web サーバーのドキュメントルートに配置
cp -r mobileorder/ /var/www/html/
```

### 2. データベースの準備
```bash
# MySQL にログイン
mysql -u root -p

# データベース作成
CREATE DATABASE mobileorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mobileorder_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON mobileorder.* TO 'mobileorder_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# スキーマとサンプルデータを挿入
mysql -u mobileorder_user -p mobileorder < mobileorder/sql/schema.sql
```

### 3. 設定ファイルの編集

`config/database.php` を編集:
```php
private $host = 'localhost';
private $db_name = 'mobileorder';
private $username = 'mobileorder_user';
private $password = 'secure_password';
```

### 4. 動作確認

1. **管理画面**: `http://localhost/mobileorder/admin/login.php`
   - ユーザー名: `admin`
   - パスワード: `admin123`

2. **顧客画面**: `http://localhost/mobileorder/public/index.html`

3. **QR生成**: `http://localhost/mobileorder/public/qr-generator.html`

## 🏪 本番環境セットアップ

### 1. サーバー要件
- Linux サーバー（Ubuntu 20.04+ 推奨）
- PHP 7.4+ with extensions: pdo_mysql, json, mbstring
- MySQL 5.7+ または MariaDB 10.3+
- Apache 2.4+ または Nginx 1.18+
- SSL証明書（Let's Encrypt推奨）

### 2. Apache での設定

`.htaccess` ファイル作成:
```apache
RewriteEngine On

# API リクエストの処理
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1.php [L,QSA]

# HTTPS 強制（本番環境）
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# セキュリティヘッダー
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
```

### 3. Nginx での設定

`/etc/nginx/sites-available/mobileorder` 作成:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/html/mobileorder;
    index index.html index.php;

    # SSL 設定
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    # セキュリティヘッダー
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;

    # API ルーティング
    location ~ ^/api/(.+)$ {
        try_files $uri /api/$1.php;
    }

    # PHP 処理
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静的ファイル
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # 管理画面の保護（オプション）
    location /admin/ {
        allow 192.168.1.0/24;  # 内部ネットワークのみ
        deny all;
    }
}
```

### 4. SSL証明書の設定（Let's Encrypt）

```bash
# Certbot インストール
sudo apt update
sudo apt install certbot python3-certbot-apache

# 証明書取得
sudo certbot --apache -d your-domain.com

# 自動更新設定
sudo crontab -e
# 以下を追加:
0 12 * * * /usr/bin/certbot renew --quiet
```

### 5. ファイル権限の設定

```bash
# 適切な所有者設定
sudo chown -R www-data:www-data /var/www/html/mobileorder
sudo chmod -R 755 /var/www/html/mobileorder
sudo chmod -R 644 /var/www/html/mobileorder/config/*.php

# 画像アップロード用ディレクトリ
sudo chmod 755 /var/www/html/mobileorder/images/
```

### 6. セキュリティ設定

#### PHP 設定（`/etc/php/7.4/apache2/php.ini`）
```ini
; セキュリティ設定
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; アップロード制限
upload_max_filesize = 5M
post_max_size = 5M
max_execution_time = 30

; セッション設定
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

#### データベースセキュリティ
```sql
-- 管理者パスワード変更
UPDATE admins SET password_hash = '$2y$10$new_hashed_password' WHERE username = 'admin';

-- 不要なデフォルトユーザー削除
DELETE FROM admins WHERE username != 'admin';
```

## 📱 PWA 設定

### 1. manifest.json の編集

`public/manifest.json` のURLを本番ドメインに変更:
```json
{
  "start_url": "https://your-domain.com/mobileorder/public/index.html",
  "scope": "https://your-domain.com/mobileorder/"
}
```

### 2. Service Worker の設定

`public/sw.js` のキャッシュ対象URLを更新:
```javascript
const urlsToCache = [
  'https://your-domain.com/mobileorder/public/',
  'https://your-domain.com/mobileorder/public/index.html',
  // ... 他のファイル
];
```

## 📊 監視とメンテナンス

### 1. ログ設定

```bash
# ログローテーション設定
sudo vim /etc/logrotate.d/mobileorder

/var/log/mobileorder/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
}
```

### 2. データベースバックアップ

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/mobileorder"
DB_NAME="mobileorder"
DB_USER="mobileorder_user"
DB_PASS="secure_password"

mkdir -p $BACKUP_DIR

# データベースバックアップ
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# 古いバックアップを削除（30日以上）
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete

# バックアップのcrontab設定
# 0 2 * * * /path/to/backup.sh
```

### 3. 性能監視

```bash
# Apache アクセスログ解析
sudo tail -f /var/log/apache2/access.log | grep mobileorder

# MySQL クエリログ
sudo tail -f /var/log/mysql/mysql.log

# PHP エラーログ
sudo tail -f /var/log/php_errors.log
```

## 🔧 カスタマイズガイド

### 1. デザインの変更

#### カラーテーマの変更
`css/style.css` の `:root` 変数を編集:
```css
:root {
    --primary-color: #your-color;
    --primary-dark: #your-dark-color;
    /* ... */
}
```

#### ロゴの追加
1. `images/logo.png` に配置
2. `public/index.html` のヘッダーに追加:
```html
<img src="../images/logo.png" alt="ロゴ" class="logo">
```

### 2. 機能の追加

#### 新しいAPIエンドポイント
1. `api/new-feature.php` を作成
2. 適切なHTTPメソッドとレスポンス形式で実装
3. フロントエンドから呼び出し

#### データベーステーブルの追加
1. `sql/schema.sql` に新しいテーブル定義を追加
2. 必要に応じてマイグレーションスクリプトを作成

## ❗ トラブルシューティング

### よくある問題と解決方法

#### 1. 「データベース接続エラー」
```bash
# MySQL サービス確認
sudo systemctl status mysql

# 接続テスト
mysql -u mobileorder_user -p mobileorder

# 設定ファイル確認
cat config/database.php
```

#### 2. 「API 500 エラー」
```bash
# PHP エラーログ確認
tail -f /var/log/php_errors.log

# Apache エラーログ確認
tail -f /var/log/apache2/error.log

# ファイル権限確認
ls -la api/
```

#### 3. 「PWA がインストールできない」
- HTTPS接続されているか確認
- manifest.json が正しく配信されているか確認
- Service Worker がブロックされていないか確認

#### 4. 「リアルタイム更新が動作しない」
- ブラウザの開発者ツールでネットワークタブを確認
- CORSエラーがないか確認
- API レスポンスが正しいか確認

## 📞 サポート

技術的な問題が発生した場合：

1. エラーログを確認
2. ブラウザの開発者ツールをチェック
3. 設定ファイルを見直し
4. 必要に応じてシステム管理者に相談

## 📋 チェックリスト

### セットアップ完了チェック

- [ ] データベースが正常に作成されている
- [ ] 管理画面にログインできる
- [ ] 顧客画面が表示される
- [ ] QRコード生成ツールが動作する
- [ ] 注文の送信と受信ができる
- [ ] PWA のインストールができる
- [ ] HTTPS 接続されている
- [ ] ログファイルが出力されている
- [ ] バックアップが設定されている

### セキュリティチェック

- [ ] デフォルトパスワードが変更されている
- [ ] SSL証明書が有効
- [ ] 不要なファイルが削除されている
- [ ] ファイル権限が適切に設定されている
- [ ] エラー情報が外部に漏れていない

これでモバイルオーダーシステムのセットアップは完了です！