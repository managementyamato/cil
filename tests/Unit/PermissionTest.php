<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 権限チェック関数のユニットテスト
 *
 * テスト対象: config/config.php の hasPermission, isAdmin, canEdit, canDelete
 * これらの関数が壊れると全ページの権限制御が破綻する
 */
class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        clearTestSession();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        clearTestSession();
    }

    // ==================== hasPermission() ====================

    public function testHasPermissionReturnsFalseWithNoSession(): void
    {
        // セッションにuser_roleがない場合
        $this->assertFalse(hasPermission('sales'));
    }

    public function testSalesRoleCanAccessSalesLevel(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertTrue(hasPermission('sales'));
    }

    public function testSalesRoleCannotAccessProductLevel(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertFalse(hasPermission('product'));
    }

    public function testSalesRoleCannotAccessAdminLevel(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertFalse(hasPermission('admin'));
    }

    public function testProductRoleCanAccessSalesLevel(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertTrue(hasPermission('sales'));
    }

    public function testProductRoleCanAccessProductLevel(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertTrue(hasPermission('product'));
    }

    public function testProductRoleCannotAccessAdminLevel(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertFalse(hasPermission('admin'));
    }

    public function testAdminRoleCanAccessAllLevels(): void
    {
        createTestSession('user@test.com', 'admin');
        $this->assertTrue(hasPermission('sales'));
        $this->assertTrue(hasPermission('product'));
        $this->assertTrue(hasPermission('admin'));
    }

    public function testUnknownRoleCannotAccessAnything(): void
    {
        $_SESSION['user_role'] = 'unknown_role';
        $this->assertFalse(hasPermission('sales'));
    }

    public function testUnknownRequiredRoleRejectsEveryone(): void
    {
        createTestSession('user@test.com', 'admin');
        // 存在しない権限レベルは 999 として扱われる
        $this->assertFalse(hasPermission('super_admin'));
    }

    // ==================== isAdmin() ====================

    public function testIsAdminReturnsTrueForAdmin(): void
    {
        createTestSession('user@test.com', 'admin');
        $this->assertTrue(isAdmin());
    }

    public function testIsAdminReturnsFalseForProduct(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertFalse(isAdmin());
    }

    public function testIsAdminReturnsFalseForSales(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertFalse(isAdmin());
    }

    public function testIsAdminReturnsFalseWithNoSession(): void
    {
        $this->assertFalse(isAdmin());
    }

    // ==================== canEdit() ====================

    public function testCanEditReturnsTrueForAdmin(): void
    {
        createTestSession('user@test.com', 'admin');
        $this->assertTrue(canEdit());
    }

    public function testCanEditReturnsTrueForProduct(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertTrue(canEdit());
    }

    public function testCanEditReturnsFalseForSales(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertFalse(canEdit());
    }

    // ==================== canDelete() ====================

    public function testCanDeleteReturnsTrueOnlyForAdmin(): void
    {
        createTestSession('user@test.com', 'admin');
        $this->assertTrue(canDelete());
    }

    public function testCanDeleteReturnsFalseForProduct(): void
    {
        createTestSession('user@test.com', 'product');
        $this->assertFalse(canDelete());
    }

    public function testCanDeleteReturnsFalseForSales(): void
    {
        createTestSession('user@test.com', 'sales');
        $this->assertFalse(canDelete());
    }

    // ==================== 権限階層の一貫性 ====================

    public function testPermissionHierarchyIsConsistent(): void
    {
        // sales < product < admin の順序が保たれていることを確認
        $roles = ['sales', 'product', 'admin'];

        foreach ($roles as $i => $role) {
            createTestSession('user@test.com', $role);

            // 自分以下の権限にはアクセスできる
            for ($j = 0; $j <= $i; $j++) {
                $this->assertTrue(
                    hasPermission($roles[$j]),
                    "{$role} should have access to {$roles[$j]} level"
                );
            }

            // 自分より上の権限にはアクセスできない
            for ($j = $i + 1; $j < count($roles); $j++) {
                $this->assertFalse(
                    hasPermission($roles[$j]),
                    "{$role} should NOT have access to {$roles[$j]} level"
                );
            }
        }
    }
}
