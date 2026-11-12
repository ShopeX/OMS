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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * View Tab 计数契约（SCRUM-7775）。
 *
 * 背景：base_db_model::viewcount() 在当前框架里返回的是 legacy 懒加载 **HTML 占位串**
 * （真实数字由浏览器异步请求 desktop&ctl=default&act=getviewcount 填入），不是计数数字。
 * 而 desktop_finder_vue_tabs::getCount() 声明返回 ?int ⇒ 直接返回该字符串会抛 TypeError，
 * 又被同一个方法里的 catch(Throwable) 吞成 0 ⇒ 所有 Vue finder 页的 Tab 计数恒为 0。
 * 本测试锁住「拿到非数值时回退 $model->count()」这一修复。
 */
if (!class_exists('app', false)) {
    class app
    {
        public static mixed $tabStyle = '2';

        public static function get(string $name): object
        {
            return new class {
                public function getConf(string $key): mixed
                {
                    return app::$tabStyle;
                }
            };
        }
    }
}

if (!class_exists('desktop_finder_vue_context', false)) {
    class desktop_finder_vue_context
    {
        public function __construct(
            private array $views,
            private object $model,
            private array $baseFilter = []
        ) {
        }

        public function buildViewDefinitions(): array
        {
            return $this->views;
        }

        public function getModel(): object
        {
            return $this->model;
        }

        public function getBaseFilter(): array
        {
            return $this->baseFilter;
        }

        public function getObjectName(): string
        {
            return 'taoexlib_mdl_log';
        }

        public function getCurrentUserId(): int
        {
            return 0;
        }
    }
}

#[RunTestsInSeparateProcesses]
final class FinderVueTabsCountContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        app::$tabStyle = '2';
    }

    /** 模型桩：viewcount() 返回调用方指定值（模拟 legacy HTML 串 / 数值），count() 按 filter 查表。 */
    private function modelStub(mixed $viewcountReturn, array $counts = []): object
    {
        return new class ($viewcountReturn, $counts) {
            public function __construct(
                private mixed $viewcountReturn,
                private array $counts
            ) {
            }

            public function viewcount(array $filter): mixed
            {
                return $this->viewcountReturn;
            }

            public function count(array $filter): int
            {
                return (int) ($this->counts[json_encode($filter)] ?? 0);
            }
        };
    }

    private function runHandler(array $views, object $model, array $baseFilter = []): array
    {
        require_once $this->root . '/app/desktop/lib/finder/vue/tabs.php';

        $context = new desktop_finder_vue_context($views, $model, $baseFilter);

        return (new desktop_finder_vue_tabs($context))->handle([]);
    }

    public function testLegacyHtmlViewcountFallsBackToRealCountWithBaseFilterMerged(): void
    {
        $legacyHtml = '<viewconut id="3095640147">loading...<script>new Request.JSON({url:'
            . "'index.php?app=desktop&ctl=default&act=getviewcount'})</script></viewconut>";

        $model = $this->modelStub($legacyHtml, [
            json_encode(['shop_id' => 'S1']) => 32,
            json_encode(['shop_id' => 'S1', 'status' => 1]) => 17,
            json_encode(['shop_id' => 'S1', 'status' => 0]) => 15,
        ]);

        $result = $this->runHandler([
            ['id' => 'all', 'label' => '全部', 'filter' => []],
            ['id' => 'success', 'label' => '发送成功', 'filter' => ['status' => 1]],
            ['id' => 'fail', 'label' => '发送失败', 'filter' => ['status' => 0]],
        ], $model, ['shop_id' => 'S1']);

        $addons = array_column($result['tabs'], 'addon');

        // 关键断言：非数值 viewcount ⇒ 回退 count()（含 baseFilter 合并），而不是被吞成 0
        $this->assertSame([32, 17, 15], $addons);
    }

    public function testNumericViewcountValueIsCastToInt(): void
    {
        $model = $this->modelStub('42', [json_encode([]) => 32]);

        $result = $this->runHandler([
            ['id' => 'all', 'label' => '全部', 'filter' => []],
        ], $model);

        $this->assertSame(42, $result['tabs'][0]['addon']);
    }

    public function testNeedCacheFalseUsesModelCount(): void
    {
        $model = $this->modelStub('123', [json_encode(['status' => 0]) => 7]);

        $result = $this->runHandler([
            ['id' => 'fail', 'label' => '发送失败', 'filter' => ['status' => 0], 'needCache' => false],
        ], $model);

        $this->assertSame(7, $result['tabs'][0]['addon']);
    }

    public function testTabStyleOneKeepsCounterDisabled(): void
    {
        app::$tabStyle = '1';

        $model = $this->modelStub('42', [json_encode([]) => 32]);

        $result = $this->runHandler([
            ['id' => 'all', 'label' => '全部', 'filter' => []],
        ], $model);

        $this->assertNull($result['tabs'][0]['addon'], 'desktop.finder.tab=1 时维持不显示计数（配置关闭）');
    }
}
