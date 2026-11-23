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

class base_errorpage 
{

    static public function exception_handler($exception){
        $message = $exception->getMessage();
        
        $code = $exception->getCode();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTrace();
        $trace_message = $exception->getTraceAsString();
    
        // \Sentry\captureException($exception);


        $trace_message = null;
        
        $root_path = realpath(ROOT_DIR);
        $output = ob_end_clean();
        
        $position = str_replace($root_path,'&gt; &nbsp;',$file).':'.$line;

        // 不报具体错误
        $trace = []; $position = '';

        $i=0;
        foreach($trace as $t){
            if(!($t['class']=='kernel' && $t['function']=='exception_error_handler')){
                $t['file'] = str_replace($root_path,'ROOT:',$t['file']);
                $basename = basename($t['file']);
                if($i==0){
                    $trace_message .= '<tr class="code" style="color:#000"><td><b>&gt;&nbsp;</b></td>';
                }else{
                    $trace_message .= '<tr class="code" style="color:#999"><td></td>';
                }
                if($t['args']){
                    if($t['class'] == 'base_db_connections' && $t['function'] == '_connect') {
                        $args_info = '';
                    } else {
                        $args_info = json_encode($t['args'], JSON_UNESCAPED_UNICODE);
                        $args_info = str_replace(['<', '>'],['<\'', '\'>'],$args_info);
                    }
                    if(trim($args_info)){
                        $args = "<span class=\"lnk\" onclick=\"alert(this.nextSibling.innerHTML)\">...</span><span style='display:none'>$args_info</span>";    
                    }else{
                        $args = "\"$args_inf\"";
                    }
                }else{
                    $args = '';
                }
                if($t['line']){
                    $trace_message .= "<td>#{$i}</td><td>{$t['class']}{$t['type']}{$t['function']}({$args})</td><td>{$basename}:{$t['line']}</td></tr>";
                }else{
                    $trace_message .= "<td>#{$i}</td><td>{$t['class']}{$t['type']}{$t['function']}({$args})</td><td>{$basename}</td></tr>";
                }
                $i++;
            }
        }
        
        $output=<<<EOF
        <p style="background:#eee;border:1px solid #ccc;padding:10px;margin:10px 0">$message</p>
        <div style="padding:10px 0;font-weight:bold;color:#000">$position</div>
        <table cellspacing="0" cellpadding='0' style="width:100%;">
        $trace_message
        </table>
EOF;

        self::output($output, 'Track');
    }
    
    static function system_is_offline(){
        self::output('','System is offline');
    }
    
    static protected function output($body,$title='',$status_code=500){
        //header('Connection:close',1,500);
        
        $date = date(DATE_RFC822);
        
        $html =<<<HTML
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
        <head>
        	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        	<title>Error: $title</title>
        	<style>
                #main{width:500px;margin:auto;}
                #header{position: relative;background:#c52f24;margin:20px 0 5px 0;
                padding:5px;color:#fff;height:30px;
                font-family: "Helvetica Neue", Arial, Helvetica, Geneva, sans-serif;}
                .code{font-size:14px;line-height:16px;font-weight:bold;font-family: "Courier New", Courier, mono;}
                .lnk{text-decoration: underline;color:#009;	cursor: pointer;}
        	</style>
        </head>

        <body>
            <div id="main">
                <div id="header">
                    <span style="float:left;">$title</span>
                    <span style="float:right;font-size:10px">$date</span>
                </div>
                <br class="clear" />
                <div>
                $body
                </div>
            </div>
        </body>
        </html>
HTML;

        echo str_pad($html,1024);
        exit;
    }
    
}//End Class