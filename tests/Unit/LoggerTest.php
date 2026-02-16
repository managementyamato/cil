<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ログ機能のユニットテスト
 */
class LoggerTest extends TestCase
{
    private string $testLogFile;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/functions/logger.php';

        // テスト用のログファイルパス（Loggerのデフォルトprefixは'app'）
        $this->testLogFile = sys_get_temp_dir() . '/app-' . date('Y-m-d') . '.log';

        // 既存のテストログファイルを削除
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    protected function tearDown(): void
    {
        // テスト後にログファイルを削除
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        parent::tearDown();
    }

    public function testLoggerInstance(): void
    {
        $logger = \Logger::getInstance();
        $this->assertInstanceOf(\Logger::class, $logger);

        // シングルトンパターンの確認
        $logger2 = \Logger::getInstance();
        $this->assertSame($logger, $logger2);
    }

    public function testLogLevels(): void
    {
        $this->assertEquals(0, \Logger::LEVEL_DEBUG);
        $this->assertEquals(1, \Logger::LEVEL_INFO);
        $this->assertEquals(2, \Logger::LEVEL_WARNING);
        $this->assertEquals(3, \Logger::LEVEL_ERROR);
        $this->assertEquals(4, \Logger::LEVEL_CRITICAL);
    }

    public function testLogInfo(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());

        $result = $logger->info('Test info message');
        $this->assertTrue($result);

        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[INFO]', $logContent);
        $this->assertStringContainsString('Test info message', $logContent);
    }

    public function testLogWarning(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());

        $result = $logger->warning('Test warning message');
        $this->assertTrue($result);

        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[WARNING]', $logContent);
        $this->assertStringContainsString('Test warning message', $logContent);
    }

    public function testLogError(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());

        $result = $logger->error('Test error message');
        $this->assertTrue($result);

        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('[ERROR]', $logContent);
        $this->assertStringContainsString('Test error message', $logContent);
    }

    public function testLogWithContext(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());

        $context = ['user_id' => 123, 'action' => 'login'];
        $result = $logger->info('User action', $context);
        $this->assertTrue($result);

        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('user_id', $logContent);
        $this->assertStringContainsString('123', $logContent);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());
        $logger->setMinLevel(\Logger::LEVEL_WARNING);

        // DEBUGとINFOは記録されない
        $logger->debug('Debug message');
        $logger->info('Info message');

        // WARNING以上は記録される
        $logger->warning('Warning message');

        $logContent = file_exists($this->testLogFile) ? file_get_contents($this->testLogFile) : '';
        $this->assertStringNotContainsString('Debug message', $logContent);
        $this->assertStringNotContainsString('Info message', $logContent);
        $this->assertStringContainsString('Warning message', $logContent);

        // レベルをリセット
        $logger->setMinLevel(\Logger::LEVEL_DEBUG);
    }

    public function testJsonLogFormat(): void
    {
        $logger = \Logger::getInstance();
        $logger->setLogDirectory(sys_get_temp_dir());
        $logger->setFormat('json');

        $logger->info('JSON format test', ['key' => 'value']);

        $logContent = file_get_contents($this->testLogFile);
        $lines = array_filter(explode("\n", trim($logContent)));
        $lastLine = end($lines);

        $decoded = json_decode($lastLine, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('INFO', $decoded['level']);
        $this->assertEquals('JSON format test', $decoded['message']);

        // フォーマットをリセット
        $logger->setFormat('text');
    }
}
