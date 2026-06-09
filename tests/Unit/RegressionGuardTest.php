<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 回帰防止テスト
 *
 * 機能追加時に既存の重要な定義が壊れていないことを検出する。
 * これらのテストが失敗したら、何かが意図せず変更されたことを意味する。
 */
class RegressionGuardTest extends TestCase
{
    // ==================== 権限システムの定数テスト ====================

    public function testRoleHierarchyHasThreeLevels(): void
    {
        // hasPermission()内部で使用される権限レベルが3段階であること
        // sales=1, product=2, admin=3 の順序が保たれていること

        // salesが最低レベル
        createTestSession('u@t.com', 'sales');
        $this->assertTrue(hasPermission('sales'));
        $this->assertFalse(hasPermission('product'));
        $this->assertFalse(hasPermission('admin'));

        // productが中間レベル
        createTestSession('u@t.com', 'product');
        $this->assertTrue(hasPermission('sales'));
        $this->assertTrue(hasPermission('product'));
        $this->assertFalse(hasPermission('admin'));

        // adminが最高レベル
        createTestSession('u@t.com', 'admin');
        $this->assertTrue(hasPermission('sales'));
        $this->assertTrue(hasPermission('product'));
        $this->assertTrue(hasPermission('admin'));
    }

    public function testCanDeleteIsStricterThanCanEdit(): void
    {
        // canDelete()はadminのみ、canEdit()はproduct以上
        // この関係が壊れると、一般ユーザーがデータを削除できてしまう

        createTestSession('u@t.com', 'product');
        $this->assertTrue(canEdit(), 'product role should be able to edit');
        $this->assertFalse(canDelete(), 'product role should NOT be able to delete');

        createTestSession('u@t.com', 'admin');
        $this->assertTrue(canEdit(), 'admin role should be able to edit');
        $this->assertTrue(canDelete(), 'admin role should be able to delete');
    }

    // ==================== DataSchema の不変性テスト ====================

    public function testDataSchemaHasMinimumRequiredEntities(): void
    {
        // 最低限必要なエンティティが存在すること
        // これらが削除されると既存データが読み込めなくなる
        $requiredEntities = [
            'projects',
            'customers',
            'employees',
            'troubles',
            'invoices',
            'settings',
            'loans',
            'repayments',
        ];

        foreach ($requiredEntities as $entity) {
            $this->assertTrue(
                \DataSchema::hasEntity($entity),
                "Critical entity '{$entity}' is missing from DataSchema. " .
                "Removing it will break existing data."
            );
        }
    }

    public function testProjectsEntityHasEssentialFields(): void
    {
        // projects のフィールドが勝手に削除されていないことを確認
        $fields = \DataSchema::getFieldNames('projects');

        $essentialFields = [
            'id', 'name', 'customer_name', 'status',
            'created_at', 'updated_at',
        ];

        foreach ($essentialFields as $field) {
            $this->assertContains(
                $field,
                $fields,
                "Essential field 'projects.{$field}' was removed from DataSchema"
            );
        }
    }

    public function testEmployeesEntityHasEssentialFields(): void
    {
        $fields = \DataSchema::getFieldNames('employees');

        $essentialFields = ['id', 'name', 'email'];

        foreach ($essentialFields as $field) {
            $this->assertContains(
                $field,
                $fields,
                "Essential field 'employees.{$field}' was removed from DataSchema"
            );
        }
    }

    public function testTroublesEntityHasEssentialFields(): void
    {
        $fields = \DataSchema::getFieldNames('troubles');

        $essentialFields = ['id', 'title', 'status'];

        foreach ($essentialFields as $field) {
            $this->assertContains(
                $field,
                $fields,
                "Essential field 'troubles.{$field}' was removed from DataSchema"
            );
        }
    }

    // ==================== 設定値の定数テスト ====================

    public function testAppVersionIsDefined(): void
    {
        $this->assertTrue(defined('APP_VERSION'), 'APP_VERSION constant must be defined');
    }

