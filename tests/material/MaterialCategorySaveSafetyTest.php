<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaterialCategorySaveSafetyTest extends TestCase
{
    private string $modelSource;
    private string $controllerSource;

    protected function setUp(): void
    {
        $this->modelSource = file_get_contents(
            __DIR__ . '/../../app/material/model/basic/material/cat.php'
        );
        $this->controllerSource = file_get_contents(
            __DIR__ . '/../../app/material/controller/api/material/cat.php'
        );
    }

    public function testParentCountChangesOnlyForCreateOrActualParentChange(): void
    {
        $this->assertStringContainsString(
            '$parent_changed = $is_update && $old_parent_id !== $new_parent_id;',
            $this->modelSource
        );
        $this->assertStringContainsString(
            'if($new_parent_id !== 0 && (!$is_update || $parent_changed))',
            $this->modelSource
        );
    }

    public function testExpectedLastModifyUsesConditionalModelUpdateAndAffectedRows(): void
    {
        $this->assertStringContainsString(
            "['cat_id' => \$catId, 'last_modify' => (int)\$expectedLastModify]",
            $this->modelSource
        );
        $this->assertStringContainsString('$this->db->affect_row()', $this->modelSource);
        $this->assertStringContainsString(
            '$objCat->saveWithExpectedLastModify($saveData, $expectedLastModify)',
            $this->controllerSource
        );
        $this->assertStringContainsString("'code' => 'LAST_MODIFY_CONFLICT'", $this->controllerSource);
    }
}
