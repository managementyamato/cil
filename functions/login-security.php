<?php
/**
 * ログインセキュリティ機能
 *
 * - ログイン通知（新規IP検知）
 * - セッション管理
 * - ログイン履歴
 */

/**
 * ログイン履歴を記録し、新規IPの場合は通知
 */
function recordLoginAndNotify($userId, $email, $name) {
    $ip = getClientIp();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // ログイン履歴を取得
    $history = getLoginHistory($userId);

    // このIPが過去にログインしたことがあるか
    $knownIps = array_column($history, 'ip');
    $isNewIp = !in_array($ip, $knownIps);

    // ログイン記録を追加
    $loginRecord = [
        'timestamp' => date('c'),
        'ip' => $ip,
        'user_agent' => $userAgent,
        'location' => getIpLocation($ip),
        'session_id' => session_id(),
    ];

    // 履歴に追加（最新100件まで）
    array_unshift($history, $loginRecord);
    $history = array_slice($history, 0, 100);
    saveLoginHistory($userId, $history);

    // 新規IPの場合は通知
    if ($isNewIp && count($knownIps) > 0) {
        sendLoginNotification($email, $name, $loginRecord);
    }

    return $isNewIp;
}

// getClientIp() は functions/security.php で定義済み
// 重複定義を避けるため、ここでは定義しない

/**
 * IPアドレスから大まかな位置情報を取得（簡易版）
 */
function getIpLocation($ip) {
    // プライベートIPの場合
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'ローカルネットワーク';
    }

    // 外部APIを使う場合はここで実装（今回は簡易版）
    return '不明';
}

/**
 * ログイン履歴を取得
 */
