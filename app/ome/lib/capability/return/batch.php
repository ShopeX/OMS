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
 * 批量操作设置写能力（SCRUM-7771）
 *
 * 唯一写链路：Vue API 控制器 / legacy 控制器 → 本 capability → model。
 * 校验、归一化、图片上传、持久化与事务边界都在本层；
 * 控制器只做权限注解、入参解析与响应格式化（skill: capability-backed-write）。
 *
 * 业务规则逐条照搬 legacy `app/ome/controller/admin/return/batch.php::set()`：
 * - 店铺必填（'请选择店铺！'，legacy :48-50）
 * - 拒绝退款 / 拒绝退货：新增时必须有图片（'请上传图片!'，legacy :51-55）
 * - 图片 ≤500K（'上传文件不能超过500K!'，legacy :57-60）
 * - 图片仅 gif/jpg/png（'您只能上传以下类型文件gif,jpg,png!'，legacy :62-68）
 * - 图片落 base_storager（f_type=public），同时写 picurl + imgext（legacy :69-73）
 * - 同一店铺 + 同一类型只允许一个默认（legacy :88-91）
 */
class ome_capability_return_batch
{
    /** @var object 批量设置模型（ome_mdl_return_batch） */
    private object $returnBatchModel;

    /** @var object 数据库连接（事务边界持有者） */
    private object $database;

    /** @var array<int, string> legacy 允许的图片扩展名（return/batch.php:62） */
    private const ALLOWED_IMAGE_EXT = ['gif', 'jpg', 'png'];

    /** @var int legacy 图片体积上限（字节，return/batch.php:58） */
    private const MAX_IMAGE_SIZE = 512000;

    /**
     * @param object|null $returnBatchModel 批量设置模型，缺省取 ome 应用模型
     * @param object|null $database 数据库连接，缺省取 kernel::database()
     */
    public function __construct(?object $returnBatchModel = null, ?object $database = null)
    {
        $this->returnBatchModel = $returnBatchModel ?? app::get('ome')->model('return_batch');
        $this->database = $database ?? kernel::database();
    }

    /**
     * 保存批量设置（batch_id 有值即编辑，无值即新增）
     *
     * @param array $request 请求字段：shop_id / batch_type / memo / is_default / batch_id
     * @param array|null $uploadedFile $_FILES['picurl'] 结构；未选择图片时为 null
     * @return array{batch_id:int, is_new:bool} 落库后的批量设置 ID 与是否新增
     * @throws InvalidArgumentException 业务校验失败（文案与 legacy 逐字一致）
     * @throws RuntimeException 图片上传或持久化失败
     */
    public function save(array $request, ?array $uploadedFile = null): array
    {
        $payload = $this->normalizeRequest($request);
        $this->validateRequest($payload, $uploadedFile);
        $payload = $this->attachUploadedImage($payload, $uploadedFile);

        // 显式删除图片（用户要求的 UX 增强）：legacy 的 sfile 只能覆盖、无法清空，
        // 这里按 remove_picurl=1 把 picurl/imgext 置空；同一次请求若带了新文件则新文件优先。
        if ($this->isImageRemovalRequested($request) && !$this->hasUploadedImage($uploadedFile)) {
            $payload['picurl'] = '';
            $payload['imgext'] = '';
        }

        $isNew = empty($payload['batch_id']);

        $transaction = $this->database->beginTransaction();
        try {
            if ($payload['is_default'] === 'true') {
                $this->clearOtherDefaults((string) $payload['shop_id'], (string) $payload['batch_type']);
            }

            $savedBatchId = $this->returnBatchModel->save($payload);
            if ($savedBatchId === false) {
                throw new RuntimeException('保存失败');
            }

            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }

        return [
            'batch_id' => (int) ($payload['batch_id'] ?? $savedBatchId),
            'is_new' => $isNew,
        ];
    }

    /**
     * 取单条批量设置的原始字段（编辑弹窗回填用）
     *
     * 必须走 db_dump：finder 列表行的 shop_id 已被 object modifier 解析成店铺名，
     * 不能回填表单（skill lookups/known-traps.md #4 同类问题）。
     *
     * @param int $batchId 批量设置 ID
     * @return array 原始记录
     * @throws InvalidArgumentException 缺少 ID
     * @throws RuntimeException 记录不存在
     */
    public function detail(int $batchId): array
    {
        if ($batchId < 1) {
            throw new InvalidArgumentException('参数错误');
        }

        $row = $this->returnBatchModel->db_dump(['batch_id' => $batchId], '*');
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('记录不存在');
        }

