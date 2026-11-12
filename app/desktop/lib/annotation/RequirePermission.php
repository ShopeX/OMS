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

namespace app\desktop\lib\annotation;

/**
 * 控制器 Action 方法权限注解
 *
 * 用法（controller 中先 use）：
 *   use app\desktop\lib\annotation\RequirePermission;
 *   #[RequirePermission('order_add')]
 *   public function add() { ... }
 *
 * 解析由 desktop_user::chkground() 在 DB 查询之前执行。
 * 优先于 menu 表权限校验。
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class RequirePermission
{
    public ?string $permission;
    /** @var string[] */
    public array $all;

    public function __construct(?string $permission = null, array $all = [])
    {
        $all = array_values($all);
        if ($permission === '') {
            throw new \InvalidArgumentException('RequirePermission scalar permission must be non-empty');
        }
        if ($permission !== null && $all !== []) {
            throw new \InvalidArgumentException('RequirePermission cannot mix scalar permission and all');
        }
        if ($permission === null && $all === []) {
            throw new \InvalidArgumentException('RequirePermission requires a permission or all list');
        }
        foreach ($all as $entry) {
            if (!is_string($entry) || $entry === '') {
                throw new \InvalidArgumentException('RequirePermission all entries must be non-empty strings');
            }
        }

        $this->permission = $permission;
        $this->all = $all;
    }
}
