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
 * 数据文件生成ftp类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */
class taskmgr_storage_sftp extends taskmgr_storage_abstract implements taskmgr_storage_interface
{

    private static $_storageConn = null;
    private static $_sftp = null;

    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct()
    {
        $this->connect();
    }

    /**
     * connect
     * @return mixed 返回值
     */
    public function connect()
    {
        if (!isset(self::$_storageConn)) {
            if (isset($GLOBALS['__STORAGE_CONFIG'])) {

                $config = $GLOBALS['__STORAGE_CONFIG'];

                self::$_storageConn = ssh2_connect($config['HOST'], $config['PORT']);
                if (!self::$_storageConn) {
                    trigger_error('connect sftp failed, please check it', E_USER_ERROR);
                }

                $login_result = ssh2_auth_password(self::$_storageConn, $config['USER'], $config['PASSWD']);
                if (!$login_result) {
                    trigger_error('login sftp failed, please check it', E_USER_ERROR);
                }

                self::$_sftp = @ssh2_sftp(self::$_storageConn);
                if (! self::$_sftp) {
                    trigger_error("Could not initialize SFTP subsystem.", E_USER_ERROR);
                }

                // if ($config['PASV'] == true) {
                //     ftp_pasv(self::$_storageConn, true);
                // }

                return true;
            } else {
                trigger_error('can\'t load __STORAGE_CONFIG, please check it', E_USER_ERROR);
            }
        }
    }

    /**
     * 向远程ftp上传保存生成文件
     * 
     * @param string $source_file 源文件含路径
     * @param string $task_id 目标文件名命名传入参数
     * @param string $url 生成目标文件路径
     * @return boolean true/false
     */
    public function save($source_file, $task_id, &$url, $extName = '')
    {
        if ($extName) {
            $this->setExtName($extName);
        }

        //存储的目的地文件路径
        $destination_file = $this->_get_ident($task_id);

        //传输上传文件
        $sftp = self::$_sftp;
        $stream = @fopen("ssh2.sftp://$sftp$destination_file", 'w');

        if (! $stream) {
            return false;
        }

        $data_to_send = @file_get_contents($source_file);

        if (@fwrite($stream, $data_to_send) === false) {
            return false;
        }

        @fclose($stream);
        //上传结果
        $url = $destination_file;
        return true;

    }

    /**
     * 向远程ftp下载文件到本地
     * 
     * @param string $url 远程源文件
     * @param string $local_file 本地目标文件
     * @return boolean true/false
     */
    public function get($url, $local_file)
    {
        $sftp = self::$_sftp;
        $stream = @fopen("ssh2.sftp://$sftp$url", 'r');

        if (! $stream) {
            return false;
        }

        $contents = stream_get_contents($stream); 

        if (@file_put_contents($local_file, $contents) === false) {
            return false;
        }

        @fclose($stream);

        //上传结果
        return true;
    }

    /**
     * 向远程ftp删除指定文件
     * 
     * @param string $url 远程源文件
     */
    public function delete($url)
    {
        $sftp = self::$_sftp;
        return ssh2_sftp_unlink($sftp, $url);
    }

    //含完整路径的生成文件地址
    public function _get_ident($key)
    {

        $config = $GLOBALS['__STORAGE_CONFIG'];

        $folder     = rtrim($config['rootPath'] ?: '/', '/') . '/' . date('Ymd');

        $sftp = self::$_sftp;

        ssh2_sftp_mkdir($sftp, $folder,0777, true);

        $path = $this->_ident($key);

        $url  = $folder . '/' . $path;

        return $url;
    }

    public function __destruct()
    {
        ssh2_disconnect(self::$_storageConn);
        ssh2_disconnect(self::$_sftp);
    }
}