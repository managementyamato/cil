<?php
/**
 * 指定請求書テンプレート 設定マニュアル（管理者向け）
 * 新しい取引先のxlsxテンプレートを Drive に登録する手順を表示
 */
require_once '../api/auth.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.cim-wrap { max-width: 900px; margin: 0 auto; padding: 1rem 1.5rem; }
.cim-wrap h2 { margin-bottom: 1rem; }
.cim-wrap h3 { margin-top: 2rem; padding: 0.5rem 0.75rem; background: #f0f4ff; border-left: 4px solid #3f51b5; font-size: 1.1rem; }
.cim-wrap h4 { margin-top: 1.5rem; font-size: 1rem; color: #333; }
.cim-wrap p { line-height: 1.7; }
.cim-wrap table { width: 100%; border-collapse: collapse; margin: 0.75rem 0; }
.cim-wrap th, .cim-wrap td { padding: 0.5rem 0.75rem; border: 1px solid #ddd; text-align: left; font-size: 0.9rem; }
.cim-wrap th { background: #f5f5f5; font-weight: 600; }
.cim-wrap code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: Consolas, monospace; font-size: 0.9em; }
.cim-wrap pre { background: #2d2d2d; color: #eee; padding: 0.75rem 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; }
.cim-step { background: #fafafa; border-left: 3px solid #3f51b5; padding: 0.75rem 1rem; margin: 0.75rem 0; }
.cim-step-num { display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; background: #3f51b5; color: #fff; border-radius: 50%; font-weight: 600; margin-right: 0.5rem; }
.cim-warn { background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 0.75rem 1rem; margin: 1rem 0; font-size: 0.9rem; }
.cim-tip { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 0.75rem 1rem; margin: 1rem 0; font-size: 0.9rem; }
.cim-faq-q { font-weight: 600; color: #333; margin-top: 1rem; }
.cim-faq-a { margin-left: 1.5rem; color: #555; }
.cim-toc { background: #f9fafb; border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem 1.5rem; margin-bottom: 2rem; }
.cim-toc ol { margin: 0; padding-left: 1.5rem; }
.cim-toc a { text-decoration: none; color: #3f51b5; }
.cim-toc a:hover { text-decoration: underline; }
</style>

<div class="cim-wrap">
    <h2>指定請求書テンプレート 設定マニュアル</h2>
    <p>取引先ごとの指定請求書xlsxを Google Drive に配置してシステムから使えるようにする手順です。</p>

    <div class="cim-tip">
        <strong>基本的にExcel作業は不要です。</strong>
        取引先から受け取ったxlsxをそのまま Drive にアップロードすれば、システムが「請求日」「品名」「数量」などのラベルを自動検出します。
        ただし<strong>営業所マスタ</strong>（取引先に複数の納入先がある場合）は<code>_branches</code>という隠しシートを追加する必要があります。
        自動検出できない特殊レイアウトは Excel の「名前の定義」で明示的に指定することも可能です。
    </div>

    <div class="cim-toc">
        <strong>目次</strong>
        <ol>
            <li><a href="#overview">全体の流れ</a></li>
            <li><a href="#drive-setup">Driveフォルダの初期設定（1回のみ）</a></li>
            <li><a href="#template-naming">テンプレート命名規則</a></li>
            <li><a href="#auto-detect">自動検出の仕組み（通常はこれだけでOK）</a></li>
            <li><a href="#branches-sheet">営業所マスタ（_branchesシート）</a></li>
            <li><a href="#items-headers">明細表のヘッダ認識ルール</a></li>
            <li><a href="#upload">Driveへアップロード</a></li>
            <li><a href="#verify">動作確認</a></li>
            <li><a href="#named-ranges">補足: Excel「名前の定義」を使う場合（自動検出が効かない時）</a></li>
            <li><a href="#faq">よくある質問</a></li>
        </ol>
    </div>

    <h3 id="overview">1. 全体の流れ</h3>
    <p>取引先から指定フォーマットのExcelテンプレートを受領したら、以下の流れで導入します。</p>
    <div class="cim-step">
        <span class="cim-step-num">1</span>取引先から受け取ったxlsxファイルを準備<br>
        <span class="cim-step-num">2</span>営業所がある場合、隠しシート <code>_branches</code> を追加（<a href="#branches-sheet">手順</a>）。無い場合はそのまま。<br>
        <span class="cim-step-num">3</span>ファイル名を取引先名に合わせてリネーム（<a href="#template-naming">命名規則</a>）<br>
        <span class="cim-step-num">4</span>Driveの「指定請求書」フォルダにアップロード<br>
        <span class="cim-step-num">5</span>指定請求書一覧ページで確認<br>
    </div>
    <p>作業時間は1取引先あたり約2〜5分（ファイル名変更・_branchesシート追加のみ）。Excel の「名前の定義」編集は不要です。</p>

    <h3 id="drive-setup">2. Driveフォルダの初期設定（1回のみ）</h3>
    <p>最初に1回だけ、Driveに専用フォルダを作って登録します。</p>
    <ol>
        <li>Google Driveで任意の場所に <strong>「指定請求書テンプレート」</strong>などのフォルダを新規作成</li>
        <li>フォルダを開いてURLをコピー: <code>https://drive.google.com/drive/folders/<strong>xxxxxxxxxxx</strong></code></li>
        <li><strong>設定 → Google Drive保存先 → 指定請求書テンプレート保管先</strong> を開く</li>
        <li>URL末尾の <strong>xxxxxxxxxxx</strong>（folders/の後ろ）をフォルダIDに貼り付け</li>
        <li>フォルダ名（表示用）に「指定請求書テンプレート」と入力して保存</li>
    </ol>
    <div class="cim-tip">
        <strong>共有ドライブでもOK:</strong> 業務用の共有ドライブ配下に作って問題ありません。システムは共有ドライブも自動対応済み。
    </div>

    <h3 id="template-naming">3. テンプレート命名規則</h3>
    <p>ファイル名は<strong>MF請求書の取引先名（partner_name）と部分一致</strong>するように設定します。</p>
    <table>
        <thead><tr><th>MFの取引先名</th><th>推奨ファイル名</th><th>マッチ判定</th></tr></thead>
        <tbody>
            <tr><td>株式会社 アクティオ</td><td><code>アクティオ.xlsx</code></td><td>○</td></tr>
            <tr><td>太陽建機レンタル株式会社</td><td><code>太陽建機レンタル.xlsx</code></td><td>○</td></tr>
            <tr><td>西尾レントオール株式会社</td><td><code>西尾レントオール.xlsx</code></td><td>○</td></tr>
            <tr><td>株式会社 アクティオ</td><td><code>アクティオ指定請求書.xlsx</code></td><td>○（「指定請求書」「株式会社」は自動除去）</td></tr>
            <tr><td>株式会社 アクティオ</td><td><code>actio.xlsx</code></td><td>✗（英字は自動変換されない）</td></tr>
        </tbody>
    </table>
    <p><strong>正規化ルール</strong>: 「株式会社」「有限会社」「㈱」「指定請求書」「請求書」などの語は自動で除去されて比較されるため、ある程度の表記揺れは吸収されます。</p>

    <h3 id="auto-detect">4. 自動検出の仕組み</h3>
    <p>システムはテンプレートを読み込む際、ラベル文字列から入力セルを自動で推測します。通常はこれだけで動作するため、Excelでの特別な設定は不要です。</p>
    <table>
        <thead><tr><th>検出する項目</th><th>認識キーワード</th><th>入力セルの推測ルール</th></tr></thead>
        <tbody>
            <tr><td>営業所名</td><td><code>納入部門名</code> / <code>部門名</code> / <code>営業所名</code> / <code>納入先</code></td><td>ラベルの右隣のセル（結合セル対応）</td></tr>
            <tr><td>請求日</td><td><code>請求日</code></td><td>ラベルの右側。年月日ラベル（年・月・日）が混在する分割パターンも自動判定</td></tr>
            <tr><td>取引先コード</td><td><code>取引先コード</code> / <code>取引コード</code></td><td>ラベル近傍（同じ行または下の行）の数字ストリップ（連続する空または1桁数字のセル）を検出</td></tr>
            <tr><td>明細表</td><td>「品名/数量/単価/金額/納入日/備考」などを3つ以上含む行</td><td>その行をヘッダ、下に続く行を「合計/小計/税抜」などが出現するまでデータ領域とする</td></tr>
        </tbody>
    </table>

    <div class="cim-warn">
        <strong>自動検出の限界:</strong> 特殊なレイアウト（ラベル文字列が上記と違う、入力セルの位置が想定外など）で検出が失敗した場合は、<a href="#named-ranges">補足の「名前の定義」</a>で明示的に指定できます。
    </div>

    <h3 id="branches-sheet">5. 営業所マスタ（_branchesシート）</h3>
    <p>同じテンプレで複数営業所に対応する場合、<strong>隠しシート <code>_branches</code></strong> を追加します。</p>
    <h4>5-1. シート作成手順</h4>
    <div class="cim-step">
        <span class="cim-step-num">1</span>Excel下部のシートタブで「＋」をクリックし新規シート作成<br>
        <span class="cim-step-num">2</span>シート名を <strong>_branches</strong> に変更（アンダースコアを忘れずに）<br>
        <span class="cim-step-num">3</span>下記のようにデータを入力<br>
        <span class="cim-step-num">4</span>シートタブを右クリック → <strong>「非表示」</strong>で隠す（任意。隠しても動作する）<br>
    </div>

    <h4>5-2. シート内容</h4>
    <table>
        <thead><tr><th>A列（営業所名）</th><th>B列（取引先コード）</th></tr></thead>
        <tbody>
            <tr><td>営業所名</td><td>取引先コード</td></tr>
            <tr><td>宮崎営業所</td><td>1668202000</td></tr>
            <tr><td>熊本営業所</td><td>1668202000</td></tr>
            <tr><td>福岡営業所</td><td>1668202000</td></tr>
            <tr><td>...</td><td>...</td></tr>
        </tbody>
    </table>
    <p><strong>ルール:</strong></p>
    <ul>
        <li>1行目はヘッダ（「営業所名」「取引先コード」）</li>
        <li>2行目以降に各営業所を列挙</li>
        <li>取引先コードが営業所ごとに異なる場合、B列にそれぞれ記入</li>
        <li>全営業所で同じ取引先コードでも、全行に同じ値を書いておく</li>
        <li>このシートが無い取引先（営業所の概念なし）は、指定請求書作成画面に営業所プルダウンが表示されない</li>
    </ul>

    <h3 id="items-headers">6. 明細表のヘッダ認識ルール</h3>
    <p><code>items_table</code>の1行目（ヘッダ行）にある文字列を見て、システムが自動でフィールドを紐付けます。</p>
    <table>
        <thead><tr><th>ヘッダに含む文字</th><th>対応フィールド</th><th>書き込む内容</th></tr></thead>
        <tbody>
            <tr><td>納入日 / 納品日</td><td>delivery_date</td><td>日付値（セル書式に従って表示）</td></tr>
            <tr><td>品名 / 品目</td><td>name</td><td>品名（文字列）</td></tr>
            <tr><td>軽減税率</td><td>reduced_tax</td><td>8%対象時のみ「※」</td></tr>
            <tr><td>数量</td><td>quantity</td><td>数値</td></tr>
            <tr><td>単価</td><td>unit_price</td><td>数値</td></tr>
            <tr><td>金額</td><td>amount</td><td>数値（数量×単価）</td></tr>
            <tr><td>備考</td><td>note</td><td>文字列（MFの詳細欄）</td></tr>
            <tr><td>注文No / 注文番号</td><td>order_no</td><td>文字列</td></tr>
        </tbody>
    </table>
    <div class="cim-tip">
        <strong>全角・半角スペースは無視:</strong> 「品　　　　　　名」のようなスペース区切りのヘッダも認識されます。
    </div>

    <h3 id="upload">7. Driveへアップロード</h3>
    <ol>
        <li>設定済みxlsxをローカルに保存（例: <code>アクティオ.xlsx</code>）</li>
        <li>Driveの「指定請求書テンプレート」フォルダを開く</li>
        <li>ファイルをドラッグ&amp;ドロップでアップロード</li>
        <li>同名の既存ファイルがある場合は<strong>先に削除</strong>してからアップロード（上書き推奨）</li>
    </ol>
    <div class="cim-warn">
        <strong>注意:</strong> Google Sheets形式（ネイティブGoogle形式）ではなく、<strong>xlsx形式のまま</strong>アップロードしてください。誤って「Google Sheetsに変換」するとシステムが読み込めません。
    </div>

    <h3 id="verify">8. 動作確認</h3>
    <ol>
        <li>サイドバー「財務 → 指定請求書一覧」を開く</li>
        <li>登録テンプレ数が1増えていることを確認（例: 「登録テンプレ: 2件 (アクティオ、太陽建機レンタル)」）</li>
        <li>MF請求書側で該当取引先の請求書を探し、「作成」ボタンをクリック</li>
        <li>作成画面で以下を確認:
            <ul>
                <li>赤いエラー（「名前の定義が不足」等）が出ていないこと</li>
                <li>営業所プルダウン（<code>_branches</code>シートがある場合のみ）</li>
                <li>明細行がMFからの自動取込で埋まっていること</li>
            </ul>
        </li>
        <li>Excelダウンロードで実物を確認</li>
    </ol>

    <div class="cim-warn">
        <strong>赤いエラーが出た場合:</strong> 自動検出で必須項目が見つかりませんでした。テンプレに「請求日」ラベルや明細ヘッダ（品名/数量/単価/金額など）が含まれているか確認してください。それでも失敗する場合は<a href="#named-ranges">名前の定義</a>で明示的に指定できます。
    </div>

    <h3 id="named-ranges">補足: Excel「名前の定義」で明示的に指定する方法</h3>
    <p>通常は<a href="#auto-detect">自動検出</a>で動作しますが、特殊なレイアウトで検出が失敗する場合、Excelの「名前の定義」機能で明示的に場所を指定できます。名前の定義がある場合は自動検出より優先されます。</p>

    <h4>設定する名前一覧</h4>
    <table>
        <thead><tr><th>名前</th><th>用途</th><th>指定するセル</th></tr></thead>
        <tbody>
            <tr><td><code>branch_name</code></td><td>営業所名を書き込むセル</td><td>単セル（例 J10）</td></tr>
            <tr><td><code>billing_date</code></td><td>請求日（単セル版）</td><td>単セル。日付値として書き込み</td></tr>
            <tr><td><code>billing_date_year</code></td><td>請求年（複数セル版）</td><td>複数セル。1桁ずつ書き込み</td></tr>
            <tr><td><code>billing_date_month</code></td><td>請求月（複数セル版）</td><td>複数セル。1桁ずつ書き込み</td></tr>
            <tr><td><code>billing_date_day</code></td><td>請求日（複数セル版）</td><td>複数セル。1桁ずつ書き込み</td></tr>
            <tr><td><code>partner_code</code></td><td>取引先コード</td><td>単セル or 複数セル</td></tr>
            <tr><td><code>items_table</code></td><td>明細表全体（ヘッダ行含む）</td><td>ヘッダ行＋データ行の矩形範囲</td></tr>
        </tbody>
    </table>

    <h4>設定手順</h4>
    <div class="cim-step">
        <span class="cim-step-num">1</span>Excelでテンプレートを開く<br>
        <span class="cim-step-num">2</span>対象のセル（または範囲）をドラッグ選択<br>
        <span class="cim-step-num">3</span>メニュー: <strong>数式 → 名前の定義 → 新規作成</strong><br>
        <span class="cim-step-num">4</span>「名前」欄に上記リストの名前を入力（例: <code>branch_name</code>）<br>
        <span class="cim-step-num">5</span>「OK」クリック<br>
        <span class="cim-step-num">6</span>全ての名前を設定したらxlsxとして保存<br>
    </div>

    <h4>書き込み挙動</h4>
    <ul>
        <li><strong>単セル</strong>: 値全体を1セルにそのまま書き込み（例: 「熊本営業所」「2026/04/30」）</li>
        <li><strong>複数セル</strong>: 1文字ずつ各セルに分割書き込み（例: 取引先コード「1668202000」→ 10セルに「1」「6」「6」...と1桁ずつ）</li>
    </ul>

    <h3 id="faq">9. よくある質問</h3>

    <p class="cim-faq-q">Q. テンプレに既存のサンプル記入がある場合、削除しないとダメ？</p>
    <p class="cim-faq-a">A. <code>items_table</code>範囲の明細セルは必ずクリアしてください（サンプル行が残ったまま出力される原因になります）。営業所名・取引先コード・請求日のセルは上書きされるので気にしなくてOK。</p>

    <p class="cim-faq-q">Q. 印鑑・罫線・会社ロゴは消えない？</p>
    <p class="cim-faq-a">A. 消えません。システムは指定されたセルの「値」のみ書き換えるため、画像・罫線・フォント・セル結合・条件付き書式は全て保持されます。</p>

    <p class="cim-faq-q">Q. 数式（SUMなど）は残せる？</p>
    <p class="cim-faq-a">A. 残せます。例:「金額=数量×単価」「合計=SUM(金額列)」など。ただし範囲が明細行を正しくカバーしていることを確認してください。</p>

    <p class="cim-faq-q">Q. テンプレを修正したい場合は？</p>
    <p class="cim-faq-a">A. Drive上のxlsxを編集・上書き保存するだけでOK。システムの再デプロイ不要。キャッシュは10分で自動更新されます。</p>

    <p class="cim-faq-q">Q. 複数シートのxlsxでも動く？</p>
    <p class="cim-faq-a">A. <code>_branches</code>以外の最初の表示シートが使われます。不要シートは事前削除を推奨します。</p>

    <p class="cim-faq-q">Q. 明細が12行を超える取引先の場合は？</p>
    <p class="cim-faq-a">A. テンプレの<code>items_table</code>範囲を広げればOK（例: B14:I40で26行分）。範囲を超える明細は自動で分割・ZIP出力されます。</p>

    <p class="cim-faq-q">Q. 同じ営業所の請求書を複数まとめたい</p>
    <p class="cim-faq-a">A. 指定請求書一覧ページでチェックボックスを使って複数選択し「選択した請求書を纏めて作成」ボタンをクリック。明細は各MF請求書の納入日を継承して結合されます。</p>

    <p class="cim-faq-q">Q. 納入日はどこから取られる？</p>
    <p class="cim-faq-a">A. 優先順位: MF請求書の明細行<code>delivery_date</code> → 請求書ヘッダの<code>sales_date</code>（売上日）→ <code>billing_date</code>（請求日）。作成画面で各行を個別に編集可能。</p>

    <p class="cim-faq-q">Q. エラーの切り分け方</p>
    <p class="cim-faq-a">A. ブラウザのF12（開発者ツール）→ Networkタブ → generate のリクエストを見るとサーバーからの詳細エラーが確認できます。</p>

    <div class="cim-tip" style="margin-top:2rem;">
        <strong>それでも分からない場合:</strong> 詳細仕様は <code>docs/custom-invoice-template-guide.md</code> を、実装の中身は <code>functions/custom-invoice-generator.php</code> を参照してください。
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
