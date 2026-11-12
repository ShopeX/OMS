<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('OMS_TEST_ROOT')) {
    define('OMS_TEST_ROOT', realpath(dirname(__DIR__, 2)) ?: getcwd());
}

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', OMS_TEST_ROOT);
}

if (!defined('APP_DIR')) {
    define('APP_DIR', ROOT_DIR . '/app');
}

require_once ROOT_DIR . '/app/base/autoload.php';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/app/base/defined.php';
require_once ROOT_DIR . '/app/base/kernel.php';

/**
 * Console Allocate API Controller Tests
 *
 * Phase 3 (Backend) - finder-to-vue migration
 * Tests normalizeFilter, toolbar actions, detail endpoints
 */
class ConsoleAllocateApiTest extends TestCase
{
    private static bool $bootstrapped = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$bootstrapped) {
            return;
        }

        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            $this->fail('Unable to resolve project root for test bootstrap');
        }

        chdir($root);

        if (!defined('OMS_TEST_ROOT')) {
            define('OMS_TEST_ROOT', $root);
        }
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', $root);
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', $root . '/app');
        }

        self::$bootstrapped = true;
    }

    /**
     * Test that normalizeFilter injects type_id and branch filter based on io param
     * io=0 -> type_id=40 (ALLOC_LIBRARY, 调拨出库)
     * io=1 -> type_id=4 (ALLOC_STORAGE, 调拨入库)
     */
    public function testNormalizeFilterInjectsTypeIdAndBranchForIo0(): void
    {
        $controller = $this->createControllerProbe(0);
        $controller->setBranchFilter(['branch_id|in' => [1, 2]]);

        $filter = $controller->publicNormalizeFilter([]);

        $this->assertArrayHasKey('type_id', $filter);
        $this->assertSame(40, $filter['type_id']);
        $this->assertSame([1, 2], $filter['branch_id|in']);
    }

    public function testNormalizeFilterInjectsTypeIdAndBranchForIo1(): void
    {
        $controller = $this->createControllerProbe(1);
        $controller->setBranchFilter(['branch_id|in' => [3]]);

        $filter = $controller->publicNormalizeFilter([]);

        $this->assertArrayHasKey('type_id', $filter);
        $this->assertSame(4, $filter['type_id']);
        $this->assertSame([3], $filter['branch_id|in']);
    }

    public function testCustomFilterKeepsUserFilters(): void
    {
        $controller = $this->createControllerProbe(0);
        $controller->setBranchFilter([]);

        $filter = $controller->publicNormalizeFilter(['iso_bn' => 'ISO123']);

        $this->assertSame('ISO123', $filter['iso_bn']);
        $this->assertSame(40, $filter['type_id']);
    }

    /**
     * Test toolbar actions includes push-to-WMS with correct submit URL and confirm text
     */
    public function testToolbarActionsIncludesPushToWmsForIo0(): void
    {
        $controller = $this->createControllerProbe(0);

        $actions = $controller->publicBuildToolbarActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        $pushAction = null;
        foreach ($actions as $action) {
            if (isset($action['label']) && $action['label'] === '推送单据至WMS') {
                $pushAction = $action;
                break;
            }
        }

        $this->assertNotNull($pushAction, 'Push to WMS action not found');
        $this->assertArrayHasKey('submit', $pushAction);
        $this->assertStringContainsString('batch_sync', $pushAction['submit']);
        $this->assertStringContainsString('io=0', $pushAction['submit']);
        $this->assertArrayHasKey('confirm', $pushAction);
        $this->assertStringContainsString('仓储', $pushAction['confirm']);
        $this->assertArrayHasKey('target', $pushAction);
        $this->assertSame('refresh', $pushAction['target']);
    }

    public function testToolbarActionsIncludesPushToWmsForIo1(): void
    {
        $controller = $this->createControllerProbe(1);

        $actions = $controller->publicBuildToolbarActions();

        $pushAction = null;
        foreach ($actions as $action) {
            if (isset($action['label']) && $action['label'] === '推送单据至WMS') {
                $pushAction = $action;
                break;
            }
        }

        $this->assertNotNull($pushAction);
        $this->assertStringContainsString('io=1', $pushAction['submit']);
    }

    /**
     * Test detail endpoints return HTML string (stub implementation)
     */
    public function testDetailBasicReturnsHtml(): void
    {
        $controller = $this->createControllerProbe(0);

        $_GET['iso_id'] = 123;
        $result = $controller->detail_basic();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
        $this->assertStringContainsString('detail-basic', $result['html']);
        $this->assertStringContainsString('123', $result['html']);
    }

    public function testDetailItemReturnsHtml(): void
    {
        $controller = $this->createControllerProbe(0);

        $_GET['iso_id'] = 456;
        $result = $controller->detail_item();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
        $this->assertStringContainsString('detail-item', $result['html']);
    }

    public function testDetailOplogReturnsHtml(): void
    {
        $controller = $this->createControllerProbe(0);

        $_GET['iso_id'] = 789;
        $result = $controller->detail_oplog();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
        $this->assertStringContainsString('detail-oplog', $result['html']);
    }

    public function testDetailUsefulReturnsHtml(): void
    {
        $controller = $this->createControllerProbe(0);

        $_GET['iso_id'] = 101;
        $result = $controller->detail_useful();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
        $this->assertStringContainsString('detail-useful', $result['html']);
    }

    /**
     * Test resolveTypeId returns correct values
     */
    public function testResolveTypeIdReturnsLibraryForIo0(): void
    {
        $controller = $this->createControllerProbe(0);

        $typeId = $controller->publicResolveTypeId(0);

        $this->assertSame(40, $typeId);
    }

    public function testResolveTypeIdReturnsStorageForIo1(): void
    {
        $controller = $this->createControllerProbe(0);

        $typeId = $controller->publicResolveTypeId(1);

        $this->assertSame(4, $typeId);
    }

    /**
     * Test getTypeName returns correct type names
     */
    public function testGetTypeNameReturnsCorrectNames(): void
    {
        $controller = $this->createControllerProbe(0);

        $this->assertSame('调拨出库', $controller->getTypeName(40));
        $this->assertSame('调拨入库', $controller->getTypeName(4));
        $this->assertSame('未知', $controller->getTypeName(999));
    }

    /**
     * Create controller probe for testing
     *
     * @param int $io io parameter
     * @return ConsoleAllocateApiControllerProbe
     */
    private function createControllerProbe(int $io): ConsoleAllocateApiControllerProbe
    {
        $controller = new ConsoleAllocateApiControllerProbe();
        $controller->setIo($io);
        return $controller;
    }
}

/**
 * Test probe extending the controller to expose protected methods
 * Bypasses parent constructor to avoid framework initialization in unit tests
 */
class ConsoleAllocateApiControllerProbe extends console_ctl_api_iostockorder_allocate
{
    protected $io;
    private array $branchFilter = [];

    public function __construct()
    {
    }

    public function setIo(int $io): void
    {
        $this->io = $io;
    }

    public function setBranchFilter(array $filter): void
    {
        $this->branchFilter = $filter;
    }

    public function publicNormalizeFilter(array $filter): array
    {
        $customFilter = $this->getCustomFilterClosure();
        $result = $customFilter($filter);
        return array_merge($filter, $result);
    }

    public function publicBuildToolbarActions(): array
    {
        return $this->buildToolbarActions();
    }

    public function publicResolveTypeId(int $io): int
    {
        return $this->resolveTypeId($io);
    }

    public function publicGetUserBranchFilter(): array
    {
        return $this->getUserBranchFilter();
    }

    protected function getUserBranchFilter(): array
    {
        return $this->branchFilter;
    }

    protected function renderLegacyDetail(string $type, int $isoId): string
    {
        return sprintf('<div class="detail-%s">%d</div>', $type, $isoId);
    }
}