function getLoginHistory($userId) {
    $file = getLoginHistoryFile($userId);
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

/**
 * ログイン履歴を保存
 */
function saveLoginHistory($userId, $history) {
    $file = getLoginHistoryFile($userId);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * ログイン履歴ファイルパスを取得
 */
function getLoginHistoryFile($userId) {
    return dirname(__DIR__) . '/data/login-history/' . md5($userId) . '.json';
}

/**
 * ログイン失敗を記録し、管理者に通知
 * @param string $attemptedEmail 試行されたメールアドレス
 * @param string $reason 失敗理由 ('not_found', 'retired', 'domain_blocked')
 */
function recordFailedLoginAndNotify($attemptedEmail, $reason) {
    $ip = getClientIp();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // 失敗記録を保存
    $failedRecord = [
        'timestamp' => date('c'),
        'attempted_email' => $attemptedEmail,
        'reason' => $reason,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'device' => parseUserAgent($userAgent),
    ];

    saveFailedLoginAttempt($failedRecord);

    // 通知設定を確認
    $configFile = dirname(__DIR__) . '/config/security-config.json';
    $notifyAdmins = true;
    $adminEmails = [];

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        $notifyAdmins = $config['failed_login_notification']['enabled'] ?? true;
        $adminEmails = $config['failed_login_notification']['admin_emails'] ?? [];
    }

    if (!$notifyAdmins || empty($adminEmails)) {
        return;
    }

    // 失敗理由のテキスト
    $reasonText = match($reason) {
        'not_found' => '存在しないアカウント',
        'retired' => '退職者アカウント',
        'domain_blocked' => '許可されていないドメイン',
        default => '不明な理由',
    };

    $subject = '【YA管理】ログイン失敗の通知 - ' . $reasonText;

    $body = <<<EOT
<html>
<body style="font-family: sans-serif;">
<h2 style="color: #dc2626;">ログイン失敗の通知</h2>
<p>登録されていないアカウントでログインが試行されました。</p>

<table style="border-collapse: collapse; margin: 20px 0;">
<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5; width: 150px;">日時</td>
    <td style="padding: 8px; border: 1px solid #ddd;">{$failedRecord['timestamp']}</td></tr>
<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;">試行されたメール</td>
    <td style="padding: 8px; border: 1px solid #ddd;">{$attemptedEmail}</td></tr>
<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;">失敗理由</td>
    <td style="padding: 8px; border: 1px solid #ddd; color: #dc2626; font-weight: bold;">{$reasonText}</td></tr>
<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;">IPアドレス</td>
    <td style="padding: 8px; border: 1px solid #ddd;">{$ip}</td></tr>
<tr><td style="padding: 8px; border: 1px solid #ddd; background: #f5f5f5;">デバイス</td>
    <td style="padding: 8px; border: 1px solid #ddd;">{$failedRecord['device']}</td></tr>
</table>

<p style="color: #666; font-size: 0.9em;">
不審なアクセスが続く場合は、IPアドレスのブロック等の対策をご検討ください。
</p>

<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
<p style="color: #999; font-size: 0.8em;">YA管理システム</p>
</body>
</html>
EOT;

    // メール送信
    require_once dirname(__DIR__) . '/functions/notification-functions.php';
    foreach ($adminEmails as $adminEmail) {
        sendNotificationEmail($adminEmail, $subject, $body);
    }
}

/**
 * 失敗したログイン試行を保存
 */
function saveFailedLoginAttempt($record) {
    $file = dirname(__DIR__) . '/data/failed-logins.json';
    $dir = dirname($file);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
    }

    // 新しい記録を追加（最新500件まで）
    array_unshift($attempts, $record);
    $attempts = array_slice($attempts, 0, 500);

    @file_put_contents($file, json_encode($attempts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * 失敗したログイン試行履歴を取得
 */
function getFailedLoginAttempts($limit = 50) {
    $file = dirname(__DIR__) . '/data/failed-logins.json';
    if (!file_exists($file)) {
        return [];
    }
    $attempts = json_decode(file_get_contents($file), true) ?: [];
    return array_slice($attempts, 0, $limit);
}

/**
 * ログイン通知メールを送信
 */
function sendLoginNotification($email, $name, $loginRecord) {
    // 通知設定を確認
    $configFile = dirname(__DIR__) . '/config/security-config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (!($config['login_notification']['enabled'] ?? true)) {
            return; // 通知無効
        }
    }

    $subject = '【YA管理】新しい端末からのログインがありました';

    // デバイス情報を解析
    $device = parseUserAgent($loginRecord['user_agent']);
    $timestamp = $loginRecord['timestamp'];
    $ip = $loginRecord['ip'];
    $location = $loginRecord['location'] ?? '不明';

    $body = <<<EOT
<html>
<body style="font-family: sans-serif; line-height: 1.6;">
<h2 style="color: #f59e0b;">新しい端末からのログイン通知</h2>

<p>{$name} 様</p>

<p>いつもと異なるIPアドレスからログインがありました。</p>

<table style="border-collapse: collapse; margin: 20px 0; width: 100%; max-width: 500px;">
<tr>
    <td style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; width: 120px; font-weight: bold;">日時</td>
    <td style="padding: 10px; border: 1px solid #ddd;">{$timestamp}</td>
</tr>
<tr>
    <td style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: bold;">IPアドレス</td>
    <td style="padding: 10px; border: 1px solid #ddd;">{$ip}</td>
</tr>
<tr>
    <td style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: bold;">場所</td>
    <td style="padding: 10px; border: 1px solid #ddd;">{$location}</td>
</tr>
<tr>
    <td style="padding: 10px; border: 1px solid #ddd; background: #f5f5f5; font-weight: bold;">デバイス</td>
    <td style="padding: 10px; border: 1px solid #ddd;">{$device}</td>
</tr>
</table>

<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
    <strong>心当たりがない場合</strong><br>
    すぐに管理者に連絡し、パスワードの変更をご検討ください。
</div>

<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
<p style="color: #999; font-size: 0.85em;">
このメールはYA管理システムから自動送信されています。<br>
セッション管理ページから他の端末をログアウトさせることができます。
</p>
</body>
</html>
EOT;

    // メール送信
    require_once dirname(__DIR__) . '/functions/notification-functions.php';
    if (function_exists('sendNotificationEmail')) {
        @sendNotificationEmail($email, $subject, $body);
    }
}

// ==================== セッション管理 ====================

/**
 * アクティブセッション一覧を取得
 */
function getActiveSessions($userId) {
    $file = getSessionListFile($userId);
    if (!file_exists($file)) {
        return [];
    }
    $sessions = json_decode(file_get_contents($file), true) ?: [];

    // 期限切れセッションを除外
    $timeout = 8 * 60 * 60; // 8時間
    $now = time();
    $sessions = array_filter($sessions, function($s) use ($now, $timeout) {
        return ($now - strtotime($s['last_activity'])) < $timeout;
    });

    return $sessions;
}

/**
 * セッションを登録
 */
function registerSession($userId) {
    $sessions = getActiveSessions($userId);

    $sessionId = session_id();
    $ip = getClientIp();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // 既存のセッションを更新または追加
    $found = false;
    foreach ($sessions as &$s) {
        if ($s['session_id'] === $sessionId) {
            $s['last_activity'] = date('c');
            $s['ip'] = $ip;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $sessions[] = [
            'session_id' => $sessionId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => date('c'),
            'last_activity' => date('c'),
            'device' => parseUserAgent($userAgent),
        ];
    }

    saveSessionList($userId, $sessions);
}

/**
 * セッションを削除（ログアウト）
 */
function removeSession($userId, $sessionId = null) {
    if ($sessionId === null) {
        $sessionId = session_id();
    }

    $sessions = getActiveSessions($userId);
    $sessions = array_filter($sessions, function($s) use ($sessionId) {
        return $s['session_id'] !== $sessionId;
    });

    saveSessionList($userId, array_values($sessions));
}

/**
 * 他のセッションをすべて削除
 */
function removeOtherSessions($userId) {
    $currentSessionId = session_id();
    $sessions = getActiveSessions($userId);

    $sessions = array_filter($sessions, function($s) use ($currentSessionId) {
        return $s['session_id'] === $currentSessionId;
    });

    saveSessionList($userId, array_values($sessions));

    return count($sessions);
}

/**
 * 特定のセッションを強制ログアウト
 */
function forceLogoutSession($userId, $targetSessionId) {
    // セッションリストから削除
    removeSession($userId, $targetSessionId);

    // PHPセッションファイルを削除（可能な場合）
    $sessionPath = session_save_path();
    if ($sessionPath) {
        $sessionFile = $sessionPath . '/sess_' . $targetSessionId;
        if (file_exists($sessionFile)) {
            @unlink($sessionFile);
        }
    }
}

/**
 * セッションリストファイルパスを取得
 */
function getSessionListFile($userId) {
    return dirname(__DIR__) . '/data/sessions/' . md5($userId) . '.json';
}

/**
 * セッションリストを保存
 */
function saveSessionList($userId, $sessions) {
    $file = getSessionListFile($userId);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * User-Agentからデバイス情報を解析
 */
function parseUserAgent($ua) {
    $device = 'Unknown';

    // OS検出
    if (preg_match('/Windows/i', $ua)) {
        $device = 'Windows PC';
    } elseif (preg_match('/Macintosh/i', $ua)) {
        $device = 'Mac';
    } elseif (preg_match('/iPhone/i', $ua)) {
        $device = 'iPhone';
    } elseif (preg_match('/iPad/i', $ua)) {
        $device = 'iPad';
    } elseif (preg_match('/Android/i', $ua)) {
        if (preg_match('/Mobile/i', $ua)) {
            $device = 'Android Phone';
        } else {
            $device = 'Android Tablet';
        }
    } elseif (preg_match('/Linux/i', $ua)) {
        $device = 'Linux';
    }

    // ブラウザ検出
    $browser = 'Unknown';
    if (preg_match('/Chrome/i', $ua) && !preg_match('/Edge/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/i', $ua)) {
        $browser = 'Edge';
    }

    return $device . ' / ' . $browser;
}

// ==================== データ変更履歴（Undo用） ====================

/**
 * データ変更を記録（Undo用）
 */
function recordDataChange($entityType, $entityId, $action, $oldData, $newData, $userId = null) {
    $file = dirname(__DIR__) . '/data/change-history.json';

    $history = [];
    if (file_exists($file)) {
        $history = json_decode(file_get_contents($file), true) ?: [];
    }

    // 新しい変更を追加
    array_unshift($history, [
        'id' => uniqid('change_'),
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'action' => $action,
        'old_data' => $oldData,
        'new_data' => $newData,
        'user_id' => $userId ?? ($_SESSION['user_email'] ?? 'system'),
        'user_name' => $_SESSION['user_name'] ?? 'System',
        'timestamp' => date('c'),
        'ip' => getClientIp(),
    ]);

    // 最新1000件まで保持
    $history = array_slice($history, 0, 1000);

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE), LOCK_EX);

    return $history[0]['id'];
}

/**
 * 変更履歴を取得
 */
function getChangeHistory($limit = 50, $entityType = null) {
    $file = dirname(__DIR__) . '/data/change-history.json';
    if (!file_exists($file)) {
        return [];
    }

    $history = json_decode(file_get_contents($file), true) ?: [];

    if ($entityType) {
        $history = array_filter($history, function($h) use ($entityType) {
            return $h['entity_type'] === $entityType;
        });
    }

    return array_slice($history, 0, $limit);
}

/**
 * 変更を取り消し（Undo）
 */
function undoChange($changeId) {
    $file = dirname(__DIR__) . '/data/change-history.json';
    if (!file_exists($file)) {
        return ['success' => false, 'error' => '履歴がありません'];
    }

    $history = json_decode(file_get_contents($file), true) ?: [];

    // 対象の変更を検索
    $change = null;
    foreach ($history as $h) {
        if ($h['id'] === $changeId) {
            $change = $h;
            break;
        }
    }

    if (!$change) {
        return ['success' => false, 'error' => '変更が見つかりません'];
    }

    // 元に戻す処理
    $data = getData();
    $entityType = $change['entity_type'];
    $entityId = $change['entity_id'];
    $action = $change['action'];
    $oldData = $change['old_data'];

    switch ($action) {
        case 'create':
            // 作成した場合は削除
            if (isset($data[$entityType])) {
                $data[$entityType] = array_filter($data[$entityType], function($item) use ($entityId) {
                    return ($item['id'] ?? null) !== $entityId;
                });
                $data[$entityType] = array_values($data[$entityType]);
            }
            break;

        case 'update':
            // 更新した場合は元のデータに戻す
            if (isset($data[$entityType]) && $oldData) {
                foreach ($data[$entityType] as &$item) {
                    if (($item['id'] ?? null) === $entityId) {
                        $item = $oldData;
                        break;
                    }
                }
            }
            break;

        case 'delete':
            // 削除した場合は復元
            if ($oldData) {
                if (!isset($data[$entityType])) {
                    $data[$entityType] = [];
                }
                $data[$entityType][] = $oldData;
            }
            break;

        default:
            return ['success' => false, 'error' => '不明な操作タイプです'];
    }

    try {
        saveData($data);

        // 履歴から削除
        $history = array_filter($history, function($h) use ($changeId) {
            return $h['id'] !== $changeId;
        });
        file_put_contents($file, json_encode(array_values($history), JSON_UNESCAPED_UNICODE), LOCK_EX);

        return ['success' => true, 'message' => '変更を取り消しました'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
