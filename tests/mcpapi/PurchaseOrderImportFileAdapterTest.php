<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PurchaseOrderImportFileAdapterTest extends TestCase
{
    private string $cwdBeforeTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        chdir(dirname(__DIR__, 2));
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', getcwd());
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::register_autoload();
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testUtf8CsvIsReadableByLegacyDesktopParser(): void
    {
        $csv = "*:CSCID,*:供应商,*:到货仓库\nI-001,MCP供应商验收,我的仓库\n";
        $legacyBytes = (new console_purchase_import_file_adapter())->toLegacyCsv($csv);
        $handle = fopen('php://temp', 'r+');
        self::assertIsResource($handle);
        fwrite($handle, $legacyBytes);
        rewind($handle);

        $contents = [];
        (new desktop_io_type_csv())->fgethandle($handle, $contents);
        fclose($handle);

        self::assertSame('*:供应商', $contents[0][1]);
        self::assertSame('*:到货仓库', $contents[0][2]);
        self::assertSame('MCP供应商验收', $contents[1][1]);
        self::assertSame('我的仓库', $contents[1][2]);
    }
}
