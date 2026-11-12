<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class eccommon_capability_region
{
    private object $regionModel;

    public function __construct(?object $regionModel = null)
    {
        $this->regionModel = $regionModel ?? app::get('eccommon')->model('regions');
    }

    public function search(array $request): array
    {
        $query = trim((string) ($request['query'] ?? ''));
        $grade = (int) ($request['region_grade'] ?? 0);
        $parentId = (int) ($request['parent_id'] ?? 0);
        $limit = min(50, max(1, (int) ($request['limit'] ?? 50)));

        if ($query === '') {
            throw new InvalidArgumentException('请填写要查询的地区名称');
        }

        $filter = ['local_name|has' => $query];
        if ($grade > 0) {
            $filter['region_grade'] = $grade;
        }
        if ($parentId > 0) {
            $filter['p_region_id'] = $parentId;
        }

        $rows = $this->regionModel->getList(
            'region_id,local_name,p_region_id,region_path,region_grade,ordernum',
            $filter,
            0,
            $limit,
            'ordernum ASC, region_id ASC'
        ) ?: [];

        return [
            'list' => $this->normalize($rows),
            'total' => count($rows),
        ];
    }

    private function normalize(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ancestorIds = [];
        foreach ($rows as $row) {
            foreach ($this->pathIds((string) ($row['region_path'] ?? '')) as $id) {
                if ($id !== (int) ($row['region_id'] ?? 0)) {
                    $ancestorIds[$id] = $id;
                }
            }
        }

        $names = [];
        if ($ancestorIds !== []) {
            $ancestors = $this->regionModel->getList('region_id,local_name', ['region_id' => array_values($ancestorIds)], 0, -1) ?: [];
            foreach ($ancestors as $ancestor) {
                $names[(int) $ancestor['region_id']] = (string) $ancestor['local_name'];
            }
        }

        $normalized = [];
        foreach ($rows as $row) {
            $ids = $this->pathIds((string) ($row['region_path'] ?? ''));
            $ancestorNames = [];
            foreach ($ids as $id) {
                if ($id === (int) ($row['region_id'] ?? 0)) {
                    break;
                }
                if (isset($names[$id])) {
                    $ancestorNames[] = $names[$id];
                }
            }
            $normalized[] = [
                'region_id' => (string) $row['region_id'],
                'name' => (string) $row['local_name'],
                'region_grade' => (int) ($row['region_grade'] ?? 0),
                'parent_id' => $row['p_region_id'] !== null && $row['p_region_id'] !== '' ? (string) $row['p_region_id'] : '',
                'ancestor_names' => $ancestorNames,
                'full_name' => implode('/', array_merge($ancestorNames, [(string) $row['local_name']])),
            ];
        }

        return $normalized;
    }

    private function pathIds(string $regionPath): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $regionPath)), static fn(int $id): bool => $id > 0));
    }
}