    // ==================== auth.phpの権限定義がソースコードと一致 ====================

    public function testDefaultPagePermissionsExistInAuthFile(): void
    {
        $authFile = dirname(__DIR__, 2) . '/api/auth.php';
        $this->assertFileExists($authFile);

        $content = file_get_contents($authFile);

        // $defaultPagePermissions が定義されていること
        $this->assertStringContainsString(
            '$defaultPagePermissions',
            $content,
            'api/auth.php must define $defaultPagePermissions'
        );
    }

    public function testAuthFileContainsSessionCheck(): void
    {
        $authFile = dirname(__DIR__, 2) . '/api/auth.php';
        $content = file_get_contents($authFile);

        // ログインチェックが存在すること
        $this->assertStringContainsString(
            '$_SESSION[\'user_email\']',
            $content,
            'api/auth.php must check user_email in session'
        );
    }

    public function testAuthFileContainsTimeoutCheck(): void
    {
        $authFile = dirname(__DIR__, 2) . '/api/auth.php';
        $content = file_get_contents($authFile);

        // セッションタイムアウトチェックが存在すること
        $this->assertStringContainsString(
            'last_activity',
            $content,
            'api/auth.php must check session timeout (last_activity)'
        );
    }

    // ==================== config.phpの重要関数が存在すること ====================

    public function testCriticalFunctionsExist(): void
    {
        // これらの関数が削除されるとシステム全体が壊れる
        $criticalFunctions = [
            'hasPermission',
            'isAdmin',
            'canEdit',
            'canDelete',
            'getData',
            'saveData',
            'getInitialData',
            'generateCsrfToken',
            'verifyCsrfToken',
            'csrfTokenField',
            'env',
        ];

        foreach ($criticalFunctions as $func) {
            $this->assertTrue(
                function_exists($func),
                "Critical function '{$func}' is missing. " .
                "This will break the entire application."
            );
        }
    }

    public function testAuthFunctionsExist(): void
    {
        // auth.phpの関数（グローバルスコープで読み込まれている場合）
        $authFunctions = [
            'getPageViewPermission',
            'getPageEditPermission',
            'canEditPage',
        ];

        global $pagePermissions;
        if (!isset($pagePermissions)) {
            $this->markTestSkipped('auth.php not loaded in test context');
        }

        foreach ($authFunctions as $func) {
            $this->assertTrue(
                function_exists($func),
                "Auth function '{$func}' is missing"
            );
        }
    }

    // ==================== CSRF保護の一貫性 ====================

    public function testCsrfTokenFieldContainsHiddenInput(): void
    {
        $field = csrfTokenField();

        // hidden inputとして出力されること
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);

