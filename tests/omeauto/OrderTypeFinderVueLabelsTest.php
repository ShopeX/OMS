<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('kernel', false)) {
    class kernel
    {
        public static function database()
        {
            return null;
        }
    }
}

if (!class_exists('app', false)) {
    class app
    {
        public static function get($name)
        {
            return new class {
                public function model($name)
                {
                    return new class {
                        public function getList(...$args)
                        {
                            return [];
                        }
                    };
                }
            };
        }
    }
}

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class OrderTypeFinderVueLabelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/app/omeauto/lib/finder/order/type.php';
    }

    public function testSetVueModeRestoresLabelsBlankedByApiCtl(): void
    {
        $_GET['ctl'] = 'api_order_type';

        $finder = new omeauto_finder_order_type();

        $this->assertSame('', $finder->column_autoconfirm);
        $this->assertSame('', $finder->column_autodispatch);
        $this->assertSame('', $finder->column_autobranch);

        $finder->setVueMode(true);

        $this->assertSame('自动审单规则', $finder->column_autoconfirm);
        $this->assertSame('自动分配规则', $finder->column_autodispatch);
        $this->assertSame('发货仓库', $finder->column_autobranch);
    }
}
