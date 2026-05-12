<?php
/**
 * 週報確認 メールアクション（ログイン不要・トークン認証）
 *
 * GET  ?token=XXX  → 確認フォーム（コメント・メンション可）を表示
 * POST ?token=XXX  → コメント保存 + メンション通知 + 確認処理 + 結果表示
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/notification-functions.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// ─── トークン検証 ──────────────────────────────────────
function findReportByToken($token) {
    $data = getData();
    foreach ($data['weekly_reports'] ?? [] as $r) {
        if (($r['confirm_token'] ?? '') === $token && empty($r['deleted_at'])) {
            return $r;
        }
    }
    return null;
}

// 社員一覧（メンション候補）取得 — 削除済みを除く
function getMentionCandidates() {
    $data = getData();
    $list = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        $name = trim($emp['name'] ?? '');
        if ($name === '') continue;
        $email = $emp['email'] ?? '';
        if (is_string($email) && str_starts_with($email, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $email = decryptValue($email); } catch (Exception $e) { $email = ''; }
        }
        $list[] = ['name' => $name, 'email' => $email];
    }
    usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $list;
}

// 管理者一覧（確認メールが届くメンバー）
function getAdminConfirmerCandidates() {
    $data = getData();
    $list = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        if (($emp['role'] ?? '') !== 'admin') continue;
        $name = trim($emp['name'] ?? '');
        if ($name === '') continue;
        $list[] = $name;
    }
    sort($list);
    return $list;
}

// メールアドレスから社員名を逆引き（暗号化されたメアドにも対応）
function findEmployeeNameByEmail(string $email): string {
    if ($email === '') return '';
    $email = strtolower(trim($email));
    $data = getData();
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        $rawEmail = $emp['email'] ?? '';
        if (is_string($rawEmail) && str_starts_with($rawEmail, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $rawEmail = decryptValue($rawEmail); } catch (Exception $e) { continue; }
        }
        if (strtolower(trim((string)$rawEmail)) === $email) {
            return trim($emp['name'] ?? '');
        }
    }
    return '';
}

function renderPage($title, $content, $isError = false) {
    $color = $isError ? '#c62828' : '#27ae60';
    // <style> ブロックが拡張機能等で除去されるケースに備え、重要要素には body の inline style も併用する
    $bodyStyle  = 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Meiryo,sans-serif;background:#f5f7fa;margin:0;padding:40px 16px;';
    $cardStyle  = 'max-width:680px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.10);padding:32px;box-sizing:border-box;';
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title) . ' - Yamato Gear</title>'
        . '<style>'
        . 'body{' . $bodyStyle . '}'
        . '.card{' . $cardStyle . '}'
        . 'h2{margin-top:0;color:' . $color . ';}'
        . '.info-table{width:100%;border-collapse:collapse;margin:1rem 0;}'
        . '.info-table th,.info-table td{padding:8px 12px;border:1px solid #e0e0e0;text-align:left;font-size:14px;vertical-align:top;}'
        . '.info-table th{background:#f5f5f5;width:130px;}'
        . '.btn{display:inline-block;padding:11px 28px;border-radius:6px;font-size:15px;font-weight:600;border:none;cursor:pointer;text-decoration:none;}'
        . '.btn-confirm{background:#27ae60;color:#fff;}'
        . '.btn-secondary{background:#e0e0e0;color:#333;}'
        . '.section-content{font-size:14px;line-height:1.7;color:#333;}'
        . '.section-content *{background-color:transparent !important;color:inherit !important;font-family:inherit !important;}'
        . '.section-content a{color:#2980b9 !important;text-decoration:underline;}'
        . '.section-content img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;display:block;}'
        . '.field-label{font-size:14px;font-weight:600;color:#444;margin:1.2rem 0 0.4rem;}'
        . '.comment-input{width:100%;min-height:90px;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;font-family:inherit;line-height:1.5;box-sizing:border-box;resize:vertical;}'
        . '.mention-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;padding:10px;background:#fafbff;border:1px solid #e5e7eb;border-radius:8px;}'
        . '.mention-chip{display:inline-block;background:#eef2ff;color:#2563eb;border:1px solid #c7d2fe;padding:5px 12px;border-radius:16px;font-size:13px;cursor:pointer;user-select:none;line-height:1.4;margin:2px;}'
        . '.mention-chip:hover{background:#dbeafe;border-color:#93c5fd;}'
        . '.mention-chip-poster{background:#dcfce7;color:#15803d;border-color:#86efac;font-weight:600;}'
        . '.hint{font-size:12px;color:#888;margin-top:4px;}'
        . '.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:1.5rem;}'
        . '</style></head>'
        . '<body style="' . $bodyStyle . '">'
        . '<div class="card" style="' . $cardStyle . '">'
        . $content
        . '</div></body></html>';
    exit;
}

// バリデーション
if (empty($token)) {
    renderPage('無効なリンク', '<h2>無効なリンクです</h2><p>URLが正しくないか、リンクが古くなっています。</p>', true);
}

$report = findReportByToken($token);

if (!$report) {
    renderPage('リンクエラー', '<h2>週報が見つかりません</h2><p>リンクが無効か、週報が削除されています。</p>', true);
}

// 既に確認済み
if (!empty($report['confirmed_at'])) {
    $confirmerName = htmlspecialchars($report['confirmed_by_name'] ?? '');
    $confirmedAt   = htmlspecialchars($report['confirmed_at'] ?? '');
    renderPage('確認済み',
        '<h2>この週報は既に確認済みです</h2>'
        . '<table class="info-table">'
        . '<tr><th>確認者</th><td>' . $confirmerName . '</td></tr>'
        . '<tr><th>確認日時</th><td>' . $confirmedAt . '</td></tr>'
        . '</table>'
    );
}

$ts = function($s) { return htmlspecialchars($s ?? ''); };
$userName  = $ts($report['user_name'] ?? $report['user_email'] ?? '');
$weekStart = $ts($report['week_start'] ?? '');

// セクション内容をHTMLで表示（URLをリンク化）
$sectionLabels = [
    'sec_role'        => '今期の役割',
    'sec_report'      => '今週の報告',
    'sec_issues'      => '現在抱えている課題',
    'sec_next_goals'  => '次週目標・計画',
    'sec_second_area' => 'いま思いつく第二領域活動',
    'sec_misc'        => '報告・連絡・相談事項',
];

function linkifyText($text) {
    // 1. 危険なタグ・属性をブラックリストで除去（外側ページのレイアウトを壊さないため）
    // table/tr/td/div/style/script/iframe/form/html/body/head などは絶対通さない
    $allowedTags = '<br><p><span><strong><b><em><i><u><a><img><ul><ol><li><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowedTags);

    // 2. 残ったタグの装飾系属性（style/class/bgcolor等）を a/img 以外で除去
    $text = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function($m) {
        $tag = strtolower($m[1]);
        $attrs = $m[2];
        if ($tag === 'a' || $tag === 'img') return '<' . $m[1] . $attrs . '>';
        $attrs = preg_replace('/\s(style|class|bgcolor|color|face|size|width|height|align|valign)=["\'][^"\']*["\']/i', '', $attrs);
        $attrs = preg_replace('/\s(style|class|bgcolor|color|face|size|width|height|align|valign)=[^\s>]+/i', '', $attrs);
        return '<' . $m[1] . $attrs . '>';
    }, $text);

    // 3. DOMDocument で読み込んでタグの整合性を保証（閉じタグ漏れを自動補完）
    if (class_exists('DOMDocument') && trim(strip_tags($text)) !== '') {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        // UTF-8 のまま読み込ませる
        $wrapped = '<?xml encoding="UTF-8"?><div id="__wrap__">' . $text . '</div>';
        @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $wrapNode = $dom->getElementById('__wrap__');
        if ($wrapNode) {
            $rebuilt = '';
            foreach ($wrapNode->childNodes as $child) {
                $rebuilt .= $dom->saveHTML($child);
            }
            if ($rebuilt !== '') $text = $rebuilt;
        }
    }

    // 4. URLをaタグ化
    return preg_replace_callback(
        '/(<[^>]+>)|(https?:\/\/[^\s<>"\']+)/i',
        function($m) {
            if (!empty($m[1])) return $m[1];
            $url = $m[2];
            return '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color:#2980b9;">' . htmlspecialchars($url) . '</a>';
        },
        $text
    );
}

// インライン style 用ヘルパー（拡張機能で <style> ブロックが除去されてもレイアウトを保つため併用）
$thStyle = 'padding:8px 12px;border:1px solid #e0e0e0;text-align:left;font-size:14px;background:#f5f5f5;width:130px;vertical-align:top;';
$tdStyle = 'padding:8px 12px;border:1px solid #e0e0e0;text-align:left;font-size:14px;vertical-align:top;';
$tableStyle = 'width:100%;border-collapse:collapse;margin:1rem 0;';

$sectionRows = '';
foreach ($sectionLabels as $key => $label) {
    $content = $report[$key] ?? '';
    $hasMedia = (stripos($content, '<img') !== false || stripos($content, '<a ') !== false);
    if (empty(trim(strip_tags($content))) && !$hasMedia) continue;
    $content = linkifyText($content);
    $sectionRows .= '<tr><th style="' . $thStyle . '">' . $ts($label) . '</th>'
                  . '<td class="section-content" style="' . $tdStyle . 'line-height:1.7;color:#333;">' . $content . '</td></tr>';
}

// ─── GET: 確認フォーム表示 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $candidates = getMentionCandidates();
    $posterName = trim($report['user_name'] ?? '');

    // メンション候補チップ（投稿者を最上位＋強調）
    $chipBaseStyle = 'display:inline-block;background:#eef2ff;color:#2563eb;border:1px solid #c7d2fe;padding:5px 12px;border-radius:16px;font-size:13px;cursor:pointer;user-select:none;line-height:1.4;margin:2px;';
    $chipPosterStyle = 'display:inline-block;background:#dcfce7;color:#15803d;border:1px solid #86efac;padding:5px 12px;border-radius:16px;font-size:13px;font-weight:600;cursor:pointer;user-select:none;line-height:1.4;margin:2px;';
    $chipsHtml = '';
    $rendered = [];
    if ($posterName !== '') {
        $chipsHtml .= '<span class="mention-chip mention-chip-poster" data-name="' . $ts($posterName) . '" style="' . $chipPosterStyle . '">@' . $ts($posterName) . ' (投稿者)</span>';
        $rendered[] = $posterName;
    }
    foreach ($candidates as $c) {
        if (in_array($c['name'], $rendered, true)) continue;
        $chipsHtml .= '<span class="mention-chip" data-name="' . $ts($c['name']) . '" style="' . $chipBaseStyle . '">@' . $ts($c['name']) . '</span>';
    }

    // 確認者氏名 SELECT（管理者リスト + その他）
    $admins = getAdminConfirmerCandidates();
    // メールURLの ?u= パラメータから受信者を逆引きして事前選択
    $recipientEmail = trim($_GET['u'] ?? '');
    $autoDetectedName = $recipientEmail !== '' ? findEmployeeNameByEmail($recipientEmail) : '';

    $confirmerOpts = '<option value="">— 選択してください —</option>';
    foreach ($admins as $n) {
        $sel = ($n === $autoDetectedName) ? ' selected' : '';
        $confirmerOpts .= '<option value="' . $ts($n) . '"' . $sel . '>' . $ts($n) . '</option>';
    }
    $confirmerOpts .= '<option value="__other__">その他（直接入力）</option>';
    $autoNote = '';
    if ($autoDetectedName !== '') {
        $autoNote = '<div class="hint" style="color:#16a34a;">✓ メール受信者として「<strong>' . $ts($autoDetectedName) . '</strong>」を自動選択しました（必要なら変更してください）。</div>';
    } elseif ($recipientEmail !== '') {
        $autoNote = '<div class="hint" style="color:#c62828;">※ メールアドレス「' . $ts($recipientEmail) . '」に該当する社員が見つかりませんでした。手動で選択してください。</div>';
    } else {
        $autoNote = '<div class="hint">あなたが誰かを識別するために、必ず氏名を選択（または直接入力）してください。</div>';
    }
    $autoValue = $ts($autoDetectedName);
    // インライン style 定義（拡張機能で <style> ブロック削除されてもレイアウト維持）
    $fieldLabelStyle = 'font-size:14px;font-weight:600;color:#444;margin:1.2rem 0 0.4rem;display:block;';
    $inputBaseStyle  = 'width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:14px;font-family:inherit;line-height:1.5;box-sizing:border-box;';
    $textareaStyle   = $inputBaseStyle . 'min-height:90px;resize:vertical;';
    $hintStyle       = 'font-size:12px;color:#888;margin-top:4px;';
    $chipsBoxStyle   = 'display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;padding:10px;background:#fafbff;border:1px solid #e5e7eb;border-radius:8px;';
    $btnConfirmStyle = 'display:inline-block;padding:11px 28px;border-radius:6px;font-size:15px;font-weight:600;border:none;cursor:pointer;background:#27ae60;color:#fff;text-decoration:none;';

    $confirmerSelectHtml = '<select name="confirmer_select" id="confirmer_select" class="comment-input" style="' . $inputBaseStyle . 'background:#fff;">' . $confirmerOpts . '</select>'
        . '<input type="text" name="confirmer_name" id="confirmer_name_input" class="comment-input" style="' . $inputBaseStyle . 'display:none;margin-top:6px;" placeholder="氏名を入力" value="' . $autoValue . '">'
        . $autoNote;

    $html = '<h2 style="margin-top:0;color:#27ae60;">週報確認</h2>'
        . '<p>' . $userName . ' さんの週報を確認します。コメントとメンションを付けて送信できます。</p>'
        . '<table class="info-table" style="' . $tableStyle . '">'
        . '<tr><th style="' . $thStyle . '">提出者</th><td style="' . $tdStyle . '">' . $userName . '</td></tr>'
        . '<tr><th style="' . $thStyle . '">提出日</th><td style="' . $tdStyle . '">' . $weekStart . '</td></tr>'
        . '<tr><th style="' . $thStyle . '">提出日時</th><td style="' . $tdStyle . '">' . $ts($report['submitted_at'] ?? '') . '</td></tr>'
        . $sectionRows
        . '</table>'
        . '<form method="POST" enctype="multipart/form-data">'
        . '<input type="hidden" name="token" value="' . $ts($token) . '">'
        . '<div class="field-label" style="' . $fieldLabelStyle . '">確認者氏名 <span style="color:#c62828;">*</span></div>'
        . $confirmerSelectHtml
        . '<div class="field-label" style="' . $fieldLabelStyle . '">コメント（任意・メンション可）</div>'
        . '<textarea name="comment" id="comment" class="comment-input" style="' . $textareaStyle . '" placeholder="コメントを入力。「@名前」でメンションすると本人にメール通知されます。"></textarea>'
        . '<div class="hint" style="' . $hintStyle . '">下のチップをクリックすると `@名前 ` が入力欄に挿入されます。</div>'
        . '<div class="mention-chips" style="' . $chipsBoxStyle . '">' . $chipsHtml . '</div>'
        . '<div class="field-label" style="' . $fieldLabelStyle . '">添付ファイル（画像 / PDF / Word / Excel / PowerPoint・複数可）</div>'
        . '<input type="file" name="attachments[]" id="attachments" multiple accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="font-size:14px;">'
        . '<div id="attachPreview" style="margin-top:6px;font-size:13px;color:#555;"></div>'
        . '<div class="hint" style="' . $hintStyle . '">画像10MB / その他25MBまで。投稿後、コメント本文の末尾に添付されます。</div>'
        . '<div class="actions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:1.5rem;">'
        . '<button type="submit" class="btn btn-confirm" style="' . $btnConfirmStyle . '">&#10003; 確認・コメント送信</button>'
        . '</div>'
        . '</form>'
        . '<script>'
        . 'document.querySelectorAll(".mention-chip").forEach(function(c){'
        . '  c.addEventListener("click",function(){'
        . '    var t=document.getElementById("comment");'
        . '    var name=this.dataset.name;'
        . '    var ins="@"+name+" ";'
        . '    var s=t.selectionStart||t.value.length;'
        . '    t.value=t.value.slice(0,s)+ins+t.value.slice(t.selectionEnd||s);'
        . '    t.focus();'
        . '    var p=s+ins.length;t.setSelectionRange(p,p);'
        . '  });'
        . '});'
        . 'var attachInput=document.getElementById("attachments");'
        . 'var preview=document.getElementById("attachPreview");'
        . 'if(attachInput&&preview){'
        . '  attachInput.addEventListener("change",function(){'
        . '    if(!this.files.length){preview.innerHTML="";return;}'
        . '    var arr=[];for(var i=0;i<this.files.length;i++){'
        . '      var f=this.files[i];var sz=(f.size/1024/1024).toFixed(2)+"MB";'
        . '      arr.push("<span style=\\"display:inline-block;background:#eef2ff;border:1px solid #c7d2fe;padding:3px 8px;border-radius:12px;margin:2px 4px 2px 0;\\">"+f.name+" ("+sz+")</span>");'
        . '    }preview.innerHTML=arr.join("");'
        . '  });'
        . '}'
        // 確認者氏名 SELECT 切り替え
        . 'var confSel=document.getElementById("confirmer_select");'
        . 'var confInp=document.getElementById("confirmer_name_input");'
        . 'if(confSel&&confInp){'
        . '  confSel.addEventListener("change",function(){'
        . '    if(this.value==="__other__"){confInp.style.display="";confInp.value="";confInp.focus();}'
        . '    else{confInp.style.display="none";confInp.value=this.value;}'
        . '  });'
        . '}'
        // 送信前バリデーション
        . 'var form=document.querySelector("form");'
        . 'if(form){'
        . '  form.addEventListener("submit",function(e){'
        . '    var name=(confInp&&confInp.value||"").trim();'
        . '    var commentVal=(document.getElementById("comment").value||"").trim();'
        . '    var hasAttach=document.getElementById("attachments").files.length>0;'
        . '    if(!name){'
        . '      e.preventDefault();'
        . '      alert("確認者氏名を選択（または直接入力）してください。");'
        . '      if(confSel)confSel.focus();'
        . '      return false;'
        . '    }'
        . '  });'
        . '}'
        . '</script>';

    renderPage('週報確認', $html);
}

// ─── アップロードファイル処理 ──────────────────────────
function processConfirmAttachments(): array {
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'] ?? null)) return ['html' => '', 'errors' => []];

    $allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedDoc    = ['application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/octet-stream', 'application/CDFV2',
    ];
    $allowed = array_merge($allowedImages, $allowedDoc);
    $extMap = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
    $allowedExts = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx'];

    $uploadDir = __DIR__ . '/../uploads/weekly-reports/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $html = '';
    $errors = [];
    $count = count($_FILES['attachments']['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $name = $_FILES['attachments']['name'][$i] ?? '';
        $tmp  = $_FILES['attachments']['tmp_name'][$i] ?? '';
        $size = $_FILES['attachments']['size'][$i] ?? 0;
        if (empty($tmp) || !is_uploaded_file($tmp)) continue;

        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        $origExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($mime, $allowed, true) && !in_array($origExt, $allowedExts, true)) {
            $errors[] = $name . ': 許可されていないファイル形式';
            continue;
        }
        $isImage = in_array($mime, $allowedImages, true) || in_array($origExt, ['jpg','jpeg','png','gif','webp'], true);
        $maxSize = $isImage ? 10 * 1024 * 1024 : 25 * 1024 * 1024;
        if ($size > $maxSize) { $errors[] = $name . ': サイズ超過'; continue; }

        $ext = $extMap[$mime] ?? $origExt ?? 'bin';
        if (!in_array($ext, $allowedExts, true)) $ext = $origExt ?: 'bin';

        $localFilename = 'wr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $localPath     = $uploadDir . $localFilename;
        if (!move_uploaded_file($tmp, $localPath)) { $errors[] = $name . ': 保存失敗'; continue; }

        $url = '/api/serve-weekly-file.php?f=' . urlencode($localFilename);
        $absUrl = getBaseUrl() . $url;
        $safeName = htmlspecialchars($name);
        if ($isImage) {
            $html .= '<div><img src="' . htmlspecialchars($absUrl) . '" alt="' . $safeName . '" style="max-width:100%;height:auto;border-radius:6px;margin:6px 0;"></div>';
        } else {
            $colorMap = [
                'pdf'=>['#fff3e0','#ffe0b2','#e65100'],
                'doc'=>['#e3f2fd','#bbdefb','#1565c0'],'docx'=>['#e3f2fd','#bbdefb','#1565c0'],
                'xls'=>['#e8f5e9','#c8e6c9','#2e7d32'],'xlsx'=>['#e8f5e9','#c8e6c9','#2e7d32'],
                'ppt'=>['#fff3e0','#ffcc80','#e65100'],'pptx'=>['#fff3e0','#ffcc80','#e65100'],
            ];
            $c = $colorMap[$ext] ?? ['#f5f5f5','#e0e0e0','#424242'];
            $html .= '<div><a href="' . htmlspecialchars($absUrl) . '" target="_blank" rel="noopener" style="display:inline-block;padding:6px 12px;background:' . $c[0] . ';border:1px solid ' . $c[1] . ';border-radius:6px;color:' . $c[2] . ';text-decoration:none;font-size:13px;margin:4px 0;">📎 ' . $safeName . '</a></div>';
        }
    }
    return ['html' => $html, 'errors' => $errors];
}

// ─── POST: 確認処理実行 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getData();
    $now = date('Y-m-d H:i:s');

    $commentText  = trim($_POST['comment'] ?? '');
    $confirmerNameInput = trim($_POST['confirmer_name'] ?? '');
    $confirmerSelect    = trim($_POST['confirmer_select'] ?? '');
    // SELECT で名前を選んだ場合（JS無効ブラウザ対応）
    if ($confirmerNameInput === '' && $confirmerSelect !== '' && $confirmerSelect !== '__other__') {
        $confirmerNameInput = $confirmerSelect;
    }

    // サーバ側バリデーション：確認者氏名は必須
    if ($confirmerNameInput === '') {
        renderPage('入力エラー',
            '<h2>確認者氏名が未入力です</h2>'
            . '<p>あなたが誰かを識別するために、確認者氏名の選択（または入力）が必要です。</p>'
            . '<p><a href="javascript:history.back()" class="btn btn-secondary" style="display:inline-block;margin-top:0.5rem;">戻る</a></p>',
            true
        );
    }

    // 添付ファイル処理
    $attach = processConfirmAttachments();
    $attachmentsHtml = $attach['html'];
    $attachmentErrors = $attach['errors'];

    $found = false;
    $confirmedReport = null;
    $reportIdx = null;
    foreach ($data['weekly_reports'] as $idx => $r) {
        if (($r['confirm_token'] ?? '') === $token && empty($r['deleted_at'])) {
            if (!empty($r['confirmed_at'])) {
                renderPage('確認済み', '<h2>この週報は既に確認済みです</h2>');
            }
            $confirmedByName = $confirmerNameInput !== '' ? $confirmerNameInput : 'メール確認';
            $data['weekly_reports'][$idx]['confirmed_at']      = $now;
            $data['weekly_reports'][$idx]['confirmed_by']      = 'email_action';
            $data['weekly_reports'][$idx]['confirmed_by_name'] = $confirmedByName;
            $data['weekly_reports'][$idx]['updated_at']        = $now;
            $found = true;
            $confirmedReport = $data['weekly_reports'][$idx];
            $reportIdx = $idx;
            break;
        }
    }

    if (!$found) {
        renderPage('エラー', '<h2>週報が見つかりません</h2>', true);
    }

    // コメント or 添付があれば保存
    $commentSaved = false;
    $mentionedNames = [];
    if ($commentText !== '' || $attachmentsHtml !== '') {
        if (!isset($data['report_comments'])) $data['report_comments'] = [];

        // メンション抽出（保存はプレーンテキスト相当、表示時に span 化される）
        if ($commentText !== '' && preg_match_all('/@([A-Za-z0-9_\x{3040}-\x{30ff}\x{4e00}-\x{9faf}\s\x{3000}]+?)(?=\s|$|@|[,，。、])/u', $commentText, $matches)) {
            $mentionedNames = array_map('trim', $matches[1]);
        }

        // テキストを <br> 改行 + URL リンク化
        $bodyHtml = '';
        if ($commentText !== '') {
            $bodyHtml = nl2br(htmlspecialchars($commentText));
            $bodyHtml = preg_replace_callback(
                '/(<[^>]+>)|(https?:\/\/[^\s<>"\']+)/i',
                function($m) {
                    if (!empty($m[1])) return $m[1];
                    $url = $m[2];
                    return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($url) . '</a>';
                },
                $bodyHtml
            );
        }
        // 添付HTMLを末尾に付加
        if ($attachmentsHtml !== '') {
            $bodyHtml .= ($bodyHtml !== '' ? '<div style="margin-top:8px;"></div>' : '') . $attachmentsHtml;
        }

        $data['report_comments'][] = [
            'id'         => uniqid('rc_', true),
            'report_id'  => $confirmedReport['id'] ?? '',
            'user_email' => 'email_action',
            'user_name'  => ($confirmerNameInput !== '' ? $confirmerNameInput : 'メール確認'),
            'body'       => $bodyHtml,
            'created_at' => $now,
        ];
        $commentSaved = true;
    }

    saveData($data);

    // ─── メール通知 ──────────────────────────
    $confirmerDisplay = $confirmerNameInput !== '' ? $confirmerNameInput : 'メール確認';

    // 提出者へ確認通知
    $submitterEmail = $confirmedReport['user_email'] ?? '';
    if (is_string($submitterEmail) && str_starts_with($submitterEmail, 'enc:')) {
        require_once __DIR__ . '/../functions/encryption.php';
        try { $submitterEmail = decryptValue($submitterEmail); } catch (Exception $e) { $submitterEmail = ''; }
    }
    if (!empty($submitterEmail) && filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
        $submitterName = htmlspecialchars($confirmedReport['user_name'] ?? '');
        $notifSubject  = "【週報確認済み】{$weekStart} の週報が確認されました";
        $notifBody     = "<p>{$submitterName} さんの週報（{$weekStart}）が確認されました。</p>"
            . "<p>確認者: " . htmlspecialchars($confirmerDisplay) . "</p>"
            . "<p>確認日時: {$now}</p>";
        if ($commentSaved) {
            $notifBody .= '<div style="margin-top:12px;padding:10px;background:#f5f5f5;border-left:3px solid #27ae60;">'
                . '<div style="font-weight:600;font-size:13px;color:#444;">確認時のコメント</div>'
                . '<div style="margin-top:4px;font-size:14px;line-height:1.6;">' . ($data['report_comments'][count($data['report_comments']) - 1]['body'] ?? '') . '</div>'
                . '</div>';
        }
        sendNotificationEmail($submitterEmail, $notifSubject, $notifBody);
    }

    // メンションされたユーザーへ通知（社員マスタから @名前 完全一致を検出）
    $notifiedEmails = [];
    $candidates = getMentionCandidates();
    // 名前の長い順にソート（部分一致誤検出防止）
    usort($candidates, fn($a, $b) => mb_strlen($b['name']) - mb_strlen($a['name']));
    if ($commentText !== '') {
        foreach ($candidates as $hit) {
            $needle = '@' . $hit['name'];
            if (mb_strpos($commentText, $needle) === false) continue;
            if (empty($hit['email']) || !filter_var($hit['email'], FILTER_VALIDATE_EMAIL)) continue;
            if (in_array($hit['email'], $notifiedEmails, true)) continue;
            $notifiedEmails[] = $hit['email'];

            $mSubj = "【週報メンション】" . htmlspecialchars($confirmerDisplay) . " さんからメンションがあります";
            $mBody = "<p>" . htmlspecialchars($confirmerDisplay) . " さんが "
                . htmlspecialchars($confirmedReport['user_name'] ?? '') . " さんの週報（" . $weekStart . "）の確認時にあなたにメンションしました。</p>"
                . '<div style="margin-top:12px;padding:10px;background:#f5f5f5;border-left:3px solid #2563eb;">'
                . '<div style="font-weight:600;font-size:13px;color:#444;">コメント</div>'
                . '<div style="margin-top:4px;font-size:14px;line-height:1.6;">' . ($data['report_comments'][count($data['report_comments']) - 1]['body'] ?? '') . '</div>'
                . '</div>'
                . '<p style="margin-top:12px;"><a href="' . htmlspecialchars(getBaseUrl()) . '/pages/reports-hub.php" style="color:#2563eb;">アプリで詳細を確認する</a></p>';
            sendNotificationEmail($hit['email'], $mSubj, $mBody);
        }
    }

    $mentionInfo = empty($notifiedEmails) ? '' : '<p>メンションされた ' . count($notifiedEmails) . ' 名にメール通知を送信しました。</p>';
    $commentInfo = $commentSaved ? '<p>コメント' . ($attachmentsHtml !== '' ? '（添付付き）' : '') . 'を保存しました。</p>' : '';
    $errorInfo = '';
    if (!empty($attachmentErrors)) {
        $errorInfo = '<p style="color:#c62828;">添付エラー:</p><ul style="color:#c62828;font-size:13px;">';
        foreach ($attachmentErrors as $err) {
            $errorInfo .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $errorInfo .= '</ul>';
    }

    $html = '<h2>確認しました</h2>'
        . '<p>' . $userName . ' さんの週報（' . $weekStart . '）を確認済みにしました。</p>'
        . '<p>提出者にメールで通知されます。</p>'
        . $commentInfo
        . $mentionInfo
        . $errorInfo;

    renderPage('確認完了', $html);
}
