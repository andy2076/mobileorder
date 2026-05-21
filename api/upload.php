<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
if (!$db) send_json_response(['success' => false, 'error' => 'データベース接続エラー'], 500);

$store = resolve_store($db);

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'error' => 'POSTのみ対応'], 405);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['image']['error'] ?? 'ファイルなし';
    send_json_response(['success' => false, 'error' => 'ファイルアップロードに失敗しました (code: ' . $err . ')'], 400);
}

$file = $_FILES['image'];

if ($file['size'] > MAX_UPLOAD_SIZE) {
    send_json_response(['success' => false, 'error' => 'ファイルサイズが大きすぎます（最大30MB）'], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!in_array($mime, ALLOWED_MEDIA_TYPES)) {
    send_json_response(['success' => false, 'error' => '対応していない形式です（JPEG/PNG/GIF/WebP/MP4/WebM）'], 400);
}

$is_video = in_array($mime, ALLOWED_VIDEO_TYPES);

// 動画の場合: ffprobe で長さチェック
if ($is_video) {
    $tmp = escapeshellarg($file['tmp_name']);
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $tmp 2>&1";
    $duration = floatval(trim(shell_exec($cmd)));
    if ($duration <= 0) {
        send_json_response(['success' => false, 'error' => '動画ファイルを読み取れませんでした'], 400);
    }
    if ($duration > MAX_VIDEO_DURATION) {
        send_json_response(['success' => false, 'error' => '動画は' . MAX_VIDEO_DURATION . '秒以内にしてください（現在: ' . round($duration, 1) . '秒）'], 400);
    }
}

$ext_map = [
    'image/jpeg' => '.jpg', 'image/png' => '.png',
    'image/gif' => '.gif', 'image/webp' => '.webp',
    'video/mp4' => '.mp4', 'video/webm' => '.webm'
];
$ext = $ext_map[$mime] ?? '.jpg';

$prefix = $is_video ? 'video_' : 'menu_';
$filename = uniqid($prefix, true) . $ext;
$dest = get_upload_path() . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    send_json_response(['success' => false, 'error' => 'ファイルの保存に失敗しました'], 500);
}

chmod($dest, 0644);

$url = get_upload_url() . $filename;
send_json_response(['success' => true, 'url' => $url]);
