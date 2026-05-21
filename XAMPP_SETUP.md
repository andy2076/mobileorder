# XAMPPでのローカルテスト環境セットアップ

## 🚀 XAMPP環境でのクイックスタート

### 前提条件
- XAMPP がインストール済み
- Apache と MySQL が起動可能

### 1. XAMPPの起動

1. XAMPP Control Panel を開く
2. **Apache** を Start
3. **MySQL** を Start
4. 両方のステータスが緑色（Running）になることを確認

### 2. ファイルの配置

```bash
# ターミナルで実行
cp -r /Users/liiandy/Library/CloudStorage/GoogleDrive-andy.railway@gmail.com/マイドライブ/mobileorder /Applications/XAMPP/xamppfiles/htdocs/
```

または手動でコピー：
1. Finderで `mobileorder` フォルダを選択
2. `/Applications/XAMPP/xamppfiles/htdocs/` にドラッグ&ドロップ

### 3. データベースの作成

#### 方法1: phpMyAdminを使用（推奨）

1. ブラウザで http://localhost/phpmyadmin にアクセス
2. 左側の「新規作成」をクリック
3. データベース名: `mobileorder`
4. 照合順序: `utf8mb4_unicode_ci` を選択
5. 「作成」をクリック

#### 方法2: SQLファイルのインポート

1. phpMyAdminで `mobileorder` データベースを選択
2. 上部の「インポート」タブをクリック
3. 「ファイルを選択」で `mobileorder/sql/schema.sql` を選択
4. 「実行」をクリック

### 4. 動作確認

#### テスト用URL
- **管理画面**: http://localhost/mobileorder/admin/login.php
- **顧客画面**: http://localhost/mobileorder/public/index.html
- **QR生成**: http://localhost/mobileorder/public/qr-generator.html
- **テーブル選択**: http://localhost/mobileorder/public/table.html

#### ログイン情報
- **ユーザー名**: admin
- **パスワード**: admin123

### 5. 基本的な動作テスト

#### 5.1 管理画面テスト
1. http://localhost/mobileorder/admin/login.php にアクセス
2. admin/admin123 でログイン
3. ダッシュボードが表示されることを確認
4. 「メニュー管理」でサンプルメニューが表示されることを確認

#### 5.2 顧客画面テスト
1. http://localhost/mobileorder/public/index.html にアクセス
2. テーブル番号（例：5）を入力
3. メニューが表示されることを確認
4. 商品をカートに追加
5. 注文を確定

#### 5.3 注文の確認
1. 管理画面のダッシュボードで新しい注文が表示されることを確認
2. 注文ステータスを「調理中」→「完成」→「受渡完了」に変更

## 🔧 トラブルシューティング

### よくある問題と解決方法

#### 1. 「データベース接続エラー」

**症状**: ページに「Database connection failed」と表示される

**確認事項**:
```bash
# XAMPPのMySQLが起動しているか確認
# XAMPP Control Panelで MySQL のステータスを確認
```

**解決方法**:
1. XAMPP Control PanelでMySQLを再起動
2. phpMyAdminで接続確認: http://localhost/phpmyadmin
3. データベース `mobileorder` が存在するか確認

#### 2. 「ページが表示されない」

**症状**: 404 Not Found エラー

**確認事項**:
- ファイルが正しく配置されているか
- ApacheがXAMPPで起動しているか

**解決方法**:
```bash
# ファイルが存在するか確認
ls /Applications/XAMPP/xamppfiles/htdocs/mobileorder/

# 正しいURLでアクセスしているか確認
# ○ http://localhost/mobileorder/admin/login.php
# × http://localhost:8080/mobileorder/admin/login.php
```

#### 3. 「API エラー 500」

**症状**: ブラウザの開発者ツールでAPI呼び出し時に500エラー

**確認事項**:
```bash
# PHP エラーログの確認
tail -f /Applications/XAMPP/xamppfiles/logs/php_error_log
```

**解決方法**:
1. `config/database.php` の設定を確認
2. ファイルの権限を確認
3. PHPの構文エラーがないか確認

#### 4. 「メニューが表示されない」

**症状**: 顧客画面でメニューが読み込まれない

**解決方法**:
1. ブラウザの開発者ツール（F12）を開く
2. Networkタブで `api/menu.php` のリクエストを確認
3. エラーレスポンスの内容を確認
4. データベースにサンプルメニューデータが入っているか確認

### 5. PHP設定の調整

#### display_errors の有効化（開発時のみ）

`/Applications/XAMPP/xamppfiles/etc/php.ini` を編集:
```ini
; 開発時のエラー表示
display_errors = On
error_reporting = E_ALL

; ログ設定
log_errors = On
error_log = /Applications/XAMPP/xamppfiles/logs/php_error_log
```

設定変更後はApacheを再起動：
1. XAMPP Control PanelでApacheを「Stop」
2. 少し待ってから「Start」

## 📱 PWA テスト

### ローカル環境でのPWAテスト

**注意**: PWAの完全なテストにはHTTPS接続が必要ですが、localhostでは一部機能をテストできます。

#### テスト可能な機能
- Service Worker の登録
- キャッシュ機能
- オフライン表示

#### テスト手順
1. ブラウザの開発者ツール（F12）を開く
2. 「Application」または「アプリケーション」タブを選択
3. 「Service Workers」でワーカーが登録されているか確認
4. 「Storage」でキャッシュが作成されているか確認

## 🌐 他のデバイスからのアクセス

### 同一ネットワーク内の他のデバイスからテスト

1. **IPアドレスの確認**:
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

2. **他のデバイスからアクセス**:
```
http://[あなたのIPアドレス]/mobileorder/public/index.html
例: http://192.168.1.100/mobileorder/public/index.html
```

3. **ファイアウォールの確認**:
- macOSの場合、システム環境設定 > セキュリティとプライバシー > ファイアウォール
- 必要に応じてXAMPPへのアクセスを許可

## 📝 開発時のヒント

### デバッグ用の設定

#### JavaScript デバッグ
```javascript
// デバッグモードの有効化（開発時のみ）
// order.js, admin.js の先頭に追加
const DEBUG = true;
if (DEBUG) console.log('Debug mode enabled');
```

#### PHP デバッグ
```php
// config/config.php に追加（開発時のみ）
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
```

### 便利なツール

#### ブラウザ拡張機能
- **Web Developer**: HTML/CSSの検証
- **JSON Formatter**: API レスポンスの確認
- **QR Code Generator**: テスト用QRコード生成

#### 開発者ツールの活用
1. **Elements**: HTMLとCSSの確認・編集
2. **Console**: JavaScriptエラーの確認
3. **Network**: API通信の確認
4. **Application**: PWA機能の確認

これでXAMPP環境でのモバイルオーダーシステムのテストが完了です！

問題が発生した場合は、ブラウザの開発者ツールとPHPエラーログを確認してください。