<?php
/**
 * 写真勤怠管理データベース初期化
 */

require_once __DIR__ . '/../config/config.php';

// データベース接続
$db = getDatabase();

// 写真アップロードテーブルの作成
$db->exec("
CREATE TABLE IF NOT EXISTS photo_attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    upload_date DATE NOT NULL,
    upload_type TEXT NOT NULL CHECK(upload_type IN ('start', 'end')),
    photo_path TEXT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id, upload_date, upload_type)
)
");

// インデックス作成
$db->exec("CREATE INDEX IF NOT EXISTS idx_photo_attendance_date ON photo_attendance(upload_date)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_photo_attendance_employee ON photo_attendance(employee_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_photo_attendance_type ON photo_attendance(upload_type)");

echo "写真勤怠管理テーブルを作成しました。\n";