        // XSS対策: value属性がエスケープされていること
        $this->assertStringNotContainsString('<script', $field);
    }

    // ==================== saveData()の安全性テスト ====================

    public function testGetInitialDataPassesSaveDataSizeCheck(): void
    {
        // getInitialData()の結果は十分なサイズがあること
        $data = getInitialData();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $this->assertGreaterThan(
            100,
            strlen($json),
            'Initial data JSON should be > 100 bytes to pass saveData() validation'
        );
    }

    // ==================== ページファイルの基本構造テスト ====================

    public function testPageFilesIncludeAuthCheck(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/pages';
        $pageFiles = glob($pagesDir . '/*.php');

        // 認証不要なページ
        $exemptPages = ['login.php', 'setup.php', 'color-samples.php', 'logout.php', 'db-migrate.php'];

        $pagesWithoutAuth = [];
        foreach ($pageFiles as $file) {
            $pageName = basename($file);
            if (in_array($pageName, $exemptPages)) {
                continue;
            }
            // アンダースコア接頭辞のパーシャル (例: _hub-shell-top.php / _hub-shell-bottom.php) は
            // 他ページから include される共通断片で単独アクセス想定外 → 認証チェック対象外
            if (str_starts_with($pageName, '_')) {
                continue;
            }
            // テスト/デバッグ用ページは auto-deploy.ps1 で本番から物理削除されるため除外
            if (str_starts_with($pageName, 'test-')) {
                continue;
            }

            $content = file_get_contents($file);

            // auth.phpまたはconfig.phpをrequireしていること
            $hasAuth = (
                strpos($content, "require_once '../api/auth.php'") !== false ||
                strpos($content, "require_once __DIR__ . '/../api/auth.php'") !== false ||
                strpos($content, "require '../api/auth.php'") !== false
            );

            if (!$hasAuth) {
                $pagesWithoutAuth[] = $pageName;
            }
        }

        $this->assertEmpty(
            $pagesWithoutAuth,
            "The following pages do not include auth.php:\n" .
            implode("\n", array_map(fn($p) => "  - pages/{$p}", $pagesWithoutAuth)) .
            "\nThis means they have NO authentication check!"
        );
    }

    public function testPostHandlersHaveCsrfCheck(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/pages';
        $pageFiles = glob($pagesDir . '/*.php');

        $exemptPages = ['login.php', 'setup.php'];
        $pagesWithUnsafePost = [];

        foreach ($pageFiles as $file) {
            $pageName = basename($file);
            if (in_array($pageName, $exemptPages)) {
                continue;
            }

            $content = file_get_contents($file);

            // POSTメソッドチェックがあるファイルを対象
            $hasPostCheck = (
                strpos($content, "REQUEST_METHOD") !== false &&
                strpos($content, "'POST'") !== false
            );

            if ($hasPostCheck) {
                // verifyCsrfToken()の呼び出しがあるか確認
                $hasCsrfCheck = strpos($content, 'verifyCsrfToken()') !== false;

                if (!$hasCsrfCheck) {
                    $pagesWithUnsafePost[] = $pageName;
                }
            }
        }

        $this->assertEmpty(
            $pagesWithUnsafePost,
            "The following pages handle POST without CSRF verification:\n" .
            implode("\n", array_map(fn($p) => "  - pages/{$p}", $pagesWithUnsafePost)) .
            "\nAdd verifyCsrfToken() to all POST handlers!"
        );
    }

    // ==================== 削除処理の権限チェック ====================

    /**
     * 削除処理（delete_ 系の POST パラメータ）に canDelete() チェックがあることを検証
     */
    public function testDeleteHandlersHavePermissionCheck(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/pages';
        $pageFiles = glob($pagesDir . '/*.php');

        // 削除処理が不要なページ（削除機能なし or API経由で削除）
        $exemptPages = ['login.php', 'setup.php', 'color-samples.php', 'logout.php', 'db-migrate.php'];

        $pagesWithUnsafeDelete = [];

        foreach ($pageFiles as $file) {
            $pageName = basename($file);
            if (in_array($pageName, $exemptPages)) {
                continue;
            }

            $content = file_get_contents($file);

            // delete_ 系の POST パラメータを処理しているか確認
            if (preg_match('/\$_POST\[[\'"](delete_|bulk_delete|purge_)/', $content)) {
                // canDelete() チェックがあるか確認
                $hasDeletePermCheck = (
                    strpos($content, 'canDelete()') !== false ||
                    strpos($content, 'isAdmin()') !== false
                );

                if (!$hasDeletePermCheck) {
                    $pagesWithUnsafeDelete[] = $pageName;
                }
            }
        }

        $this->assertEmpty(
            $pagesWithUnsafeDelete,
            "The following pages handle delete operations without canDelete() or isAdmin() check:\n" .
            implode("\n", array_map(fn($p) => "  - pages/{$p}", $pagesWithUnsafeDelete)) .
            "\nAll delete operations must check canDelete() or isAdmin()!"
        );
    }

    /**
     * 削除処理に監査ログ（auditDelete/writeAuditLog）の記録があることを検証
     */
    public function testDeleteHandlersHaveAuditLog(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/pages';
        $pageFiles = glob($pagesDir . '/*.php');

        // loans.php は loans-api.php の deleteLoan() 内で監査ログを記録するため除外
        // _template-*.php はテンプレートファイル（ダミーコード）のため除外
        $exemptPages = ['login.php', 'setup.php', 'color-samples.php', 'logout.php', 'loans.php',
                        '_template-list.php', '_template-settings.php', '_template-hub.php'];

        $pagesWithoutAuditLog = [];

        foreach ($pageFiles as $file) {
            $pageName = basename($file);
            if (in_array($pageName, $exemptPages)) {
                continue;
            }

            $content = file_get_contents($file);

            // delete_ 系の POST パラメータを処理しているか確認
            if (preg_match('/\$_POST\[[\'"](delete_|bulk_delete)/', $content)) {
                // 監査ログの記録があるか確認
                $hasAuditLog = (
                    strpos($content, 'auditDelete(') !== false ||
                    strpos($content, 'writeAuditLog(') !== false
                );

                if (!$hasAuditLog) {
                    $pagesWithoutAuditLog[] = $pageName;
                }
            }
        }

        $this->assertEmpty(
            $pagesWithoutAuditLog,
            "The following pages handle delete operations without audit logging:\n" .
            implode("\n", array_map(fn($p) => "  - pages/{$p}", $pagesWithoutAuditLog)) .
            "\nAll delete operations must record audit logs (auditDelete or writeAuditLog)!"
        );
    }

    // ==================== ソフトデリート関数のテスト ====================

    public function testSoftDeleteFunctionsExist(): void
    {
        $criticalFunctions = [
            'softDelete',
            'restoreItem',
            'filterDeleted',
            'getDeletedItems',
            'purgeDeleted',
        ];

        foreach ($criticalFunctions as $func) {
            $this->assertTrue(
                function_exists($func),
                "Soft delete function '{$func}' is missing. " .
                "This will break the data protection system."
            );
        }
    }

    // ==================== api-middleware.php のページファイルinclude禁止テスト ====================

    /**
     * pages/*.php が api-middleware.php をincludeする場合、
     * set_error_handler(null) で必ずエラーハンドラをリセットしていることを検証。
     *
     * api-middleware.php はAPIファイル専用で、全PHPエラーをJSONで返して exit するエラーハンドラを設定する。
     * ページファイルでこのエラーハンドラが有効なまま使われると、HTML出力中のWarningでページが
     * 途中で切れ、{"success":false,"error":"Internal server error"} が埋め込まれる（2026-02-17に実際に発生）。
     */
    public function testPageFilesResetApiMiddlewareErrorHandler(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/pages';
        $pageFiles = glob($pagesDir . '/*.php');

        $violations = [];

        foreach ($pageFiles as $file) {
            $pageName = basename($file);
            $content = file_get_contents($file);

            // api-middleware.php をincludeしているページを対象
            $includesMiddleware = (
                strpos($content, "require_once '../functions/api-middleware.php'") !== false ||
                strpos($content, "require '../functions/api-middleware.php'") !== false
            );

            if (!$includesMiddleware) {
                continue;
            }

            // set_error_handler(null) でリセットしていることを確認
            $resetsErrorHandler = strpos($content, 'set_error_handler(null)') !== false;

            if (!$resetsErrorHandler) {
                $violations[] = $pageName;
            }
        }

        $this->assertEmpty(
            $violations,
            "以下のページファイルが api-middleware.php をincludeしていますが、エラーハンドラをリセットしていません:\n" .
            implode("\n", array_map(fn($p) => "  - pages/{$p}", $violations)) .
            "\napi-middleware.php のinclude後に set_error_handler(null); set_exception_handler(null); を追加してください。" .
            "\nこれがないとHTML出力中のPHP Warningでページが強制終了されます。"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        clearTestSession();
    }
}
