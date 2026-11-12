<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * PJ3270-48 组合(type 2)销售物料导入 - 分摊基数测试
 *
 * 覆盖行为矩阵:
 *   A 占比已填合计=100     -> 直接采用,不触发分摊(guard: $itemRateSum == 0)
 *   C 占比全空+贡献价全空   -> amount 优先,amount 合计为 0 时回落 quantity(旧模板兼容,行为不变)
 *   D 占比全空+贡献价有填   -> 按组合贡献价 rate_price 分摊(本任务新增)
 *   E 占比全空+贡献价部分填 -> 填写行按 rate_price 分摊,未填行 rate=0(原生语义)
 *
 * 两个层次:
 *   1) testInsertType2PorthFieldPriorityContract 对 import.php _insert type 2 分支做源码契约断言(改动驱动: 改前红/改后绿)
 *   2) testScenario* 对 ome_order::calculate_part_porth 做功能断言(钉死分摊数学语义,防公共函数语义漂移)
 */
class CombineRatePriceAllocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 引入vendor autoload
        require_once __DIR__ . '/../../vendor/autoload.php';

        // 在 setUp 方法中引入依赖文件
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    /**
     * 与 _insert type 2 分支调用形态一致(part_total=100, part_field=rate)
     * minuend_field 生产侧未传(等价 null), 测试显式置 null 避免未定义索引告警
     */
    private function allocateBy(array $items, string $porthField): array
    {
        $options = array(
            'part_total'    => 100,
            'part_field'    => 'rate',
            'porth_field'   => $porthField,
            'minuend_field' => null,
        );
        return kernel::single('ome_order')->calculate_part_porth($items, $options);
    }

    /**
     * bcmath 2 位小数求和, 断言分摊后合计恒等于 100.00
     */
    private function sumRate(array $items): string
    {
        $sum = '0';
        foreach ($items as $item) {
            $sum = bcadd($sum, (string)$item['rate'], 2);
        }
        return $sum;
    }

    // ---------- 场景 D: 占比全空 + 贡献价全部填写 ----------

    public function testScenarioDAllocateByRatePrice(): void
    {
        // amount 故意与 rate_price 不成比例(10/999/1), 证明分摊基数是贡献价而非金额
        $items = [
            ['bm_id'=>101, 'quantity'=>1, 'cost'=>10.0,  'amount'=>10.0,  'rate'=>0.0, 'rate_price'=>10.0],
            ['bm_id'=>102, 'quantity'=>1, 'cost'=>999.0, 'amount'=>999.0, 'rate'=>0.0, 'rate_price'=>20.0],
            ['bm_id'=>103, 'quantity'=>1, 'cost'=>1.0,   'amount'=>1.0,   'rate'=>0.0, 'rate_price'=>70.0],
        ];

        $result = $this->allocateBy($items, 'rate_price');

        $this->assertEqualsWithDelta(10.0, (float)$result[0]['rate'], 0.0001);
        $this->assertEqualsWithDelta(20.0, (float)$result[1]['rate'], 0.0001);
        $this->assertEqualsWithDelta(70.0, (float)$result[2]['rate'], 0.0001);
        $this->assertSame('100.00', $this->sumRate($result));
    }

    public function testScenarioDEqualRatePriceTailCorrection(): void
    {
        $items = [
            ['bm_id'=>201, 'quantity'=>1, 'cost'=>0.0, 'amount'=>0.0, 'rate'=>0.0, 'rate_price'=>10.0],
            ['bm_id'=>202, 'quantity'=>1, 'cost'=>0.0, 'amount'=>0.0, 'rate'=>0.0, 'rate_price'=>10.0],
            ['bm_id'=>203, 'quantity'=>1, 'cost'=>0.0, 'amount'=>0.0, 'rate'=>0.0, 'rate_price'=>10.0],
        ];

        $result = $this->allocateBy($items, 'rate_price');

        // 均分 33.33 x3 差 0.01, 尾差修正加到首个参与分摊的行
        $this->assertEqualsWithDelta(33.34, (float)$result[0]['rate'], 0.0001);
        $this->assertEqualsWithDelta(33.33, (float)$result[1]['rate'], 0.0001);
        $this->assertEqualsWithDelta(33.33, (float)$result[2]['rate'], 0.0001);
        $this->assertSame('100.00', $this->sumRate($result));
    }

    // ---------- 场景 E: 占比全空 + 贡献价部分填写 ----------

    public function testScenarioEPartialRatePrice(): void
    {
        $items = [
            ['bm_id'=>301, 'quantity'=>1, 'cost'=>1.0, 'amount'=>1.0, 'rate'=>0.0, 'rate_price'=>30.0],
            ['bm_id'=>302, 'quantity'=>1, 'cost'=>1.0, 'amount'=>1.0, 'rate'=>0.0, 'rate_price'=>70.0],
            ['bm_id'=>303, 'quantity'=>1, 'cost'=>1.0, 'amount'=>1.0, 'rate'=>0.0, 'rate_price'=>0.0], // 未填贡献价
        ];

        $result = $this->allocateBy($items, 'rate_price');

        // 填写的两行按贡献价分摊, 未填行原生语义 rate=0
        $this->assertEqualsWithDelta(30.0, (float)$result[0]['rate'], 0.0001);
        $this->assertEqualsWithDelta(70.0, (float)$result[1]['rate'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float)$result[2]['rate'], 0.0001);
        $this->assertSame('100.00', $this->sumRate($result));
    }

    // ---------- 场景 C: 占比全空 + 贡献价全空(兼容旧模板, 行为不变) ----------

    public function testScenarioCFallbackToAmount(): void
    {
        $items = [
            ['bm_id'=>401, 'quantity'=>5, 'cost'=>20.0, 'amount'=>100.0, 'rate'=>0.0, 'rate_price'=>0.0],
            ['bm_id'=>402, 'quantity'=>5, 'cost'=>60.0, 'amount'=>300.0, 'rate'=>0.0, 'rate_price'=>0.0],
        ];

        $result = $this->allocateBy($items, 'amount');

        $this->assertEqualsWithDelta(25.0, (float)$result[0]['rate'], 0.0001);
        $this->assertEqualsWithDelta(75.0, (float)$result[1]['rate'], 0.0001);
        $this->assertSame('100.00', $this->sumRate($result));
    }

    public function testScenarioCFallbackToQuantity(): void
    {
        // 价格为 0 -> amount 合计为 0, 回落 quantity
        $items = [
            ['bm_id'=>501, 'quantity'=>1, 'cost'=>0.0, 'amount'=>0.0, 'rate'=>0.0, 'rate_price'=>0.0],
            ['bm_id'=>502, 'quantity'=>3, 'cost'=>0.0, 'amount'=>0.0, 'rate'=>0.0, 'rate_price'=>0.0],
        ];

        $result = $this->allocateBy($items, 'quantity');

        $this->assertEqualsWithDelta(25.0, (float)$result[0]['rate'], 0.0001);
        $this->assertEqualsWithDelta(75.0, (float)$result[1]['rate'], 0.0001);
        $this->assertSame('100.00', $this->sumRate($result));
    }

    // ---------- 源码契约: _insert type 2 分支的分摊基数优先级(改动驱动) ----------

    public function testInsertType2PorthFieldPriorityContract(): void
    {
        $src = file_get_contents(__DIR__ . '/../../app/material/lib/sales/material/import.php');
        $this->assertIsString($src);

        // 场景 A/B: 仅当占比合计为 0(全部未填)时才触发分摊; 已填合计=100 直接采用, 部分填≠100 由校验层报错
        $this->assertStringContainsString('if($itemRateSum == 0)', $src);

        // 场景 D/E/C: 分摊基数优先级 组合贡献价 -> 金额 -> 数量
        $this->assertStringContainsString(
            "'porth_field' => \$ratePriceSum > 0 ? 'rate_price' : (\$itemSum > 0 ? 'amount' : 'quantity'),",
            $src
        );

        // 贡献价按合计参与判定(而非逐行), 部分填写时整体切换 rate_price 模式
        $this->assertStringContainsString(
            "\$ratePriceSum = array_sum(array_column(\$items, 'rate_price'));",
            $src
        );
    }
}
