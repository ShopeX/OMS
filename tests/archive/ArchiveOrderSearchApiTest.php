<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('OMS_TEST_ROOT')) {
    define('OMS_TEST_ROOT', realpath(dirname(__DIR__, 2)));
}

if (!defined('APP_DIR')) {
    define('APP_DIR', OMS_TEST_ROOT . '/app');
}

include_once OMS_TEST_ROOT . '/config/config.php';
include_once ROOT_DIR . '/app/base/defined.php';
include_once ROOT_DIR . '/app/base/kernel.php';

class ArchiveOrderSearchApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testNormalizeFilterDefaultsInjectsRangeAndFlag(): void
    {
        $controller = $this->createControllerProbe();
        $range = ['from' => '2024-01-01', 'to' => '2024-01-31'];

        $defaults = $controller->publicNormalizeFilterDefaults([], $range);

        $this->assertSame('2024-01-01', $defaults['time_from']);
        $this->assertSame('2024-01-31', $defaults['time_to']);
        $this->assertSame('1', $defaults['flag']);

        $preserved = $controller->publicNormalizeFilterDefaults([
            'time_from' => '2024-02-01',
            'time_to' => '2024-02-28',
            'flag' => '0',
        ], $range);

        $this->assertSame('2024-02-01', $preserved['time_from']);
        $this->assertSame('2024-02-28', $preserved['time_to']);
        $this->assertSame('0', $preserved['flag']);
    }

    public function testBuildBaseQueryStringIncludesTimeAndFlag(): void
    {
        $controller = $this->createControllerProbe();
        $query = $controller->publicBuildBaseQueryString([
            'time_from' => '2024-03-01',
            'time_to' => '2024-03-31',
            'flag' => '1',
        ]);

        parse_str($query, $parsed);
        $this->assertSame('2024-03-01', $parsed['time_from']);
        $this->assertSame('2024-03-31', $parsed['time_to']);
        $this->assertSame('1', $parsed['flag']);
    }

    public function testBuildToolbarActionsMatchesLegacyMetadata(): void
    {
        $controller = $this->createControllerProbe();
        $actions = $controller->publicBuildToolbarActions();

        $this->assertCount(3, $actions);
        $this->assertSame('index.php?app=archive&ctl=return&act=create', $actions[0]['href']);
        $this->assertStringContainsString('width:600', $actions[0]['target']);
        $this->assertSame('index.php?app=archive&ctl=order&act=index', $actions[1]['href']);
    }

    private function createControllerProbe(): ArchiveOrderSearchControllerProbe
    {
        return new ArchiveOrderSearchControllerProbe(app::get('archive'));
    }
}

class ArchiveOrderSearchControllerProbe extends archive_ctl_api_order_search
{
    public function __construct()
    {
    }

    public function publicNormalizeFilterDefaults(array $filter, array $range): array
    {
        return $this->normalizeFilterDefaults($filter, $range);
    }

    public function publicBuildBaseQueryString(array $filter): string
    {
        return $this->buildBaseQueryString($filter);
    }

    public function publicBuildToolbarActions(): array
    {
        return $this->buildToolbarActions();
    }
}
