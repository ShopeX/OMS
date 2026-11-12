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

/**
 * 客户分类（material / admin_customer_classify）共享写能力
 *
 * 单一写实现，两条入口共用同一份逻辑：
 *  - Vue JSON 入口：material_ctl_api_customer_classify::save()（act=save，JSON body）
 *  - legacy 入口：material_ctl_admin_customer_classify::save()（表单 AJAX，事务由 begin()/end() 托管）
 *
 * 校验 / 归一化 / 落库与 legacy 原实现等价（app/material/controller/admin/customer/classify.php::save()）：
 *  - class_bn 非空 + 正则 `/^[0-9a-zA-Z\_\#\-\/]*$/`
 *  - class_name 非空
 *  - 编辑态 `class_bn` 不参与更新（编码不允许修改）
 *  - disabled 归一化为 'true' / 'false'（legacy 值域）
 */
final class material_capability_customer_classify
{
    private object $classifyModel;

    private object $database;

    public function __construct(?object $classifyModel = null, ?object $database = null)
    {
        $this->classifyModel = $classifyModel ?? app::get('material')->model('customer_classify');
        $this->database = $database ?? kernel::database();
    }

    /**
     * 编辑回填（legacy act=edit 表单字段）
     *
     * @return array{data:array{class_id:int,class_bn:string,class_name:string,disabled:string}}
     */
    public function loadClassify(int $classId): array
    {
        if ($classId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $row = $this->classifyModel->dump(['class_id' => $classId], '*');
        if (empty($row)) {
            throw new InvalidArgumentException('客户分类不存在');
        }

        return [
            'data' => [
                'class_id'   => (int) $row['class_id'],
                'class_bn'   => (string) ($row['class_bn'] ?? ''),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'disabled'   => ($row['disabled'] === 'true') ? 'true' : 'false',
            ],
        ];
    }

    /**
     * 新建 / 编辑保存（逐字对齐 legacy save() 的校验与分支）
     *
     * @param array $input           class_id(可空) / class_bn / class_name / disabled
     * @param bool  $ownTransaction  legacy 入口事务由 desktop_controller::begin() 托管，传 false
     *
     * @return array{id:int,message:string}
     */
    public function saveClassify(array $input, bool $ownTransaction = true): array
    {
        $classId   = (int) ($input['class_id'] ?? 0);
        $classBn   = trim((string) ($input['class_bn'] ?? ''));
        $className = trim((string) ($input['class_name'] ?? ''));

        // legacy: $disabled = ($_POST['disabled'] == 'true' ? 'true' : 'false')
        $disabledRaw = $input['disabled'] ?? 'false';
        $disabled    = ($disabledRaw === 'true' || $disabledRaw === true) ? 'true' : 'false';

        if ($classBn === '') {
            throw new InvalidArgumentException('客户分类编码不能为空');
        }

        if ($className === '') {
            throw new InvalidArgumentException('客户分类名称不能为空');
        }

        if (!preg_match('/^[0-9a-zA-Z\_\#\-\/]*$/', $classBn)) {
            throw new InvalidArgumentException('客户分类编码只支持(数字、英文、_下划线、-横线)');
        }

        $dataInfo = [];
        if ($classId > 0) {
            $dataInfo = $this->classifyModel->dump(['class_id' => $classId], '*');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $saveData = [
                'class_bn'   => $classBn,
                'class_name' => $className,
                'disabled'   => $disabled,
            ];

            if ($dataInfo) {
                // 编辑态：编码不允许修改（legacy unset class_bn）
                unset($saveData['class_bn']);
                $rs = $this->classifyModel->update($saveData, ['class_id' => $classId]);
            } else {
                $rs = $this->classifyModel->insert($saveData);
            }

            if (is_bool($rs)) {
                throw new RuntimeException(($dataInfo ? '更新' : '添加') . '客户分类失败');
            }

            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return [
            'id'      => $dataInfo ? $classId : (int) $rs,
            'message' => ($dataInfo ? '更新' : '添加') . '客户分类成功',
        ];
    }
}
