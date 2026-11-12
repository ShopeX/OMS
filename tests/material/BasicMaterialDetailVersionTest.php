<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class BasicMaterialDetailVersionTest extends TestCase
{
    public function testDetailReturnsPersistedLastModifiedVersion(): void
    {
        $this->bootstrapController();
        $_GET = ['p' => [7]];

        $controller = new material_ctl_api_material_basic();
        $controller->detail();

        $this->assertSame(1700000001, $controller->response['last_modified'] ?? null);
    }

    private function bootstrapController(): void
    {
        if (!class_exists('desktop_ctl_api_base', false)) {
            eval(<<<'PHP'
class desktop_ctl_api_base {
    public array $response = [];
    protected function respond($data, $status = 200) { $this->response = $data; }
    protected function fail($message, $status = 400, $extra = []) { throw new RuntimeException($message); }
}
class DetailHarnessModel {
    public function __construct(private string $name) {}
    public function dump($filter = null, $fields = '*') {
        if ($this->name === 'basic_material') return ['bm_id' => 7, 'type' => '3', 'material_name' => '物料', 'last_modified' => 1700000001];
        return false;
    }
    public function getList($fields = '*', $filter = [], $offset = 0, $limit = null) { return []; }
    public function getlist($fields = '*', $filter = [], $offset = 0, $limit = null) { return []; }
}
class DetailHarnessApp {
    public function __construct(private string $name) {}
    public function model($name) { return new DetailHarnessModel($name); }
}
class app { public static function get($name) { return new DetailHarnessApp($name); } }
class material_codebase { public static function getBarcodeType() { return 'material'; } }
PHP);
        }
        require_once __DIR__ . '/../../app/material/controller/api/material/basic.php';
    }
}
