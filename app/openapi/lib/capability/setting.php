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
 * WITHOUT WARRANTIES OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


/**
 * 基础设置（openapi/admin_setting）写链路 capability：校验 + 落库。
 *
 * - 语义与文案对齐 legacy app/openapi/controller/admin/setting.php::save/setStatus
 *   （标识已存在 / 配置信息不存在 / 保存成功 / 保存失败 / 更新成功）；
 * - 本层不判权限：权限闸门只在控制器层（控制器方法上的权限注解）；
 * - 模型可注入，便于单测。
 */
final class openapi_capability_setting
{
    /** @var object|null */
    private $model;

    public function __construct(?object $model = null)
    {
        $this->model = $model;
    }

    /**
     * 新增 / 编辑保存。
     *
     * @param array $request name / code / interfacekey / status / is_data_mask / config [/ s_id]
     * @return array{success: bool, message: string}
     */
    public function save(array $request): array
    {
        $model = $this->settingModel();
        $sId   = (int) ($request['s_id'] ?? 0);

        $data = [
            'name'         => trim((string) ($request['name'] ?? '')),
            'config'       => $request['config'] ?? [],
            'status'       => ((int) ($request['status'] ?? 0) === 1) ? 1 : 0,
            'interfacekey' => trim((string) ($request['interfacekey'] ?? '')),
            'is_data_mask' => ((int) ($request['is_data_mask'] ?? 0) === 1) ? 1 : 0,
        ];

        if ($sId <= 0) {
            $code = trim((string) ($request['code'] ?? ''));
            // legacy 未显式拦截空标识（表单侧必填）；此处补一道防御，避免落脏行
            if ($code === '') {
                throw new InvalidArgumentException('标识不能为空');
            }
            if ($model->dump(['code' => $code], 's_id')) {
                throw new InvalidArgumentException('标识已存在');
            }
            $data['code'] = $code;
        } else {
            if (!$model->dump(['s_id' => $sId], 's_id,code')) {
                throw new InvalidArgumentException('配置信息不存在');
            }
            $data['s_id'] = $sId;
        }

        // dbeav_model::save() 形参按引用传递（&$data），必须传变量，不能传数组字面量
        if (!$model->save($data)) {
            throw new RuntimeException('保存失败');
        }

        return ['success' => true, 'message' => '保存成功'];
    }

    /**
     * 暂停 / 启用。
     *
     * @return array{success: bool, message: string}
     */
    public function setStatus(int $sId, int $status): array
    {
        $model = $this->settingModel();

        if ($sId <= 0) {
            throw new InvalidArgumentException('参数错误：缺少 s_id');
        }
        if (!$model->dump(['s_id' => $sId], 's_id')) {
            throw new InvalidArgumentException('配置信息不存在');
        }

        $data = ['s_id' => $sId, 'status' => ($status === 1) ? 1 : 0];
        if (!$model->save($data)) {
            throw new RuntimeException('更新失败');
        }

        return ['success' => true, 'message' => '更新成功'];
    }

    private function settingModel(): object
    {
        if ($this->model === null) {
            $this->model = app::get('openapi')->model('setting');
        }

        return $this->model;
    }
}