        return $row;
    }

    /**
     * 归一化请求字段
     *
     * @param array $request 原始请求
     * @return array 归一化后的待落库字段
     */
    private function normalizeRequest(array $request): array
    {
        $payload = [
            // sdb_ome_shop.shop_id 是 varchar(32) 哈希（例：70aaa305bc361d28fb953815257aba67），
            // legacy 直接透传 POST 原值，这里必须保持字符串，禁止 int 强转
            'shop_id' => trim((string) ($request['shop_id'] ?? '')),
            'batch_type' => trim((string) ($request['batch_type'] ?? '')),
            'memo' => trim((string) ($request['memo'] ?? '')),
            // legacy 表单是 radio true/false（return_product/set/batch.html:50-51）
            'is_default' => ((string) ($request['is_default'] ?? 'false')) === 'true' ? 'true' : 'false',
        ];

        $batchId = (int) ($request['batch_id'] ?? 0);
        if ($batchId > 0) {
            $payload['batch_id'] = $batchId;
        }

        return $payload;
    }

    /**
     * 业务校验（顺序与 legacy 一致，保证报错文案先命中同一条）
     *
     * @param array $payload 归一化后的字段
     * @param array|null $uploadedFile 上传的图片
     * @return void
     * @throws InvalidArgumentException 校验失败
     */
    private function validateRequest(array $payload, ?array $uploadedFile): void
    {
        if ($payload['shop_id'] === '') {
            throw new InvalidArgumentException('请选择店铺！');
        }

        $hasImage = $this->hasUploadedImage($uploadedFile);

        if (
            in_array($payload['batch_type'], ['refuse', 'refuse_return'], true)
            && empty($payload['batch_id'])
            && !$hasImage
        ) {
            throw new InvalidArgumentException('请上传图片!');
        }

        if (!$hasImage) {
            return;
        }

        if ((int) ($uploadedFile['size'] ?? 0) > self::MAX_IMAGE_SIZE) {
            throw new InvalidArgumentException('上传文件不能超过500K!');
        }

        $imageExt = $this->resolveImageExt((string) ($uploadedFile['name'] ?? ''));
        if (!in_array($imageExt, self::ALLOWED_IMAGE_EXT, true)) {
            throw new InvalidArgumentException('您只能上传以下类型文件' . implode(',', self::ALLOWED_IMAGE_EXT) . '!');
        }
    }

    /**
     * 上传图片并补 picurl / imgext（legacy 用 base_storager 的 file 存储）
     *
     * @param array $payload 归一化后的字段
     * @param array|null $uploadedFile 上传的图片
     * @return array 补齐图片字段后的待落库数据
     * @throws RuntimeException 上传失败
     */
    private function attachUploadedImage(array $payload, ?array $uploadedFile): array
    {
        if (!$this->hasUploadedImage($uploadedFile)) {
            return $payload;
        }

        $storager = kernel::single('base_storager');
        $message = '';
        $fileId = $storager->save_upload($uploadedFile, 'file', '', $message);
        if (empty($fileId)) {
            throw new RuntimeException($message !== '' ? (string) $message : '图片上传失败');
        }

        $payload['picurl'] = $storager->getUrl($fileId, 'file');
        $payload['imgext'] = $this->resolveImageExt((string) ($uploadedFile['name'] ?? ''));

        return $payload;
    }

    /**
     * 清除同店铺同类型的其它默认项（legacy return/batch.php:88-91）
     *
     * @param string $shopId 店铺 ID（sdb_ome_shop.shop_id 为 varchar(32) 哈希）
     * @param string $batchType 批量类型
     * @return void
     */
    private function clearOtherDefaults(string $shopId, string $batchType): void
    {
        $this->returnBatchModel->update(
            ['is_default' => 'false'],
            [
                'shop_id' => $shopId,
                'batch_type' => $batchType,
                'is_default' => 'true',
            ]
        );
    }

    /**
     * 是否请求删除已有图片（remove_picurl=1/true）
     *
     * @param array $request 请求字段
     * @return bool
     */
    private function isImageRemovalRequested(array $request): bool
    {
        $flag = strtolower(trim((string) ($request['remove_picurl'] ?? '')));

        return $flag === '1' || $flag === 'true';
    }

    /**
     * 是否真的选择了图片（legacy 判据是 size != 0，等价于 error != UPLOAD_ERR_NO_FILE）
     *
     * @param array|null $uploadedFile $_FILES 元素
     * @return bool
     */
    private function hasUploadedImage(?array $uploadedFile): bool
    {
        if (!is_array($uploadedFile) || empty($uploadedFile['name'])) {
            return false;
        }

        return (int) ($uploadedFile['size'] ?? 0) > 0;
    }

    /**
     * 解析文件名扩展名（legacy 用 strrchr + substr 的等价写法）
     *
     * @param string $fileName 原始文件名
     * @return string 小写扩展名，无扩展名时返回空串
     */
    private function resolveImageExt(string $fileName): string
    {
        $dotPosition = strrpos($fileName, '.');
        if ($dotPosition === false) {
            return '';
        }

        return strtolower(substr($fileName, $dotPosition + 1));
    }
}
