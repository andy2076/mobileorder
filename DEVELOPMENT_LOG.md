# machiorder 開発ログ

## プロジェクト概要
- **サービス名**: machiorder（マチオーダー）
- **URL**: https://machiorder.com
- **概要**: マルチテナント対応のモバイルオーダーSaaS
- **技術スタック**: PHP 8.3 / MySQL / Nginx / Vanilla JS（フレームワークなし）
- **VPS**: 160.251.179.150（root）
- **SSL**: Let's Encrypt（自動更新）

---

## URL構成

| 用途 | URL |
|------|-----|
| 客側 注文画面 | `https://machiorder.com/s/{slug}/?table=1` |
| 店舗管理 ダッシュボード | `https://machiorder.com/s/{slug}/admin/dashboard.php` |
| 店舗管理 メニュー管理 | `https://machiorder.com/s/{slug}/admin/menu.php` |
| 店舗管理 売上分析 | `https://machiorder.com/s/{slug}/admin/analytics.php` |
| 店舗登録 | `https://machiorder.com/register/` |
| QRコード生成 | `https://machiorder.com/s/{slug}/qr-generator.php` |

デモ店舗のslugは `default`（サンプル食堂）。

---

## ファイル構成

```
/var/www/html/mobileorder/
├── admin/
│   ├── analytics.php     # 売上分析画面（Chart.js）
│   ├── dashboard.php     # 注文管理カンバン画面
│   ├── login.php         # 管理者ログイン
│   └── menu.php          # メニューCRUD（画像・動画アップロード対応）
├── api/
│   ├── analytics.php     # 売上データAPI
│   ├── auth.php          # ログイン認証API
│   ├── logout.php        # ログアウト
│   ├── menu.php          # メニューCRUD API
│   ├── order.php         # 注文作成API（客側から呼ばれる）
│   ├── orders.php        # 注文一覧取得API（管理側）
│   ├── serve.php         # 品目提供・キャンセルAPI
│   ├── status.php        # 注文ステータス更新API
│   └── upload.php        # 画像・動画アップロードAPI
├── config/
│   ├── config.php        # 定数定義・ヘルパー関数
│   ├── database.php      # DB接続クラス
│   └── store.php         # マルチ店舗解決ロジック
├── css/
│   ├── admin.css         # 管理画面用CSS
│   └── style.css         # 客側CSS
├── images/
│   └── stores/{slug}/menu/  # 店舗別メニュー画像・動画
├── js/
│   └── order.js          # 客側注文SPA
├── public/
│   ├── index.php         # 客側エントリーポイント
│   ├── manifest.json     # PWA manifest
│   ├── manifest.php      # 動的manifest
│   ├── qr-generator.php  # テーブルQRコード生成
│   └── sw.js             # Service Worker
└── register.php          # 新規店舗登録（招待コード制）
```

---

## DB設計

### stores
店舗マスタ。slugでURLルーティング。

### admins
管理者アカウント。store_idで店舗紐付け。

### categories
メニューカテゴリ。store_id別。sort_orderで並び順制御。

### menu_items
メニュー商品。image_urlは画像（JPEG/PNG/GIF/WebP）または動画（MP4/WebM、10秒以内）。

### orders
注文。ステータス遷移: `pending` → `preparing` → `completed` → `paid`（または `cancelled`）。

### order_items
注文明細。品目単位で提供済み（is_served）・キャンセル（is_cancelled / cancelled_qty）を管理。

### invite_codes
店舗登録用の招待コード。

---

## 開発履歴

### Phase 1: 基盤構築
- PHP + MySQL + Nginx でモバイルオーダーの基本機能を構築
- 客側SPA（order.js）: メニュー表示、カート、注文送信
- 管理側: ログイン、メニューCRUD、注文一覧
- PWA対応（manifest.json, Service Worker）

### Phase 2: UI改善
- **no-image.jpg 修正**: SVG data URI → 実JPEG
- **メニューちらつき修正**: 全DOM再描画 → 差分更新に変更
- **ブラウザキャッシュ対応**: nginx 7日キャッシュ + SW cache バージョン管理
- **ヘッダーリンク表示修正**
- **画像URL入力 → ファイルアップロードに変換**
- **レスポンシブグリッドレイアウト**: メニュー表示をグリッド化
- **スティッキーヘッダー + カテゴリボタン**: スクロール時もカテゴリ選択可能に

### Phase 3: マルチ店舗対応
- `/s/{slug}/` 形式のURLルーティング（nginx rewrite）
- store_idによるデータ分離
- 全テーブルにstore_id追加
- 招待コード制の店舗登録機能
- 店舗別画像ディレクトリ

### Phase 4: 動画対応
- メニュー商品に動画（MP4/WebM）アップロード可能に
- サーバー側: ffprobeで動画の長さをバリデーション（10秒以内）
- クライアント側: `<video autoplay muted loop playsinline>` で再生
- アップロード上限: PHP 30MB / nginx 30MB

