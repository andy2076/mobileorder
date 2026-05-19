-- モバイルオーダーシステム データベーススキーマ
CREATE DATABASE IF NOT EXISTS mobileorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mobileorder;

-- 管理者テーブル
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    shop_name VARCHAR(100) DEFAULT 'モバイルオーダー',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- メニューカテゴリテーブル
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- メニューテーブル
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    image_url VARCHAR(255),
    is_available TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 注文テーブル
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT NOT NULL,
    status ENUM('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
    total_amount INT NOT NULL DEFAULT 0,
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 注文明細テーブル
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    item_price INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

-- 初期データ: 管理者 (パスワード: admin123)
INSERT INTO admins (username, password, shop_name) VALUES
('admin', '$2y$10$3s3RB.gWYvKEgv.OcieTPOUy7eMoMOkNoSzQwKXSy6.WJx8.RKwxy', 'サンプル食堂');

-- 初期データ: カテゴリ
INSERT INTO categories (name, sort_order) VALUES
('ランチ', 1),
('ドリンク', 2),
('デザート', 3);

-- 初期データ: メニュー
INSERT INTO menu_items (category_id, name, description, price, sort_order) VALUES
(1, '日替わり定食', '本日のおすすめ定食です', 850, 1),
(1, 'ラーメン', '醤油ベースのあっさり味', 750, 2),
(1, 'チャーハン', '卵とネギのシンプルチャーハン', 700, 3),
(2, 'コーラ', 'Mサイズ', 300, 1),
(2, 'ウーロン茶', '温・冷お選びいただけます', 280, 2),
(2, 'コーヒー', 'ホット・アイスお選びいただけます', 350, 3),
(3, 'プリン', '手作りなめらかプリン', 380, 1),
(3, 'アイスクリーム', 'バニラ・チョコ・抹茶', 350, 2);
