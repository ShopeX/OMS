<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutoconfirmEffectEvidenceCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/omeauto/lib/capability/effect/evidence.php');
    }

    public function testCapabilityParsesRuleLinkedAutoConfirmEvidenceOnly(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/effect/evidence.php';

        $calls = [];
        $operationLogModel = new class($calls) {
            public array $calls = [];

            public function __construct(& $calls)
            {
                $this->calls = & $calls;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->calls[] = $filter;

                if (($filter['operation'] ?? null) === 'order_confirm@ome') {
                    return [
                        [
                            'log_id' => 101,
                            'obj_id' => '9001',
                            'operation' => 'order_confirm@ome',
                            'operate_time' => strtotime('2026-07-24 10:00:00'),
                            'memo' => '订单确认,操作员:系统,审单规则:规则A(11),仓库规则:仓库A(2)(发货单号：<a href="index.php?app=ome&ctl=admin_receipts_print&act=show_delivery_items&id=1" target="_blank">D20260724001</a>)',
                        ],
                        [
                            'log_id' => 102,
                            'obj_id' => '9002',
                            'operation' => 'order_confirm@ome',
                            'operate_time' => strtotime('2026-07-24 10:30:00'),
                            'memo' => '订单确认(发货单号：D20260724001)',
                        ],
                        [
                            'log_id' => 103,
                            'obj_id' => '9004',
                            'operation' => 'order_confirm@ome',
                            'operate_time' => strtotime('2026-07-25 00:00:01'),
                            'memo' => '订单确认,操作员:系统,审单规则:规则A(11),仓库规则:仓库A(2)',
                        ],
                    ];
                }

                return [
                    [
                        'log_id' => 201,
                        'obj_id' => '9003',
                        'operation' => 'order_dispatch@ome',
                        'operate_time' => strtotime('2026-07-24 11:00:00'),
                        'memo' => '订单自动审单未通过,审单规则:规则A(11),仓库规则:仓库A(2),详情见<br/>[]<br>操作员:系统获取订单，订单自动分派给确认组:组A,确认人:张三',
                    ],
                ];
            }
        };

        $capability = new omeauto_capability_effect_evidence($operationLogModel);
        $result = $capability->autoConfirmEvidence([
            'time_from' => '2026-07-24 00:00:00',
            'time_to' => '2026-07-24 23:59:59',
        ]);

        $this->assertCount(2, $result['rows']);
        $this->assertSame('2026-07-24 00:00:00', $result['window']['from']);
        $this->assertSame('2026-07-24 23:59:59', $result['window']['to']);
        $this->assertTrue($result['window']['inclusive']);
        $this->assertSame(2, $result['hit']['count']);
        $this->assertSame(1, $result['effect']['counts']['confirmed']);
        $this->assertSame(1, $result['effect']['counts']['queued_after_auto_confirm_failure']);
        $this->assertSame(0, $result['takeover']['count']);
        $this->assertFalse($result['takeover']['source_available']);
        $this->assertSame(1, $result['exception']['count']);
        $this->assertSame(2, $result['summary']['evidence_count']);
        $this->assertSame(2, $result['summary']['hit_count']);
        $this->assertSame(1, $result['summary']['outcome_counts']['confirmed']);
        $this->assertSame(1, $result['summary']['outcome_counts']['queued_after_auto_confirm_failure']);
        $this->assertSame(0, $result['summary']['takeover_count']);
        $this->assertSame(1, $result['summary']['exception_count']);
        $this->assertSame(11, $result['rows'][0]['confirm_rule_oid']);
        $this->assertSame('规则A', $result['rows'][0]['confirm_rule_name']);
        $this->assertSame('9001', $result['rows'][0]['order_id']);
        $this->assertSame('confirmed', $result['rows'][0]['outcome']);
        $this->assertSame('queued_after_auto_confirm_failure', $result['rows'][1]['outcome']);
        $this->assertSame('order_confirm@ome', $calls[0]['operation']);
        $this->assertSame('order_dispatch@ome', $calls[1]['operation']);
    }

    public function testCapabilityFiltersByConfirmRuleOidAndRejectsMissingTimeBounds(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/effect/evidence.php';

        $operationLogModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    [
                        'log_id' => 301,
                        'obj_id' => '9101',
                        'operation' => 'order_confirm@ome',
                        'operate_time' => strtotime('2026-07-24 10:00:00'),
                        'memo' => '订单确认,操作员:系统,审单规则:规则A(11),仓库规则:仓库A(2)',
                    ],
                    [
                        'log_id' => 302,
                        'obj_id' => '9102',
                        'operation' => 'order_confirm@ome',
                        'operate_time' => strtotime('2026-07-24 10:05:00'),
                        'memo' => '订单确认,操作员:系统,审单规则:规则B(12),仓库规则:仓库A(2)',
                    ],
                ];
            }
        };

        $capability = new omeauto_capability_effect_evidence($operationLogModel);
        $result = $capability->autoConfirmEvidence([
            'time_from' => '2026-07-24 00:00:00',
            'time_to' => '2026-07-24 23:59:59',
            'confirm_rule_oid' => 12,
        ]);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(12, $result['rows'][0]['confirm_rule_oid']);

        $this->expectException(InvalidArgumentException::class);
        $capability->autoConfirmEvidence(['time_from' => '2026-07-24 00:00:00']);
    }

    public function testCapabilityMarksPartialWhenSourceRowsMayBeTruncatedBeforeParsing(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/effect/evidence.php';

        $operationLogModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                if (($filter['operation'] ?? null) !== 'order_confirm@ome') {
                    return [];
                }

                return [
                    ['log_id' => 1, 'obj_id' => '1', 'operation' => 'order_confirm@ome', 'operate_time' => strtotime('2026-07-24 09:00:00'), 'memo' => '订单确认(发货单号：D1)'],
                    ['log_id' => 2, 'obj_id' => '2', 'operation' => 'order_confirm@ome', 'operate_time' => strtotime('2026-07-24 09:01:00'), 'memo' => '订单确认(发货单号：D2)'],
                    ['log_id' => 3, 'obj_id' => '3', 'operation' => 'order_confirm@ome', 'operate_time' => strtotime('2026-07-24 09:02:00'), 'memo' => '订单确认(发货单号：D3)'],
                    ['log_id' => 4, 'obj_id' => '4', 'operation' => 'order_confirm@ome', 'operate_time' => strtotime('2026-07-24 09:03:00'), 'memo' => '订单确认(发货单号：D4)'],
                    ['log_id' => 5, 'obj_id' => '5', 'operation' => 'order_confirm@ome', 'operate_time' => strtotime('2026-07-24 09:04:00'), 'memo' => '订单确认(发货单号：D5)'],
                ];
            }
        };

        $capability = new omeauto_capability_effect_evidence($operationLogModel);
        $result = $capability->autoConfirmEvidence([
            'time_from' => '2026-07-24 00:00:00',
            'time_to' => '2026-07-24 23:59:59',
            'limit' => 1,
        ]);

        $this->assertTrue($result['partial']);
        $this->assertContains('源日志查询可能已截断，结果仅代表候选证据窗口', $result['warnings']);
    }
}
