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
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.com/license/gpl GPL License
 */

class base_http {

    var $timeout         = 10;
    var $defaultChunk    = 4096;
    var $http_ver        = '1.1';
    var $hostaddr        = null;
    var $default_headers = array(
        'Pragma'        => "no-cache",
        'Cache-Control' => "no-cache",
        'Connection'    => "close"
    );

    function __construct() {
        if (defined('HTTP_PROXY')) {
            list($this->proxyHost, $this->proxyPort) = explode(':', HTTP_PROXY);
        }
    }

    function set_timeout($timeout) {
        $timeout = (intval($timeout) > 0) ? intval($timeout) : 10;
        $this->timeout = $timeout;

        return $this;
    }

    function action($action, $url, $headers = null, $callback = null, $data = null, $ping_only = false) {

        $this->callback = $callback;
        $tmp_data = $data;

        if ($url) {
            $url_info = parse_url($url);
            $request_query = (isset($url_info['path']) ? $url_info['path'] : '/') . (isset($url_info['query']) ? '?' . $url_info['query'] : '');
            $request_server = $request_host = $url_info['host'];
            $request_port = (isset($url_info['port']) ? $url_info['port'] : (($url_info['scheme'] == 'https') ? 443 : 80));
        } else {
            $request_server = $_SERVER['SERVER_ADDR'];
            $request_query = $_SERVER['PHP_SELF'];
            $request_host = $_SERVER['HTTP_HOST'];
            $request_port = $_SERVER['SERVER_PORT'];
        }

        $out = strtoupper($action) . ' ' . $request_query . " HTTP/{$this->http_ver}\r\n";
        $out .= 'Host: ' . $request_host . ($request_port != 80 && $request_port != 443 ? (':' . $request_port) : '') . "\r\n";
        $this->responseHeader = &$responseHeader;
        $this->responseBody = &$responseBody;

        if ($data) {
            if (is_array($data)) {
                $data = utils::http_build_query($data);
            }
            if ($headers['Content-Encoding'] == 'gzip') {
                $gdata = gzencode($data);
                if ($gdata) {
                    $data = $gdata;
                } else {
                    unset($headers['Content-Encoding']);
                }
            }//todo: 判断是否需要gzip
            $headers['Content-Length'] = strlen($data);
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        $headers = array_merge($this->default_headers, (array)$headers);

        foreach ((array)$headers as $k => $v) {
            $out .= $k . ': ' . $v . "\r\n";
        }
        $out .= "\r\n" . $data;
        $data = null;
        kernel::log($out);
        $responseHeader = array();
        if ($this->proxyHost && $this->proxyPort) {
            $request_server = $this->proxyHost;
            $request_port = $this->proxyPort;
            kernel::log('Using proxy ' . $request_server . ':' . $request_port . '. ');
        }

        if ($this->hostaddr) {
            $request_addr = $this->hostaddr;
        } else {
            //if (!$this->is_addr($request_server)) {
            //    kernel::log('Resolving ' . $request_server . '... ', true);
            //    $request_addr = gethostbyname($request_server);
            //    kernel::log($request_addr);
            //} else {
                $request_addr = $request_server;
            //}
            if ($url_info['scheme'] == 'https') {
                $request_addr = "ssl://" . $request_addr;
            } else {
                $request_addr = "tcp://" . $request_addr;
            }
        }
        if ($this->hostport) {
            $request_port = $this->hostport;
        }

        $request_addr = $request_addr . ":" . $request_port;
        $request_addr = (!is_array($request_addr)) ? array($request_addr) : $request_addr;

        foreach ($request_addr AS $request_host_addr) {
            kernel::log(sprintf('Connecting to %s|%s|:%s... connected.', $request_server, $request_host_addr, $request_port));
	   $stream_context = stream_context_create(array('ssl' => array(
			'verify_peer'       => false,
			'verify_peer_name'  => false,
			'allow_self_signed' => true)));
	    $fp = stream_socket_client($request_host_addr, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT,$stream_context);
            if ($fp) {
		
                if ($this->timeout && function_exists('stream_set_timeout')) {
                    $this->read_time_left = $this->read_time_total = $this->timeout;
                } else {
                    $this->read_time_total = null;
                }

                $sent = fwrite($fp, $out);
                if ($ping_only !== false) {
                    if (is_numeric($ping_only) && $ping_only > 0) {
                        sleep($ping_only);
                    }

                    return $sent;
                }

                kernel::log('HTTP request sent, awaiting response... ', true);
                $this->request_start = $this->microtime();

                $out = null;

                $responseBody = '';
                if (HTTP_TIME_OUT === $this->readsocket($fp, 512, $status, 'fgets')) {
                    return HTTP_TIME_OUT;
                }

                if (preg_match('/\d{3}/', $status, $match)) {
                    $this->responseCode = $match[0];
                }

                kernel::log($this->responseCode, true);
                while (!feof($fp)) {
                    if (HTTP_TIME_OUT === $this->readsocket($fp, 512, $raw, 'fgets')) {
                        return HTTP_TIME_OUT;
                    }
                    $raw = trim($raw);
                    if ($raw) {
                        if ($p = strpos($raw, ':')) {
                            $responseHeader[strtolower(trim(substr($raw, 0, $p)))] = trim(substr($raw, $p + 1));
                        }
                    } else {
                        break;
                    }
                }
            
		    switch ($this->responseCode) {
                    case 301:
                    case 302:
                        kernel::log(" Redirect \n\t--> " . $responseHeader['location']);
                        if (isset($responseHeader['location'])) {
                            return $this->action($action, $responseHeader['location'], $headers, $callback, $tmp_data);
                        } else {
                            return false;
                        }

                    case 200:
                        kernel::log(' OK');

                        return $this->process($fp);

                    case 404:
                        kernel::log(' file not found');

                        return false;

                    default:
                        return false;
                }
            }
        }

        return false;
    }

    function process($fp) {
        $chunkmode = (isset($this->responseHeader['transfer-encoding']) && $this->responseHeader['transfer-encoding'] == 'chunked');
        if ($chunkmode) {
            if (HTTP_TIME_OUT === $this->readsocket($fp, 512, $chunklen, 'fgets')) {
                return HTTP_TIME_OUT;
            }
            $chunklen = hexdec(trim($chunklen));
        } elseif (isset($this->responseHeader['content-length'])) {
            // 设置为非阻塞, 因不分块传输时 服务端不会再输入数据
            stream_set_blocking($fp, false);
            $chunklen = min($this->defaultChunk, $this->responseHeader['content-length']);
        } else {
            $chunklen = $this->defaultChunk;
        }


        while (!feof($fp) && $chunklen) {
            if (HTTP_TIME_OUT === $this->readsocket($fp, $chunklen, $content)) {
                return HTTP_TIME_OUT;
            }
            $readlen = strlen($content);
            while ($chunklen != $readlen) {
                if (HTTP_TIME_OUT === $this->readsocket($fp, $chunklen - $readlen, $buffer)) {
                    return HTTP_TIME_OUT;
                }
                if (!strlen($buffer))
                    break;
                $readlen += strlen($buffer);
                $content .= $buffer;
            }

            if ($this->callback) {
                if (!call_user_func_array($this->callback, array(&$this, &$content))) {
                    break;
                }
            } else {
                $responseBody .= $content;
            }

            if ($chunkmode) {
                fread($fp, 2);
                if (HTTP_TIME_OUT === $this->readsocket($fp, 512, $chunklen, 'fgets')) {
                    return HTTP_TIME_OUT;
                }
                $chunklen = hexdec(trim($chunklen));
            } else {
                $readed += strlen($content);
                if ($this->responseHeader['content-length'] <= $readed) {
                    break;
                }
            }
        }
        fclose($fp);
        if ($this->callback) {
            return true;
        } else {
            return $responseBody;
        }
    }

    function is_addr($ip) {
        return preg_match('/^[0-9]{1-3}\.[0-9]{1-3}\.[0-9]{1-3}\.[0-9]{1-3}$/', $ip);
    }

    private function microtime() {
        list($usec, $sec) = explode(" ", microtime());

        return ((float)$usec + (float)$sec);
    }

    private function readsocket($fp, $length, &$content, $func = 'fread') {
        if (!$this->reset_time_out($fp)) {
            return HTTP_TIME_OUT;
        }

        $content = $func($fp, $length);

        if ($this->check_time_out($fp)) {
            return HTTP_TIME_OUT;
        } else {
            return true;
        }
    }

    private function reset_time_out(&$fp) {
        if ($this->read_time_total === null) {
            return true;
        } elseif ($this->read_time_left < 0) {
            return false;
        } else {
            $this->read_time_left = $this->read_time_total - $this->microtime() + $this->request_start;
            $second = floor($this->read_time_left);
            $microsecond = intval(($this->read_time_left - $second) * 1000000);
            stream_set_timeout($fp, $second, $microsecond);

            return true;
        }
    }

    private function check_time_out(&$fp) {
        if (function_exists('stream_get_meta_data')) {
            $info = stream_get_meta_data($fp);

            return $info['timed_out'];
        } else {
            return false;
        }
    }


}
