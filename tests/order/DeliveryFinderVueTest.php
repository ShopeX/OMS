<?php
declare(strict_types=1);

interface DeliveryFinderCustomFilterPublic
{
    public function publicCustomFilter(array $filter): array;
}

use PHPUnit\Framework\TestCase;

class DeliveryFinderVueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', realpath(dirname(__DIR__, 2)));
        }
        include_once ROOT_DIR . '/app/base/defined.php';
        include_once ROOT_DIR . '/app/base/kernel.php';
    }

    public function testFetchReturnsPaginatedList(): void
    {
        $finder = new class extends desktop_finder_vue_prototype {
            protected $sensitive_fields = ['ship_name'];

            protected function getSchema(): array
            {
                return [
                    'columns' => [
                        'delivery_bn' => ['label' => '发货单号', 'in_list' => true, 'default_in_list' => true],
                        'ship_name' => ['label' => '收货人', 'in_list' => true, 'default_in_list' => true, 'filtertype' => 'normal'],
                        'sale_time' => ['label' => '销售时间', 'in_list' => true, 'default_in_list' => false, 'filtertype' => 'time'],
                    ],
                ];
            }

            protected function getModel()
            {
                return new class {
                    public $lastFilter;
                    public $lastOrder;

                    public function getList($cols, $filter, $offset, $limit, $order)
                    {
                        $this->lastFilter = $filter;
                        $this->lastOrder = $order;
                        return [
                            ['delivery_bn' => 'BN001', 'ship_name' => '张三'],
                            ['delivery_bn' => 'BN002', 'ship_name' => '李四'],
                        ];
                    }

                    public function count($filter)
                    {
                        return 2;
                    }
                };
            }

            public function searchOptions(): array
            {
                return ['order_bn' => '订单号'];
            }
        };

        $result = $finder->fetch([
            'page' => 2,
            'page_size' => 10,
            'orderBy' => 'delivery_bn',
            'orderType' => 'asc',
            'filters' => ['org_id' => 100],
        ]);

        $this->assertSame(2, $result['total']);
        $this->assertSame(2, count($result['list']));
        $this->assertSame(2, $result['page']);
        $this->assertSame(10, $result['pageSize']);
        $this->assertArrayHasKey('ship_name_encrypted', $result['list'][0]);
    }

    public function testFetchFullIncludesMetadata(): void
    {
        $finder = new class extends desktop_finder_vue_prototype {
            protected function getSchema(): array
            {
                return [
                    'columns' => [
                        'delivery_bn' => ['label' => '发货单号', 'in_list' => true, 'default_in_list' => true, 'filtertype' => 'yes', 'filterdefault' => true],
                        'ship_area' => ['label' => '收货地区', 'in_list' => true, 'default_in_list' => true, 'filtertype' => 'normal'],
                    ],
                ];
            }

            protected function getModel()
            {
                return new class {
                    public function getList($cols, $filter, $offset, $limit, $order)
                    {
                        return [];
                    }

                    public function count($filter)
                    {
                        return 0;
                    }
                };
            }

            public function searchOptions(): array
            {
                return ['order_bn' => '订单号'];
            }
        };

        $result = $finder->fetchFull([
            'filters' => [],
        ]);

        $this->assertArrayHasKey('columns', $result);
        $this->assertCount(2, $result['columns']);
        $this->assertArrayHasKey('filters', $result);
        $this->assertSame('yes', $result['filters'][0]['type']);
        $this->assertSame(['order_bn' => '订单号'], $result['searchOptions']);
        $this->assertSame('all', $result['views'][0]['id']);
    }

    public function testCustomFilterTranslatesOrderBn(): void
    {
        $finder = $this->createDeliveryFinderStub([
            ['delivery_id' => 101],
            ['delivery_id' => 102],
            ['delivery_id' => 101],
        ]);

        $filter = $finder->publicCustomFilter(['order_bn' => 'BN888', 'org_id' => 88]);

        $this->assertArrayNotHasKey('order_bn', $filter);
        $this->assertSame([101, 102], $filter['delivery_id|in']);
        $this->assertSame(88, $filter['org_id']);

        $empty = $finder->publicCustomFilter(['order_bn' => 'BN000']);
        $this->assertSame(['delivery_id|in' => ['-1']], $empty);
    }

    private function createDeliveryFinderStub(array $items): DeliveryFinderCustomFilterPublic
    {
        return new class($items) extends sales_finder_vue_delivery implements DeliveryFinderCustomFilterPublic {
            private $items;

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function publicCustomFilter(array $filter): array
            {
                return $this->customFilter($filter);
            }

            protected function getOrderItemModel()
            {
                $items = $this->items;
                return new class($items) {
                    private $items;

                    public function __construct(array $items)
                    {
                        $this->items = $items;
                    }

                    public function getList($cols, $filter)
                    {
                        if (isset($filter['order_bn']) && $filter['order_bn'] === 'BN000') {
                            return [];
                        }
                        return $this->items;
                    }
                };
            }
        };
    }
}
