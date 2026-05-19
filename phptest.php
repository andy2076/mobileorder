<?php
echo "<h1>PHP動作テスト</h1>";
echo "<p>PHP バージョン: " . phpversion() . "</p>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

// データベース接続テスト
try {
    $pdo = new PDO("mysql:host=localhost;dbname=mobileorder", "root", "");
    echo "<p style='color: green;'>✅ データベース接続成功</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ データベース接続エラー: " . $e->getMessage() . "</p>";
    echo "<p>まずphpMyAdminで 'mobileorder' データベースを作成してください</p>";
}

echo "<h2>ファイル構造確認</h2>";
$files = [
    'admin/login.php',
    'public/index.html',
    'api/menu.php',
    'config/database.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file - 存在</p>";
    } else {
        echo "<p style='color: red;'>❌ $file - 見つかりません</p>";
    }
}
?>