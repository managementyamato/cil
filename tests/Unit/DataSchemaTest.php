<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DataSchema;

/**
 * DataSchemaクラスのユニットテスト
 *
 * テスト対象: functions/data-schema.php
 * スキーマ定義が壊れると既存データとの互換性が破綻する
 */
class DataSchemaTest extends TestCase
{
    // ==================== getEntityKeys() ====================

    public function testGetEntityKeysReturnsExpectedEntities(): void
    {
        $keys = DataSchema::getEntityKeys();

        // 必須エンティティが存在することを確認
        $requiredEntities = [
            'projects', 'settings', 'customers', 'partners',
            'employees', 'invoices', 'mf_invoices', 'loans',
            'repayments', 'troubles', 'assignees',
        ];

        foreach ($requiredEntities as $entity) {
            $this->assertContains(
                $entity,
                $keys,
                "Required entity '{$entity}' is missing from DataSchema"
            );
        }
    }

    public function testGetEntityKeysReturnsArray(): void
    {
        $keys = DataSchema::getEntityKeys();
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
    }

    // ==================== getDefault() ====================

    public function testGetDefaultReturnsEmptyArrayForCollections(): void
    {
        $collectionEntities = [
            'projects', 'customers', 'partners', 'employees',
            'invoices', 'mf_invoices', 'loans', 'repayments',
            'troubles',
        ];

        foreach ($collectionEntities as $entity) {
            $default = DataSchema::getDefault($entity);
            $this->assertIsArray(
                $default,
                "Default for '{$entity}' should be an array"
            );
            $this->assertEmpty(
                $default,
                "Default for '{$entity}' should be empty array"
            );
        }
    }

    public function testGetDefaultReturnsSettingsWithSpreadsheetUrl(): void
    {
        $settings = DataSchema::getDefault('settings');
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('spreadsheet_url', $settings);
    }

    public function testGetDefaultReturnsNullForUnknownEntity(): void
    {
        $this->assertNull(DataSchema::getDefault('nonexistent'));
    }

    // ==================== getFields() ====================

    public function testGetFieldsReturnsFieldDefinitions(): void
    {
        $fields = DataSchema::getFields('projects');
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('name', $fields);
    }

    public function testGetFieldsIdIsRequiredForAllEntitiesWithFields(): void
    {
        $entitiesWithFields = [
            'projects', 'customers', 'partners', 'employees',
            'invoices', 'mf_invoices', 'loans', 'repayments',
            'troubles',
        ];

        foreach ($entitiesWithFields as $entity) {
            $fields = DataSchema::getFields($entity);
            if ($fields !== null) {
                $this->assertArrayHasKey(
                    'id',
                    $fields,
                    "Entity '{$entity}' should have 'id' field"
                );
                $this->assertTrue(
                    $fields['id']['required'],
                    "Entity '{$entity}' id field should be required"
                );
            }
        }
    }

    public function testGetFieldsReturnsNullForEntityWithoutFields(): void
    {
        $this->assertNull(DataSchema::getFields('assignees'));
        $this->assertNull(DataSchema::getFields('settings'));
    }

    public function testGetFieldsReturnsNullForUnknownEntity(): void
    {
        $this->assertNull(DataSchema::getFields('nonexistent'));
    }

    // ==================== hasEntity() ====================

    public function testHasEntityReturnsTrueForKnownEntities(): void
    {
        $this->assertTrue(DataSchema::hasEntity('projects'));
        $this->assertTrue(DataSchema::hasEntity('employees'));
        $this->assertTrue(DataSchema::hasEntity('settings'));
    }

    public function testHasEntityReturnsFalseForUnknownEntity(): void
    {
        $this->assertFalse(DataSchema::hasEntity('nonexistent'));
        $this->assertFalse(DataSchema::hasEntity(''));
    }

    // ==================== getInitialData() ====================

