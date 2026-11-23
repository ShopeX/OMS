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

/**
 * ShopEx licence
 * Aliyun OSS存储
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 * @aliyunossdoc https://help.aliyun.com/zh/oss/developer-reference/php-1/?spm=a2c4g.11186623.0.0.e7836ac8ARwmzc
 */

require_once ROOT_DIR . '/vendor/autoload.php';
use OSS\Core\OssException;
use OSS\OssClient;

class base_storage_aliyunosssystem implements base_interface_storager
{
    /**
     * 文件前缀
     *
     * @var string
     **/
    private $_prefix = '';
    private $bucket  = '';

    public function __construct($prefix = '')
    {
        is_object($prefix) && $prefix = '';
        $this->_prefix = $prefix ?: 'common';
        $this->bucket = ALIYUNOSS_BUCKET;
        $this->url_timeout = 946080000; // 获取图片url，url的有效时间（30年）

        try {
            $this->_connect();
        } catch (Exception $e) {
            $this->ossClient = null;
        }
    }

    /**
     * 连接OSS
     *
     * @return void
     * @author
     **/
    private function _connect()
    {
        try {
            $this->ossClient = new OssClient(ALIYUNOSS_ACCESSKEY_ID, ALIYUNOSS_ACCESSKEY_SECRET, ALIYUNOSS_ENDPOINT);
            // 设置建立连接的超时时间。默认值为10，单位为秒
            $this->ossClient->setConnectTimeout(10);
            // 设置失败请求重试次数。默认3次。
            $this->ossClient->setMaxTries(3);
            // 设置Socket层传输数据的超时时间。默认值为5184000（60天），单位为秒。
            $this->ossClient->setTimeout(600);
            // 设置是否开启SSL证书校验。false（默认值）：关闭SSL证书校验
            $this->ossClient->setUseSSL(true);
        } catch (OssException $e) {
            // print_r("Exception:" . $e->getMessage() . "\n");
            $this->ossClient = null;
        }

    }

    /**
     * 创建存储空间
     *
     * @return void
     * @author
     **/
    public function createBucket()
    {
        if (!$this->ossClient) {
            return false;
        }
        try {
            $this->ossClient->createBucket($this->bucket);
        } catch (OssException $e) {
            return $e->getMessage();
        }
        return true;
    }

    public function save($file, &$url, $type, $addons, $ext_name = "")
    {
        if (!$this->ossClient) {
            return false;
        }

        try {
            // id = path
            $id = $this->_get_ident($file, $type, $addons, $url, $path, $ext_name);

            // $content = fopen($file, "r");

            // $this->ossClient->putObject($this->bucket, $id, $content); // 字符串上传
            $this->ossClient->uploadFile($this->bucket, $id, $file); // 文件上传

            $url = $this->ossClient->signUrl($this->bucket, $id, $this->url_timeout, "GET");
        } catch (OssException $e) {
            // print_r("Exception:" . $e->getMessage() . "\n");
            return false;
        }
        return $id;
    }

    public function replace($file, $id)
    {
        if (!$this->ossClient) {
            return false;
        }
        try {

            // $content = fopen($file, "r");

            //Upload oss
            // $this->ossClient->putObject($this->bucket, $id, $content);
            $this->ossClient->uploadFile($this->bucket, $id, $file);
        } catch (OssException $e) {
            // print_r("Exception:" . $e->getMessage() . "\n");
            return false;
        }
        return $id;
    }

    public function _get_ident($file, $type, $addons, &$url, &$path, $ext_name)
    {
        $blobName = [
            $addons['node_id'] ?: base_shopnode::node_id('ome'),
            trim($this->_prefix, '/'),
            basename($file),
        ];

        $path = trim(implode('/', $blobName), '/');


        if ($ext_name) {
            $path = $path . $ext_name;
        }

        return $path;
    }

    public function remove($id)
    {
        if (!$this->ossClient) {
            return false;
        }
        try {

            $this->ossClient->deleteObject($this->bucket, $id);
        } catch (OssException $e) {
            // print_r("Exception:" . $e->getMessage() . "\n");
            return false;
        }
        return true;
    }

    public function getFile($id, $type)
    {
        if (!$this->ossClient) {
            return false;
        }

        if (pathinfo($type, PATHINFO_EXTENSION)) {
            $filename = $type;
        } else {
            $filename = DATA_DIR . '/' . trim($id, '/');

            $f_dir = dirname($filename);

            if (!is_dir($f_dir)) {
                utils::mkdir_p($f_dir);
            }
        }

        try {
            $options   = array(
                OssClient::OSS_FILE_DOWNLOAD => $filename,
            );
            $this->ossClient->getObject($this->bucket, $id, $options);

            return $filename;
        } catch (OssException $e) {
            return false;
        }
        return true;
    }
}
