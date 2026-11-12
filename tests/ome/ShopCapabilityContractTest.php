<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('ome_shop_type', false)) {
    class ome_shop_type
    {
        public static function get_shop_type(): array
        {
            return ['taobao' => '淘宝店', 'jd' => '京东店'];
        }
    }
}

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class ShopCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/shop.php');
    }

    public function testCapabilityListsShops(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function getList(string $cols, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['shop_id' => 1, 'shop_bn' => 'SHOP-A', 'name' => '店铺A'],
                    ['shop_id' => 2, 'shop_bn' => 'SHOP-B', 'name' => '店铺B'],
                ];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );
        $result = $capability->list();

        $this->assertSame(2, count($result['rows']));
        $this->assertSame('店铺A', $result['rows'][0]['name']);
        $this->assertSame('SHOP-B', $result['rows'][1]['shop_bn']);
    }

    public function testCapabilityAddsOrgFilterWhenOrganizationIdsProvided(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $capturedFilter = null;
        $shopModel = new class($capturedFilter) {
            private $capturedFilter;

            public function __construct(&$capturedFilter)
            {
                $this->capturedFilter = &$capturedFilter;
            }

            public function getList(string $cols, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [['shop_id' => 1, 'shop_bn' => 'SHOP-A', 'name' => '店铺A', 'org_id' => 12]];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );

        $result = $capability->list(['12', '18']);

        $this->assertSame(['12', '18'], $capturedFilter['org_id|in']);
        $this->assertSame(1, $capturedFilter['s_type']);
        $this->assertSame('shopyjdf', $capturedFilter['delivery_mode|notin']);
        $this->assertCount(1, $result['rows']);
    }

    public function testCapabilityReturnsEmptyRowsWhenOrganizationIdsResolveToEmptyList(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $called = false;
        $shopModel = new class($called) {
            private $called;

            public function __construct(&$called)
            {
                $this->called = &$called;
            }

            public function getList(string $cols, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->called = true;
                return [['shop_id' => 1]];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );

        $result = $capability->list([]);

        $this->assertFalse($called);
        $this->assertSame([], $result['rows']);
    }

    public function testCapabilityReturnsShopDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function dump($shopId): array
            {
                return ['shop_id' => 3, 'shop_bn' => 'SHOP-DETAIL', 'name' => '店铺详情'];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );
        $detail = $capability->detail(3);

        $this->assertSame(3, $detail['shop_id']);
        $this->assertSame('店铺详情', $detail['name']);
    }

    public function testCapabilityRejectsShopDetailOutsideOrganizationScope(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function dump($shopId): array
            {
                return ['shop_id' => $shopId, 'shop_bn' => 'SHOP-DETAIL', 'name' => '店铺详情', 'org_id' => 18];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );

        $this->assertNull($capability->detail(3, ['12']));
    }

    public function testCapabilityReturnsAddFormConfig(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function dump($shopId): array
            {
                return [];
            }
        };
        $orgModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 1, 'name' => '总部']];
            }
        };
        $shopService = new class {
            public function getcols(): array
            {
                return [['col_key' => 'erp_shop_code']];
            }
        };
        $authConfig = new class {
            public function getAdapterList(): array
            {
                return ['matrixonline' => ['label' => '商派矩阵']];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            $orgModel,
            $shopService,
            $authConfig,
            new class {
                public function getlist(string $fields, array $filter = []): array
                {
                    return [];
                }
            }
        );
        $form = $capability->addForm();

        $this->assertArrayHasKey('shop', $form);
        $this->assertArrayHasKey('options', $form);
        $this->assertSame('matrixonline', $form['shop']['config']['adapter']);
        $this->assertSame('总部', $form['options']['orgs'][0]['label']);
    }

    public function testCapabilityReturnsEditFormConfigWithDecodedConfig(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function dump($shopId): array
            {
                return ['shop_id' => 3, 'config' => serialize(['adapter' => 'matrixonline', 'password' => 'encoded'])];
            }
            public function aes_decode(string $value): string
            {
                return $value === 'encoded' ? 'decoded-password' : $value;
            }
        };
        $orgModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 1, 'name' => '总部']];
            }
        };
        $shopService = new class {
            public function getcols(): array
            {
                return [['col_key' => 'erp_shop_code']];
            }
        };
        $authConfig = new class {
            public function getAdapterList(): array
            {
                return ['matrixonline' => ['label' => '商派矩阵']];
            }
        };
        $propsModel = new class {
            public function getlist(string $fields, array $filter = []): array
            {
                return [['props_col' => 'erp_shop_code', 'props_value' => 'ERP001']];
            }
        };

        $capability = new ome_capability_shop($shopModel, $orgModel, $shopService, $authConfig, $propsModel);
        $form = $capability->editForm(3);

        $this->assertSame(3, $form['shop']['shop_id']);
        $this->assertSame('decoded-password', $form['shop']['config']['password']);
        $this->assertSame('ERP001', $form['customcols'][0]['col_value']);
    }

    public function testCapabilityRejectsEditFormOutsideOrganizationScope(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';

        $shopModel = new class {
            public function dump($shopId): array
            {
                return ['shop_id' => $shopId, 'org_id' => 18, 'config' => serialize(['adapter' => 'matrixonline'])];
            }
        };

        $capability = new ome_capability_shop(
            $shopModel,
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getcols(): array { return []; }
            },
            new class {
                public function getAdapterList(): array { return []; }
            },
            new class {
                public function getlist(string $fields, array $filter = []): array { return []; }
            }
        );

        $this->assertNull($capability->editForm(3, ['12']));
    }
}