    public function testGetInitialDataContainsAllEntities(): void
    {
        $data = DataSchema::getInitialData();
        $keys = DataSchema::getEntityKeys();

        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $data,
                "Initial data should contain key '{$key}'"
            );
        }
    }

    public function testGetInitialDataMatchesDefaults(): void
    {
        $data = DataSchema::getInitialData();
        $keys = DataSchema::getEntityKeys();

        foreach ($keys as $key) {
            $this->assertSame(
                DataSchema::getDefault($key),
                $data[$key],
                "Initial data for '{$key}' should match getDefault()"
            );
        }
    }

    // ==================== ensureSchema() ====================

    public function testEnsureSchemaAddsMissingKeys(): void
    {
        // 一部のキーだけ持つデータ
        $partialData = [
            'projects' => [['id' => '1', 'name' => 'Test']],
            'settings' => ['spreadsheet_url' => 'https://example.com'],
        ];

        $result = DataSchema::ensureSchema($partialData);

        // 元のデータは保持される
        $this->assertCount(1, $result['projects']);
        $this->assertEquals('https://example.com', $result['settings']['spreadsheet_url']);

        // 不足キーが補完される
        $this->assertArrayHasKey('customers', $result);
        $this->assertArrayHasKey('employees', $result);
        $this->assertArrayHasKey('troubles', $result);
    }

    public function testEnsureSchemaDoesNotOverwriteExistingData(): void
    {
        $existingData = DataSchema::getInitialData();
        $existingData['projects'] = [['id' => 'p1', 'name' => 'Existing']];

        $result = DataSchema::ensureSchema($existingData);

        // 既存データが上書きされていないことを確認
        $this->assertCount(1, $result['projects']);
        $this->assertEquals('p1', $result['projects'][0]['id']);
    }

    public function testEnsureSchemaHandlesEmptyInput(): void
    {
        $result = DataSchema::ensureSchema([]);
        $keys = DataSchema::getEntityKeys();

        // 全キーが追加される
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    // ==================== isFieldEditable() ====================

    public function testIdFieldIsNotEditable(): void
    {
        $this->assertFalse(DataSchema::isFieldEditable('projects', 'id'));
        $this->assertFalse(DataSchema::isFieldEditable('employees', 'id'));
    }

    public function testCreatedAtFieldIsNotEditable(): void
    {
        $this->assertFalse(DataSchema::isFieldEditable('projects', 'created_at'));
    }

    public function testRegularFieldIsEditable(): void
    {
        $this->assertTrue(DataSchema::isFieldEditable('projects', 'name'));
        $this->assertTrue(DataSchema::isFieldEditable('projects', 'status'));
        $this->assertTrue(DataSchema::isFieldEditable('employees', 'name'));
    }

    public function testUndefinedFieldIsNotEditable(): void
    {
        $this->assertFalse(DataSchema::isFieldEditable('projects', 'nonexistent_field'));
    }

    public function testEntityWithoutFieldsAllowsAllEdits(): void
    {
        // assigneesにはfieldsが定義されていない→全て編集可能
        $this->assertTrue(DataSchema::isFieldEditable('assignees', 'anything'));
    }

    // ==================== getFieldNames() ====================

    public function testGetFieldNamesReturnsFieldList(): void
    {
        $names = DataSchema::getFieldNames('projects');
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('status', $names);
    }

    public function testGetFieldNamesReturnsEmptyForEntityWithoutFields(): void
    {
        $this->assertEmpty(DataSchema::getFieldNames('assignees'));
    }

    // ==================== getRequiredFields() ====================

    public function testGetRequiredFieldsReturnsCorrectFields(): void
    {
        $required = DataSchema::getRequiredFields('projects');
        $this->assertContains('id', $required);
        $this->assertContains('name', $required);
        // statusは必須ではない
        $this->assertNotContains('status', $required);
    }

    public function testGetRequiredFieldsReturnsEmptyForEntityWithoutFields(): void
    {
        $this->assertEmpty(DataSchema::getRequiredFields('assignees'));
    }

    public function testGetRequiredFieldsForAllEntities(): void
    {
        // IDが必須のエンティティ一覧
        $entitiesWithId = [
            'projects', 'customers', 'partners', 'employees',
            'invoices', 'mf_invoices', 'loans', 'repayments',
            'troubles',
        ];

        foreach ($entitiesWithId as $entity) {
            $required = DataSchema::getRequiredFields($entity);
            $this->assertContains(
                'id',
                $required,
                "Entity '{$entity}' should have 'id' as required field"
            );
        }
    }
}