### Phase 5: ダッシュボード カンバン化
元は時系列リスト表示だった注文管理画面を、iPad/タブレット向けのカンバンボードに全面書き換え。

#### カラム構成
1. **受付**（pending）: 新着注文。「調理開始」ボタン、キャンセルリンク
2. **調理中**（preparing）: 品目チェックリスト。タップで提供済みマーク
3. **会計**（completed）: テーブル別にグルーピング。合計金額表示、「会計済み」ボタン

#### 機能
- **通知音**: Web Audio API でビープ音。新着注文で鳴動（トグルON/OFF）
- **緊急度表示**: 経過時間に応じてカード背景色が変化（5分→黄色、10分→赤）
- **3秒ポーリング**: 注文をリアルタイム反映
- **新着ハイライト**: 新規注文カードにpulseアニメーション
- **全履歴モーダル**: 過去の注文（completed/cancelled/paid含む）を一覧表示
- **モバイル対応**: 768px未満でタブ切替表示

### Phase 6: 品目単位ステータス管理
注文単位ではなく品目単位で提供状態を追跡。

- `order_items.is_served`: 品目が提供済みか
- 全品提供済み → 自動的に注文ステータスを `completed` に変更
- チェックを外す → `preparing` に戻す

### Phase 7: 品目キャンセル機能
調理中に個別の品目をキャンセル可能に（例: 定食を食べ終わった後にデザートをキャンセル）。

- `order_items.is_cancelled`: 品目が全キャンセルか
- `order_items.cancelled_qty`: 部分キャンセル数（例: ラーメン×2のうち1杯だけキャンセル）
- `serve.php` のアクション:
  - `toggle_serve`: 提供済みトグル
  - `cancel`: 品目の全キャンセル/解除
  - `cancel_one`: 1個ずつキャンセル（quantity > 1 の場合）
- キャンセル時は注文合計金額を自動再計算
- 全品キャンセル → 注文自体を `cancelled` に
- 会計カラムでキャンセル品は取り消し線で表示、金額はキャンセル分を除外

### Phase 8: 売上分析
サブスク価値向上のため、売上データの可視化機能を追加。

- **期間選択**: 今日 / 7日間 / 30日間 / カスタム日付
- **サマリー**: 売上、注文数、客単価、販売個数、キャンセル数
- **グラフ（Chart.js CDN）**:
  - 日別売上推移（棒グラフ + 注文数折れ線）
  - 時間帯別注文数
  - 人気メニュー TOP10（ランキングテーブル）
  - 曜日別売上
  - テーブル別売上

---

## 注文フロー

```
[客] スマホでQR読み取り → メニュー選択 → 注文送信
         ↓
[店] 受付カラムに表示 → 「調理開始」タップ
         ↓
[店] 調理中カラム → 品目ごとにチェック（提供済み）
     ※ 未提供品は個別キャンセル可能（−1 / 全取消）
         ↓
[店] 全品提供済み → 自動的に会計カラムへ
         ↓
[店] 会計カラム（テーブル別合計）→ 「会計済み」タップ → 完了
```

---

## インフラ

| 項目 | 設定 |
|------|------|
| OS | Ubuntu (Debian系) |
| Web | Nginx |
| PHP | 8.3-fpm |
| DB | MySQL |
| SSL | Let's Encrypt (certbot) |
| upload上限 | PHP: 30MB / Nginx: 30MB |
| ffmpeg | インストール済み（動画バリデーション用） |

---

## 開発ワークフロー

```
VPSで直接編集 → rsync でローカルに同期 → git push
```

ローカル: `/Users/lii/Library/CloudStorage/GoogleDrive-andy.railway@gmail.com/マイドライブ/machiorder/`
VPS: `/var/www/html/mobileorder/`

---

## 2026-05-21: QR生成バグ修正 & ダッシュボードUI改善

### 問題
`/mobileorder/public/qr-generator.html`（静的HTML版）で生成したQRコードのURLが誤っていた。

- **原因**: Phase 3（マルチテナント化）以前の古いコードが残っており、`${baseUrl}/index.html?table=${i}` を生成していた
- **正しいURL**: `${baseUrl}/?table=${i}`（index.phpにルーティングされる）

### 修正内容

1. **qr-generator.html**: URL生成を `/?table=` に修正、ラベル・プレースホルダーを現行のマルチテナントURL形式に更新
2. **dashboard.php**: ヘッダーメニューに「QR生成」リンクを追加（`qr-generator.php` へ遷移）

### Git同期
- `.git`がVPSから消失していたため、GitHubからcloneして復元
- Phase 3〜8の全差分 + 今回の修正をまとめてcommit & push
- リポジトリ: `github.com/andy2076/mobileorder`

---

## 今後の検討事項
- 店舗登録フローの改善（現在は招待コード制）
- PWAプッシュ通知（新規注文の通知を店舗側に）
- 決済連携
- 多言語対応
- 注文履歴の客側表示
