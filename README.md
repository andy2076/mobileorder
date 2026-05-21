# モバイルオーダーシステム

地方小規模店舗向けのモバイル注文システムです。QRコードを使用して顧客がスマートフォンから直接注文でき、店舗側はリアルタイムで注文を管理できます。

## 主な機能

### 顧客側機能
- QRコード読み取り後のテーブル番号入力
- カテゴリ別メニュー表示
- 商品選択・数量指定・カート機能
- 注文確定・送信
- PWA対応（オフライン機能・ホーム画面追加）

### 店舗側機能
- 管理画面ログイン
- リアルタイム注文受信・表示
- 注文状況更新（調理開始・完了）
- メニュー管理（追加・編集・削除・在庫切れ設定）
- 日次売上集計
- QRコード生成ツール

## 技術仕様

- **フロントエンド**: HTML5, CSS3, JavaScript (Vanilla)
- **バックエンド**: PHP 7.4+
- **データベース**: MySQL 5.7+ / MariaDB 10.3+
- **PWA**: Service Worker, Web App Manifest
- **リアルタイム通信**: JavaScript ポーリング（3秒間隔）

## ファイル構成

```
mobileorder/
├── public/                 # 顧客用画面
│   ├── index.html         # メイン注文画面
│   ├── table.html         # テーブル選択画面
│   ├── qr-generator.html  # QRコード生成ツール
│   ├── manifest.json      # PWA マニフェスト
│   ├── sw.js             # Service Worker
│   └── offline.html       # オフライン画面
├── admin/                 # 管理画面
│   ├── login.php         # ログイン画面
│   ├── dashboard.php     # 注文管理ダッシュボード
│   ├── menu.php          # メニュー管理
│   └── settings.php      # 基本設定
├── api/                   # REST API
│   ├── auth.php          # 認証API
│   ├── menu.php          # メニュー管理API
│   ├── order.php         # 注文送信API
│   ├── orders.php        # 注文一覧取得API
│   └── status.php        # 注文状況更新API
├── config/                # 設定ファイル
│   ├── database.php      # DB接続設定
│   └── config.php        # 基本設定
├── css/                   # スタイルシート
│   ├── style.css         # 顧客画面用
│   └── admin.css         # 管理画面用
├── js/                    # JavaScript
│   ├── order.js          # 注文処理
│   ├── admin.js          # 管理画面
│   ├── realtime.js       # リアルタイム通信
│   └── pwa.js            # PWA機能
├── sql/                   # データベース
│   └── schema.sql        # テーブル定義・初期データ
└── images/                # 画像ファイル
    ├── icon-192.png      # PWA アイコン
    ├── icon-512.png      # PWA アイコン
    └── no-image.jpg      # デフォルト商品画像
```

## セットアップ手順

### 1. 環境要件
- PHP 7.4 以上
- MySQL 5.7 以上 または MariaDB 10.3 以上
- Apache または Nginx
- HTTPS 対応（PWA機能に必要）

### 2. データベース設定

1. MySQLにデータベースを作成:
```sql
CREATE DATABASE mobileorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. スキーマファイルを実行:
```bash
mysql -u [username] -p mobileorder < sql/schema.sql
```

### 3. 設定ファイルの編集

`config/database.php` を環境に合わせて編集:
```php
private $host = 'localhost';        # DBホスト
private $db_name = 'mobileorder';   # DB名
private $username = 'your_user';    # DBユーザー名
private $password = 'your_pass';    # DBパスワード
```

`config/config.php` のベースURLを編集:
```php
define('BASE_URL', '/mobileorder');  # インストールパス
```

### 4. Webサーバー設定

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1.php [L]

# HTTPS リダイレクト（本番環境）
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### Nginx
```nginx
location /mobileorder/api/ {
    try_files $uri $uri.php;
}

# HTTPS リダイレクト（本番環境）
if ($scheme != "https") {
    return 301 https://$server_name$request_uri;
}
```

### 5. ファイル権限設定

```bash
chmod 755 mobileorder/
chmod 644 mobileorder/config/*.php
chmod 755 mobileorder/images/
```

## 初期設定

### 1. 管理者アカウント

デフォルトアカウント:
- **ユーザー名**: admin
- **パスワード**: admin123

### 2. 店舗情報設定

1. `/admin/login.php` にアクセス
2. デフォルトアカウントでログイン
3. 「設定」から店舗情報を更新

### 3. メニュー登録

1. 「メニュー管理」でサンプルメニューを確認
2. 実際のメニューを追加・編集
3. 商品画像URLを設定

### 4. QRコード生成

1. `/public/qr-generator.html` にアクセス
2. ベースURLを正しいドメインに設定
3. テーブル数を設定してQRコード生成
4. 印刷してテーブルに配置

## 使用方法

### 顧客の注文手順

1. テーブルのQRコードをスマートフォンで読み取り
2. テーブル番号を確認・入力
3. メニューから商品を選択してカートに追加
4. 特別な要望があれば入力
5. 注文を確定

### 店舗の注文管理

1. 管理画面にログイン
2. ダッシュボードで新規注文を確認
3. 「調理開始」ボタンで調理開始
4. 調理完了後「調理完了」ボタンで顧客に通知
5. 商品提供後「受渡完了」ボタンで注文完了

## カスタマイズ

### デザインの変更

- `css/style.css`: 顧客画面のスタイル
- `css/admin.css`: 管理画面のスタイル
- `:root` 変数でテーマカラーを一括変更可能

### 機能の追加

- `api/` フォルダに新しいAPIエンドポイントを追加
- データベーススキーマは `sql/schema.sql` を編集
- フロントエンドは各JSファイルに機能を追加

## トラブルシューティング

### よくある問題

1. **データベース接続エラー**
   - `config/database.php` の設定を確認
   - MySQLサービスが起動しているか確認

2. **API エラー 500**
   - PHPエラーログを確認
   - ファイル権限を確認

3. **PWA が動作しない**
   - HTTPS接続されているか確認
   - Service Worker がブロックされていないか確認

4. **リアルタイム更新が動作しない**
   - ブラウザのネットワークタブでAPI呼び出しを確認
   - CORSエラーがないか確認

### ログの確認

- PHP エラーログ: `/var/log/php_errors.log`
- Apache ログ: `/var/log/apache2/error.log`
- ブラウザコンソール: F12 開発者ツール

## セキュリティ

### 実装済みのセキュリティ機能

- SQLインジェクション対策（PDO prepared statements）
- XSS対策（htmlspecialchars）
- CSRF保護（トークン認証）
- パスワードハッシュ化（password_hash）
- セッション管理

### 推奨設定

1. **HTTPS の強制**
2. **定期的なバックアップ**
3. **管理者パスワードの変更**
4. **不要なファイルの削除**
5. **アクセスログの監視**

## ライセンス

このプロジェクトはMITライセンスの下で公開されています。

## サポート

質問や問題がある場合は、以下の手順で対応してください：

1. このREADMEのトラブルシューティングを確認
2. ブラウザの開発者ツールでエラーを確認
3. PHPエラーログを確認
4. 必要に応じて設定ファイルを見直し

## 更新履歴

### v1.0.0 (2025-08-19)
- 初回リリース
- 基本的な注文・管理機能
- PWA対応
- リアルタイム通信