<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_effect_evidence
{
    private object $operationLogModel;

    public function __construct(?object $operationLogModel = null)
    {
        $this->operationLogModel = $operationLogModel ?? app::get('ome')->model('operation_log');
    }

    public function autoConfirmEvidence(array $request): array
    {
        $timeFrom = $this->requireTimestamp($request['time_from'] ?? null, '缺少开始时间');
        $timeTo = $this->requireTimestamp($request['time_to'] ?? null, '缺少结束时间');
        $timeFromTs = strtotime($timeFrom);
        $timeToTs = strtotime($timeTo);
        if ($timeFromTs > $timeToTs) {
            throw new InvalidArgumentException('开始时间不能大于结束时间');
        }

        $limit = min(max((int) ($request['limit'] ?? 200), 1), 500);
        $scanLimit = min(max($limit * 5, $limit + 1), 1000);
        $confirmRuleOid = isset($request['confirm_rule_oid']) ? (int) $request['confirm_rule_oid'] : 0;
        $orderId = isset($request['order_id']) ? (string) $request['order_id'] : '';

        $baseFilter = array_filter([
            'st_time' => $timeFrom,
            'et_time' => $timeTo,
            'obj_id' => $orderId !== '' ? $orderId : null,
        ], static fn($value) => $value !== null && $value !== '');

        $confirmRows = $this->loadOperationRows('order_confirm@ome', $baseFilter, $scanLimit);
        $dispatchRows = $this->loadOperationRows('order_dispatch@ome', $baseFilter, $scanLimit);
        $rows = array_merge($confirmRows['rows'], $dispatchRows['rows']);

        $parsedRows = [];
        foreach ($rows as $row) {
            $parsed = $this->parseEvidenceRow($row);
            if ($parsed === null) {
                continue;
            }
            if ($parsed['operate_time'] < $timeFromTs || $parsed['operate_time'] > $timeToTs) {
                continue;
            }
            if ($confirmRuleOid > 0 && $parsed['confirm_rule_oid'] !== $confirmRuleOid) {
                continue;
            }
            $parsedRows[] = $parsed;
        }

        usort($parsedRows, static function (array $left, array $right): int {
            return [$left['operate_time'], $left['log_id']] <=> [$right['operate_time'], $right['log_id']];
        });

        $partial = count($parsedRows) > $limit || $confirmRows['source_truncated'] || $dispatchRows['source_truncated'];
        if ($partial) {
            $parsedRows = array_slice($parsedRows, 0, $limit);
        }

        $outcomeCounts = [];
        foreach ($parsedRows as $row) {
            $outcomeCounts[$row['outcome']] = ($outcomeCounts[$row['outcome']] ?? 0) + 1;
        }

        $warnings = [];
        if ($confirmRows['source_truncated'] || $dispatchRows['source_truncated']) {
            $warnings[] = '源日志查询可能已截断，结果仅代表候选证据窗口';
        }
        if (count($parsedRows) === $limit && $partial && $warnings === []) {
            $warnings[] = '结果已按limit截断';
        }

        $exceptionRows = array_values(array_filter(
            $parsedRows,
            static fn(array $row): bool => $row['outcome'] === 'queued_after_auto_confirm_failure'
        ));

        return [
            'window' => [
                'from' => $timeFrom,
                'to' => $timeTo,
                'inclusive' => true,
            ],
            'query' => [
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
                'confirm_rule_oid' => $confirmRuleOid > 0 ? $confirmRuleOid : null,
                'order_id' => $orderId !== '' ? $orderId : null,
                'limit' => $limit,
            ],
            'hit' => [
                'count' => count($parsedRows),
                'rows' => $parsedRows,
            ],
            'effect' => [
                'counts' => $outcomeCounts,
            ],
            'takeover' => [
                'count' => 0,
                'rows' => [],
                'source_available' => false,
            ],
            'exception' => [
                'count' => count($exceptionRows),
                'rows' => $exceptionRows,
            ],
            'summary' => [
                'hit_count' => count($parsedRows),
                'evidence_count' => count($parsedRows),
                'effect_counts' => $outcomeCounts,
                'takeover_count' => 0,
                'exception_count' => count($exceptionRows),
                'outcome_counts' => $outcomeCounts,
            ],
            'rows' => $parsedRows,
            'partial' => $partial,
            'warnings' => $warnings,
        ];
    }

    private function requireTimestamp(mixed $value, string $missingMessage): string
    {
        $timestamp = trim((string) $value);
        if ($timestamp === '') {
            throw new InvalidArgumentException($missingMessage);
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('时间格式必须为 YYYY-MM-DD HH:MM:SS');
        }

        return $timestamp;
    }

    private function parseEvidenceRow(array $row): ?array
    {
        $memo = (string) ($row['memo'] ?? '');
        if (!preg_match('/审单规则:(.*?)(?:<span.*?<\/span>)?\((\d+)\),仓库规则:/u', $memo, $matches)) {
            return null;
        }

        $operation = (string) ($row['operation'] ?? '');
        if ($operation === 'order_dispatch@ome' && !str_contains($memo, '订单自动审单未通过')) {
            return null;
        }
        return [
            'log_id' => (int) ($row['log_id'] ?? 0),
            'order_id' => (string) ($row['obj_id'] ?? ''),
            'operation' => $operation,
            'operate_time' => (int) ($row['operate_time'] ?? 0),
            'confirm_rule_oid' => (int) $matches[2],
            'confirm_rule_name' => trim(strip_tags($matches[1])),
            'outcome' => $operation === 'order_dispatch@ome'
                ? 'queued_after_auto_confirm_failure'
                : (str_contains($memo, '部分确认') ? 'partial_confirmed' : 'confirmed'),
            'memo' => $memo,
        ];
    }

    private function loadOperationRows(string $operation, array $baseFilter, int $limit): array
    {
        $rows = $this->operationLogModel->getList('*', $baseFilter + ['operation' => $operation], 0, $limit, 'operate_time ASC,log_id ASC');
        return [
            'rows' => array_values(array_filter(
                $rows,
                static fn(array $row): bool => (string) ($row['operation'] ?? '') === $operation
            )),
            'source_truncated' => count($rows) >= $limit,
        ];
    }
}
