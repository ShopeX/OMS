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

(function() {
    this.initPrinter = function(type,options){
        switch (type) {
            case 'shopex':
                return new ShopexPrinter(options);
            case 'cainiao':
                return new CaiNiaoPrinter(options);
            case 'pdd':
                return new PddPrinter(options);
            case 'jd':
                return new JdPrinter(options);
            case 'douyin':
                return new DouyinPrinter(options);
            case 'kuaishou':
                return new KuaishouPrinter(options);
            case 'wphvip':
                return new WphvipPrinter(options);
            case 'lodop':
                return new LodopPrinter(options);
            case 'sf':
                return new SfPrinter(options);
            case 'xhs':
                return new XhsPrinter(options);
            case 'aikucun':
                return new AikucunPrinter(options);
            case 'wxshipin':
                return new WxshipinPrinter(options);
            case 'dewuppzf': // 得物品牌直发 
                return new DewuppzfPrinter(options);
            case 'dewuppzfzy': // 得物品牌直发自研 
                return new DewuppzfzyPrinter(options);
            case 'meituan4bulkpurchasing':
                return new Meituan4bulkpurchasingPrinter(options);
            case 'youzan':
                return new CaiNiaoPrinter(options);
        }

        return null;
    };

    var Printer = new Class({
        Implements: [Events, Options],
        options:{
            data:{},      // 打印数据
            template:'',  // 打印模板
            template_url:'',
            custom_template_url:'',
            custom_data:{},
            activex:null,// 控件
            type:0,// 纸张类型,0:快递单(套打),1:分页输出(标准), 2:卷筒输出, 3:卷筒自适应
            brower:2,
            /*
            onPrintSuccess: function(responseText){},
            onPreview: function(responseText){},
            onGetPrinters: function(responseText){},
            onPrintComplete: function(responseText){},
            onPrinterConfig:function(){},
            onOpen:function(){},
            onClose:function(){},
            onError:function(){},
            onMoveto:function(){}*/
        },
        initialize: function(options){
            this.setOptions(options);

            this.destatus = 'ready';
            this.demsg = '';
        },
        print:function(printer){},
        preview:function(printer){},
        getPrinters:function(){},
        setPrinterConfig:function(printer){},
        moveto:function(page){},
        isReady:function(){},
        // loadReport:function(data,template){},
        setPrinters:function(element,data){
            var offsetx = element.getParent('#print-preview').getElement('#offsetx');
            var offsety = element.getParent('#print-preview').getElement('#offsety');
            var thermal = element.getParent('#print-preview').getElement('#thermal');

            if (!data.printerlist) return 0;

            var select = new Element('select',{
                id:'printerlist',
                name:'printerlist',
                class:'x-input-select inputstyle',
                styles:{
                    width:'width:130px;'
                },
                events:{
                    change:function(){
                        var optionSelected  = this.getElement('option:selected');

                        // 设置偏移量
                        if ($defined(offsetx)) offsetx.set('value',optionSelected.get('x'));

                        if ($defined(offsety)) offsety.set('value',optionSelected.get('y'));

                        // 设置热敏
                        if ($defined(thermal)) thermal.set('checked', optionSelected.get('isThermal')?true:false);
                    }
                }
            });

            var optionDefault = [];
            data.printerlist.each(function(printer){
                var option = new Element('option',{
                    value:printer.printername,
                    text:printer.printername,
                    selected:data.default==printer.printername?true:false,
                    x:printer.x,
                    y:printer.y,
                    isThermal:printer.isThermal
                });
                select.adopt(option);

                // 自动匹配打印机
                // if (printer.printername.contains("<{$express_company_no|default:0123456789}>")) {
                //     optionDefault[2] = option;
                // }

                if (data.lastSelected.express == printer.printername) {
                    optionDefault[1] = option;
                }

                if (data.default == printer.printername) {
                    optionDefault[0] = option;
                }
            });

            if (optionDefault.length) {
                var optionSelected = optionDefault.getLast();

                optionSelected.selected=true;

                // 设置偏移量
                if ($defined(offsetx)) offsetx.set('value',optionSelected.get('x'));

                if ($defined(offsety)) offsety.set('value',optionSelected.get('y'));

                // 设置热敏
                if ($defined(thermal)) thermal.set('checked', 'true'==optionSelected.get('isThermal')?true:false);
            }

            element.adopt(select);

            return data.printerlist.length;
        },
        decryptAsync:function(nums){
            this.destatus = 'running';
            this.demsg    = '数据解密中';
            var data = this.options.data;
            if (typeof this.options.data === 'string'){
                data = JSON.decode(data);
            }
            this.iTotal = data.length;
            this.iSucc = 0;
            this.queueNum = nums || 1;
            this.finishQueueNum = 0;
            this.fireEvent('decryptStart',{t:this.iTotal,s:this.iSucc});
            for(var i = 0; i < this.queueNum; i++) {
                this.decryptAsyncProcess(i,data);
            }
        },
        decryptAsyncProcess:function(idx,data) {
            if(idx >= this.iTotal) {
                this.finishQueueNum++;
                if(this.finishQueueNum<this.queueNum) {
                    return;
                }
                this.destatus = this.destatus == 'running' ? 'succ' : 'fail';
                this.options.data = typeof this.options.data === 'string' ? this.toUnicode(JSON.encode(data)) : data;

                this.fireEvent('decryptComplete',{t:this.iTotal,s:this.iSucc,d:this.options.data,rsp:this.destatus,errmsg:this.errmsg});
            } else {
                var _this = this;
                if(data[idx]) {
                    if (data[idx]['is_encrypt']) {
                        var app = data[idx]['app'] == 'brush' ? 'brush' : 'wms';
                        new Security({
                            url:'index.php?app='+app+'&ctl=admin_delivery&act=showSensitiveData&p[0]='+data[idx]['delivery_id'],
                            showData:false,
                            onAsyncData:function(security) {
                                _this.dealDecryptProcess(security, data[idx]);
                                _this.iSucc++;
                                _this.fireEvent('decryptProcess',{t:_this.iTotal,s:_this.iSucc});
                                _this.decryptAsyncProcess(idx+_this.queueNum,data);
                            }
                        }).getAsyncData();
                    } else {
                        _this.iSucc++;
                        _this.fireEvent('decryptProcess',{t:_this.iTotal,s:_this.iSucc});
                        _this.decryptAsyncProcess(idx+_this.queueNum,data);
                    }
                }
            }
        },
        decrypt:function(){
            this.destatus = 'running';
            this.demsg    = '数据解密中';
            var delaytime = arguments[0] ? arguments[0] : 0;

            var data = this.options.data;
            if (typeof this.options.data === 'string'){
                data = JSON.decode(data);
            }

            var iTotal = data.length; var iSucc = 0;

            this.fireEvent('decryptStart',{t:iTotal,s:iSucc});
            var _this = this;

            setTimeout(function(){ _this.decryptProcess(data); }, 300);

            return this;
        },
        decryptProcess:function(data){
            var iTotal = data.length; var iSucc = 0;
            var rs = true; this.errmsg = '';
            var rs = data.every(function(item,key){
                if (item['is_encrypt']) {
                    var app = item['app'] == 'brush' ? 'brush' : 'wms';
                    var security = new Security({
                        url:'index.php?app='+app+'&ctl=admin_delivery&act=showSensitiveData&p[0]='+item['delivery_id'],
                        showData:true
                    });
                    var ddprs = this.dealDecryptProcess(security, item);
                    if(!ddprs) {
                        return false
                    }
                }

                iSucc++;

                this.fireEvent('decryptProcess',{t:iTotal,s:iSucc});

                return true;
            }.bind(this));

            this.options.data = typeof this.options.data === 'string' ? this.toUnicode(JSON.encode(data)) : data;

            this.destatus = rs ? 'succ' : 'fail';
            this.demsg    =  rs ? '数据解密完成':'数据解密失败';

            this.fireEvent('decryptComplete',{t:iTotal,s:iSucc,d:this.options.data,rsp:this.destatus,errmsg:this.errmsg});

            return this;
        },
        dealDecryptProcess:function(security, item) {

            var resp = security.decrypt('resp');
            if (!resp) {
                this.errmsg = '['+security.decrypt('delivery_bn')+']解密失败：请求超时';
                return false;
            }

            if (resp['rsp'] == 'fail') {
                this.errmsg = '['+security.decrypt('delivery_bn')+']解密失败：'+resp['err_msg'];
                return false;
            }

            var body = security.decrypt('encrypt_body');
            Object.each(JSON.decode(body['fields']), function(v, k){
                item[k] = security.decrypt(k);
            });

            var ship_province = security.decrypt('ship_province');
            var ship_city     = security.decrypt('ship_city');
            var ship_district = security.decrypt('ship_district');
            var ship_addr     = security.decrypt('ship_addr');
            var ship_tel      = security.decrypt('ship_tel');
            var ship_mobile   = security.decrypt('ship_mobile');
            var ship_name     = security.decrypt('ship_name');
            var memo          = security.decrypt('memo');
            var dly_mobile    = typeof(item['dly_mobile'])!='undefined'?item['dly_mobile']:'';
            var dly_tel       = typeof(item['dly_tel'])!='undefined'?item['dly_tel']:'';
            
            ship_addr = ship_addr.replace(ship_province+ship_city+ship_district,"");

            if(security.decrypt('is_asterisk')){
                ship_tel      = security.decrypt('ship_tel').replace(/(\d{4})(-*)(\d{3,4})(\d{4})/,'$1***$4');
                ship_mobile   = security.decrypt('ship_mobile').replace(/(\d{3})\d{4}(\d{4})/, "$1****$2");
                dly_mobile    = dly_mobile.replace(/(\d{3})\d{4}(\d{4})/, "$1****$2");
                dly_tel       = dly_tel.replace(/(\d{3})\d{4}(\d{4})/, "$1****$2");
            }
            
            if (item['member_uname'] && security.decrypt('uname')) item['member_uname'] = security.decrypt('uname');
            if (item['member_name'] && security.decrypt('name')) item['member_name'] = security.decrypt('name');
            if (item['member_tel'] && ship_tel) item['member_tel'] = ship_tel;
            if (item['ship_detailaddr'] && ship_province) item['ship_detailaddr'] = ship_province+ship_city+ship_district+ship_addr;
            if (item['ship_addr'] && ship_addr) item['ship_addr'] = ship_addr;
            if (item['ship_name'] && ship_name) item['ship_name'] = ship_name;
            if (item['ship_addr_mark'] && ship_addr) item['ship_addr_mark'] = ship_addr+(memo?'  ('+memo+')':'');
            if (item['ship_detailaddr_mark'] && ship_province) item['ship_detailaddr_mark'] = ship_province+ship_city+ship_district+ship_addr+(memo?'  ('+memo+')':'');
            if (item['consignee_addr'] && ship_addr) item['consignee_addr'] = ship_addr;
            if (item['consignee_telephone'] && ship_tel) item['consignee_telephone'] = ship_tel;
            if (item['consignee_mobile'] && ship_mobile) item['consignee_mobile'] = ship_mobile;
            if (item['consignee_name'] && ship_name)   item['consignee_name'] = ship_name;
            if (item['ship_mobile'] && ship_mobile)   item['ship_mobile'] = ship_mobile;
            if (item['dly_mobile'] && dly_mobile)    item['dly_mobile'] = dly_mobile;
            if (item['dly_tel'] && dly_tel)       item['dly_tel']  = dly_tel;
            if (item['ship_tel'] && ship_tel)      item['ship_tel'] = ship_tel;
            return true;
        },
        toUnicode:function(s){
            return s.replace(/([\u4E00-\u9FA5]|[\uFE30-\uFFA0]|[\u0080-\u303F])/g,function(w){
                var code = w.charCodeAt(0).toString(16);

                while (code.length < 4) code = "0" + code;

                return "\\u" + code;
            });
        },
        getTotalPage:function(){},
        getDefaultPrinter:function(data,cp_code){
            var defaultPrinter = {};

            if (!data.printerlist) return 0;

            data.printerlist.every(function(printer){
                if (printer.printername.contains(cp_code)) {
                    defaultPrinter = {
                        name:printer.printername,
                        offsetx:printer.x,
                        offsety:printer.y,
                        is_thermal:printer.isThermal
                    };

                    return false;
                }

                if (data.lastSelected.express == printer.printername) {
                    defaultPrinter = {
                        name:printer.printername,
                        offsetx:printer.x,
                        offsety:printer.y,
                        is_thermal:printer.isThermal
                    };

                    return false;
                }

                if (data.default == printer.printername) {
                    defaultPrinter = {
                        name:printer.printername,
                        offsetx:printer.x,
                        offsety:printer.y,
                        is_thermal:printer.isThermal
                    };

                    return false;
                }
            });

            return defaultPrinter;
        },
        getEncryptPrintData: function() {
            var data = this.options.data;
            if (typeof this.options.data === 'string'){
                data = JSON.decode(data);
            }
            this.iTotal = data.length;
            this.iSucc = 0;
            this.iFail = 0;
            this.queueNum = 5;
            this.finishQueueNum = 0;
            this.encryptPrintDataDialog = new Dialog(new Element("div.tableform",{html:'<div class="division"><div><h4 id="iTitle">正在获取打印数据，请稍等......</h4><div style="margin-left: 5px;">需处理 <span id="total">0</span> 条,已处理 <span id="iTotal" style="color:#083E96">0</span> 条,其中失败 <span id="iFail" style="color:#083E96">0</span> 条。<span id="iMsg" style="color:red"></span> </div></div><div id="processBarBg" class="processBarBg"><div class="processBar" id="processBar">&nbsp;</div></div></div>'}),
            {
                title:'获取打印数据',
                width:600,
                height:130,
                resizeable:false,
            });

            $E('#total', this.encryptPrintDataDialog).setText(this.iTotal);
            for(var i = 0; i < this.queueNum; i++) {
                this.getEncryptPrintDataProcess(i,data);
            }
        },
        getEncryptPrintDataProcess: function(idx,data) {
            var _this = this;
            if(idx >= this.iTotal) {
                this.finishQueueNum++;
                if(this.finishQueueNum<this.queueNum) {
                    return;
                }
                this.options.data = typeof this.options.data === 'string' ? this.toUnicode(JSON.encode(data)) : data;
                if(!$E('#iMsg', this.encryptPrintDataDialog).getHTML()) {
                    _this.encryptPrintDataDialog.close();
                    return;
                }
                var tmpErr = '<br/>获取打印数据完成,三秒后关闭<br/>'+$E('#iMsg', this.encryptPrintDataDialog).getHTML();
                $E('#iMsg', this.encryptPrintDataDialog).setHTML(tmpErr);
                setTimeout(function() {_this.encryptPrintDataDialog.close();}, 3000);
            } else {
                if(data[idx]) {
                    var requestData = {
                        'logi_no':data[idx]['logi_no'],
                        'batch_logi_no':data[idx]['batch_logi_no'],
                        'delivery_id':data[idx]['delivery_id'],
                        'channel_id':data[idx]['channel_id']
                    };
                    var otherRD = this.getEncryptRequestData(data[idx]);
                    if(otherRD) {
                        requestData['custom_data'] = otherRD;
                    }
                    new Request.JSON({
                        url:'index.php?app=logisticsmanager&ctl=admin_waybill&act=getEncryptPrintData',
                        data: requestData,
                        onComplete:function(rs) {
                            if(rs.rsp == 'succ') {
                                data[idx]['json_packet'] = rs.data;
                            } else {
                                _this.iFail++;
                                $E('#iFail', _this.encryptPrintDataDialog).setText(_this.iFail);
                                var tmpErr = $E('#iMsg', _this.encryptPrintDataDialog).getHTML();
                                tmpErr += '<br/>'+data[idx]['logi_no'] + '获取打印数据失败：'+rs.msg;
                                $E('#iMsg', _this.encryptPrintDataDialog).setHTML(tmpErr);
                            }
                            _this.iSucc++;
                            $E('#iTotal', _this.encryptPrintDataDialog).setText(_this.iSucc);
                            $('processBar').setStyle('width',_this.iSucc/_this.iTotal*100+'%');
                            _this.getEncryptPrintDataProcess(idx+_this.queueNum,data);
                        }
                    }).send();
                }
            }
        },
        getEncryptRequestData: function(oriData) {
            return {};
        },
        getUUID:function(len, radix){
            var chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'.split('');
            var uuid = [], i;
            radix = radix || chars.length;
            if (len) {
                for (i = 0; i < len; i++) uuid[i] = chars[0 | Math.random()*radix];
            } else {
                var r;
                uuid[8] = uuid[13] = uuid[18] = uuid[23] = '-';
                uuid[14] = '4';
                for (i = 0; i < 36; i++) {
                    if (!uuid[i]) {
                        r = 0 | Math.random()*16;
                        uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
                    }
                }
            }
            return uuid.join('');
        },
    });
var CaiNiaoPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);

            this.webSocket = new WebSocket('ws://127.0.0.1:13528');

            this.webSocket.onopen = function(event) {
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'printerConfig':
                            this.fireEvent('printerConfig', data);break;
                        case 'print':
                            if (data.previewImage) {
                                this.fireEvent('preview', data);break;
                            } else {
                                this.fireEvent('printComplete', data);break;
                            }
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if( data.taskStatus == 'failed' ) {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event) 
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接菜鸟打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        setPrinterConfig:function(printer){
            if (!this.isReady()) {
                return false;
            }

            var request = this.getRequestObject('printerConfig');
            this.webSocket.send(JSON.stringify(request));

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
                return false;
            }

            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }

            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }

            var request  = this.getRequestObject("getPrinters");
            this.webSocket.send(JSON.stringify(request));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];

            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'));
                if(!jsonPacket) {
                    alert('2016年8月24号之前获取的电子面单，\n或物流公司对应电子面单来源被更改，\n无法打印');
                    return documents;
                }

                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                doc = {
                    documentID:documentID,
                    contents:[]
                };

                // jsonPacket.data.waybillCode = doc.documentID;
                // jsonPacket.templateURL = this.options.template_url?this.options.template_url:jsonPacket.templateURL;
                doc.contents.push(jsonPacket);

                if (this.options.custom_template_url) {
                    var custom = {
                        templateURL:this.options.custom_template_url,
                        data:{}
                    };
                    
                    if (this.options.custom_data) {
                        this.options.custom_data.each(function(d){
                            custom['data'][d] = printData[d];
                        });
                    }
                    doc.contents.push(custom);
                }

                documents.push(doc);
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
            var tasks = []; var taskID = 0;

            var taskDocNum = 10;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject("print");
                objWaybill.task = {
                    taskID:taskID + '_' + this.getUUID(8,10),
                    preview:is_preview,
                    previewType:'image',
                    printer:spooler,
                    documents:[]
                };
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push({
                        contents:val.contents,
                        documentID:(is_preview?1:0) + '_' + val.documentID
                    });
                });

                tasks.push(objWaybill);

                taskID++;
            }

            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.version="1.0";
            request.cmd=cmd;
            return request;
        }
    });

    /**
     * LODOP打印控件
     * @param  {[type]} options){                         this.parent(options);            if (!document.getelementbyid('lodop-funcs')) {                var lodopFuncs [description]
     * @return {[type]}            [description]
     */
    var LodopPrinter = new Class({
        Extends: CaiNiaoPrinter,
        formatData:function(data,spooler,is_preview){
            var documents = [];

            data.each(function(printData){
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : (printData.logi_no ? printData.logi_no : this.getUUID(16));
                doc = {
                    documentID:documentID,
                    contents:[]
                };
                var jsonPacket = {};
                jsonPacket.data = printData;
                jsonPacket.templateURL = this.options.template;
                doc.contents.push(jsonPacket);
                documents.push(doc);
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        print:function(printer){
            if (this.destatus == 'running' || this.destatus == 'fail') {
                return this.fireEvent('printFailure');
            }

            return this.parent(printer);
        }
    });

    var PddPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);

            this.webSocket = new WebSocket('ws://127.0.0.1:5000');

            this.webSocket.onopen = function(event) {
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'print':
                            this.fireEvent('printComplete', data);break;
                        case 'PrintResultNotify':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if( data.taskStatus == 'failed' ) {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接拼多多打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
                return false;
            }

            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }

            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }

            var request  = this.getRequestObject("getPrinters");
            this.webSocket.send(JSON.stringify(request));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];

            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'));
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return documents;
                }

                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                doc = {
                    documentID:documentID,
                    contents:[]
                };
                if(typeof jsonPacket == 'object' && !jsonPacket.encryptedData) {
                    doc.contents.push({data: jsonPacket, user_id:printData.user_id});
                } else {
                    jsonPacket.user_id = printData.user_id;
                    doc.contents.push(jsonPacket);
                }

                if (this.options.custom_template_url) {
                    var custom = {
                        templateURL:this.options.custom_template_url,
                        data:{}
                    };

                    if (this.options.custom_data) {
                        this.options.custom_data.each(function(d){
                            custom['data'][d] = printData[d];
                        });
                    }
                    doc.contents.push(custom);
                }

                documents.push(doc);
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
            var tasks = []; var taskID = 0;

            var taskDocNum = is_preview ? 1 : 10;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject("print");
                objWaybill.task = {
                    taskID:taskID + '_' + this.getUUID(8,10),
                    preview:is_preview,
                    printer:spooler,
                    documents:[]
                };
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push({
                        contents:val.contents,
                        documentID:(is_preview?1:0) + '_' + val.documentID
                    });
                });
                tasks.push(objWaybill);

                taskID++;
            }

            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.version="1.0";
            request.ISVName="商派云起ERP";
            request.ERPId="27ec0c6a2c5e4627ae252560d45bd006";
            request.cmd=cmd;
            return request;
        }
    });


    var KuaishouPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            this.webSocket = new WebSocket('ws://127.0.0.1:16888/ks/printer');

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log(event);
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'rendered':
                            this.fireEvent('preview', data);break;
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.taskStatus == 'failed') {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'))
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                var contents = [];
                contents.push({
                    encryptedData: jsonPacket.printData,
                    signature: jsonPacket.signature,
                    key: jsonPacket.key,
                    templateURL: this.options.template
                });
                if(this.options.custom_template_url) {
                    var customData = {};
                    if(this.options.custom_data) {
                        this.options.custom_data.each(function(d){
                            customData[d] = printData[d];
                        });
                    }
                    contents.push({customData:customData,templateURL:this.options.custom_template_url})
                }
                doc = {
                    documentID:(is_preview ? '1_' : '0_') + documentID,
                    contents:contents,
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = []; var taskID = 0;
          
            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject('print');
                objWaybill.task = {};
                objWaybill.task.printer =  spooler;
                objWaybill.task.preview =  is_preview ? true : false;
                if(is_preview) {
                    objWaybill.task.previewType = 'image';
                }
                objWaybill.task.taskID =  this.getUUID(8);
                objWaybill.task.documents = [];
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log(tasks);
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var DouyinPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            this.getEncryptPrintData();
            this.webSocket = new WebSocket('ws://127.0.0.1:13888');

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log(event);
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'rendered':
                            this.fireEvent('preview', data);break;
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.taskStatus == 'failed') {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接抖音打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = typeof printData.json_packet === 'string' ? JSON.decode(printData.json_packet.replace(/“/g, '"')) : printData.json_packet;
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                var contents = [];
                contents.push({
                    encryptedData: jsonPacket.print_data,
                    signature: jsonPacket.sign,
                    params: jsonPacket.params,
                    templateURL: this.options.template
                });
                if(this.options.custom_template_url) {
                    var customData = {
                        productInfo: printData.bn_name_spec_amount_y,
                        shopName: printData.shop_name,
                        orderId: printData.order_bn,
                        remark: printData.order_memo,
                        productCount: printData.order_count
                    };
                    if(this.options.custom_data) {
                        this.options.custom_data.each(function(d){
                            customData[d] = printData[d];
                        });
                    }
                    contents.push({data:customData,templateURL:this.options.custom_template_url})
                }
                doc = {
                    documentID:(is_preview ? '1_' : '0_') + documentID,
                    contents:contents,
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = []; var taskID = 0;
          
            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject('print');
                objWaybill.task = {};
                objWaybill.task.printer =  spooler;
                objWaybill.task.preview =  is_preview ? true : false;
                if(is_preview) {
                    objWaybill.task.previewType = 'image';
                }
                objWaybill.task.taskID =  this.getUUID(8);
                objWaybill.task.documents = [];
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log(tasks);
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var JdPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            var _this = this;
            setTimeout(function() {
                _this.getEncryptPrintData();
            }, 30);
            this.webSocket = new WebSocket('ws://127.0.0.1:9113');

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {

                    eval('var data = ' + event.data);
                    switch(data.code) {
                        case '6':
                            this.fireEvent('getPrinters', data);break;
                        case '8':
                            this.fireEvent('preview', data);break;
                        case '2':
                            if(data.success) {
                                this.fireEvent('printSuccess', data);break;
                            } else {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接京东打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('GET_Printers');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = printData.json_packet;
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return documents;
                }
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;

                doc = {
                    documentID:documentID,
                    templateURL:this.options.template,
                    templatData:jsonPacket,
                    customTempUrl:this.options.custom_template_url,
                    customData:{'1':'2'},
                };
                

                if(this.options.custom_data) {
                    this.options.custom_data.each(function(d){
                        if (printData[d]) doc['customData'][d] = printData[d];
                    });
                }


                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = []; var taskID = 0;
          
            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject(is_preview ? 'PRE_View' : 'print');
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.parameters = {
                        printName:spooler,
                        tempUrl:val.templateURL,
                        customTempUrl:val.customTempUrl,
                        customData:[],
                        printData:[],
                    };
                    objWaybill.parameters.printData.push(val.templatData);
                    objWaybill.parameters.customData.push(val.customData);
                    objWaybill.key =  (is_preview?1:0) + '_' + val.documentID;
                 
                });
              
                tasks.push(objWaybill);

                taskID++;
            }
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.orderType=cmd;
            return request;
        },
    });

    var WphvipPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            this.getEncryptPrintData();
            this.webSocket = new WebSocket('ws://127.0.0.1:12233');

            this.webSocket.onopen = function(event) {

                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log(event);

                    eval('var data = ' + event.data);
                   // debugger
                   // console.log(data)
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'rendered':
                            this.fireEvent('preview', data);break;
                        case 'print':
                            if(data.code == '200') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.code == '201' || data.code == '203') {
                                this.fireEvent('printFailure', data.details[0]['msg']);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        getEncryptPrintData: function() {
            var data = this.options.data;

            if (typeof this.options.data === 'string'){
                data = JSON.decode(data);
            }
            this.iTotal = data.length;
            this.iSucc = 0;
            this.iFail = 0;
            this.queueNum = 5;
            this.finishQueueNum = 0;
            this.encryptPrintDataDialog = new Dialog(new Element("div.tableform",{html:'<div class="division"><div><h4 id="iTitle">正在获取打印数据，请稍等......</h4><div style="margin-left: 5px;">需处理 <span id="total">0</span> 条,已处理 <span id="iTotal" style="color:#083E96">0</span> 条,其中失败 <span id="iFail" style="color:#083E96">0</span> 条。<span id="iMsg" style="color:red"></span> </div></div><div id="processBarBg" class="processBarBg"><div class="processBar" id="processBar">&nbsp;</div></div></div>'}),
                {
                    title:'获取打印数据',
                    width:600,
                    height:130,
                    resizeable:false,
                });

            $E('#total', this.encryptPrintDataDialog).setText(this.iTotal);
            for(var i = 0; i < this.queueNum; i++) {
                this.getEncryptPrintDataProcess(i,data);
            }
        },
        getEncryptPrintDataProcess: function(idx,data) {
            var _this = this;
            if(idx >= this.iTotal) {
                this.finishQueueNum++;
                if(this.finishQueueNum<this.queueNum) {
                    return;
                }
                this.options.data = typeof this.options.data === 'string' ? this.toUnicode(JSON.encode(data)) : data;
                var tmpErr = '<br/>获取打印数据完成,三秒后关闭<br/>'+$E('#iMsg', this.encryptPrintDataDialog).getHTML();
                $E('#iMsg', this.encryptPrintDataDialog).setHTML(tmpErr);
                setTimeout(function() {_this.encryptPrintDataDialog.close();}, 3000);
            } else {
                if(data[idx]) {
                    new Request.JSON({
                        url:'index.php?app=logisticsmanager&ctl=admin_waybill&act=getEncryptPrintData',
                        data:{'logi_no':data[idx]['logi_no'],'channel_id':data[idx]['channel_id']},
                        onComplete:function(rs) {
                            if(rs.templateUrl != '') {
                                data[idx]['templateUrl'] = rs.templateUrl;
                                data[idx]['store_id'] = rs.store_id;
                            } else {
                                _this.iFail++;
                                $E('#iFail', _this.encryptPrintDataDialog).setText(_this.iFail);
                                var tmpErr = $E('#iMsg', _this.encryptPrintDataDialog).getHTML();
                                tmpErr += '<br/>'+data[idx]['logi_no'] + '获取打印数据失败：'+rs.msg;
                                $E('#iMsg', _this.encryptPrintDataDialog).setHTML(tmpErr);
                            }
                            _this.iSucc++;
                            $E('#iTotal', _this.encryptPrintDataDialog).setText(_this.iSucc);
                            $('processBar').setStyle('width',_this.iSucc/_this.iTotal*100+'%');
                            _this.getEncryptPrintDataProcess(idx+_this.queueNum,data);
                        }
                    }).send();
                }
            }
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接唯品会vip打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                console.log(JSON.stringify(task))
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){

            var documents = [];
            var store_id;
            var senderData = {};

            data.each(function(printData){

                var jsonPacket = typeof printData.json_packet === 'string' ? JSON.decode(printData.json_packet.replace(/“/g, '"')) : printData.json_packet;

                senderData.name = jsonPacket['sender.name'];
                senderData.tel = jsonPacket['sender.tel'];
                senderData.mobile = jsonPacket['sender.mobile'];
                senderData.address = jsonPacket['sender.address'];
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                var printDatas = [];
                store_id = printData.store_id;
                printDatas.push({
                    printId: printData.logi_no,
                    printData: JSON.stringify(jsonPacket),
                    customData: {}
                });
                // if(printData.templateUrl) {
                //     var customData = {
                //         productInfo: printData.bn_name_spec_amount_y,
                //         shopName: printData.shop_name,
                //         orderId: printData.order_bn,
                //         remark: printData.order_memo,
                //         productCount: printData.order_count
                //     };
                //     if(this.options.custom_data) {
                //         this.options.custom_data.each(function(d){
                //             customData[d] = printData[d];
                //         });
                //     }
                //     contents.push({data:customData,templateURL:this.options.custom_template_url})
                // }
                doc = {
                    templateUrl:printData.templateUrl,
                    printDatas:printDatas,
                };

                documents.push(doc);

            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview,store_id,senderData);
        },
        transPrintData:function(documents,spooler,is_preview,store_id,senderData){

            var tasks = []; var taskID = 0;

            var taskDocNum = 1;
            var objWaybill = this.getRequestObject('print');
            objWaybill.task = {};
            objWaybill.task.actionType =  is_preview ? 'PREVIEW' : 'PRINT';
            objWaybill.task.traceId =  this.getUUID(8);
            objWaybill.task.storeId =  store_id;
            objWaybill.task.printerName =  spooler;
            objWaybill.task.printParamDTO = {'templateUrl':documents[0]['templateUrl'],'printDatas':[],'senderData':senderData};
            objWaybill.task.printParamDTO.printDatas.push(documents[0]['printDatas'][0]);
            for (var i = 1,len=documents.length;i<len;i+=taskDocNum) {
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.printParamDTO.printDatas.push(val.printDatas[0]);
                });

            }
            
            tasks.push(objWaybill);
            console.log(tasks)
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var SfPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            var _this = this;
            this.readyState == 0
            var scpOptions = {
                partnerID : this.options.partner_id,
                //env : 'sbox',
                callback : function (result) {
                    _this.fireEvent('open',result);
                    _this.readyState = 1;
                }
            }
            this.SCPPrint = new SCPPrint(scpOptions);
        },
        isReady:function(){
            if (this.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接顺丰打印组件，请稍后再试！'});

                return false;
            }
            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            let _this = this;
            this.formatData(this.options.data,printer.name,false).each(function(task){
                let waybill = {waybill:task.waybill};
                const callback = (result) => {
                    if (result.code === 1) {
                        _this.fireEvent('printSuccess', waybill);
                    } else {
                        _this.fireEvent('printFailure', waybill);
                    }
                };
                delete task.waybill;
                console.log(task);
                this.SCPPrint.print(JSON.decode(JSON.encode(task)), callback, {lodopFn: 'PRINT'});
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            let _this = this;
            const callback = (result) => {
            };
            this.formatData(this.options.data,printer.name,true).each(function(task){
                delete task.waybill;
                console.log(task);
                this.SCPPrint.print(JSON.decode(JSON.encode(task)), callback, {lodopFn: 'PREVIEW'});
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            let _this = this;
            const callback = (result) => {
               if (result.code === 1) {
                  _this.fireEvent('getPrinters', result);
               }
            };
            this.SCPPrint.getPrinters(callback);
            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                let content = {
                    masterWaybillNo: printData.logi_no,
                    isPrintLogo: true,
                    waybillNoCheckType: 2,
                    waybillNoCheckValue: printData.waybill_no_check,
                };
                var jsonPacket = printData.json_packet ? JSON.decode(printData.json_packet.replace(/“/g, '"')) : {};
                if(jsonPacket.mailno_md) {
                    if(documents.length == 1) {
                        documents[0][seq] = 1;
                        documents[0][sum] = data.length;
                    }
                    content.masterWaybillNo = jsonPacket.mailno_md;
                    content.branchWaybillNo = printData.logi_no;
                    content.seq = documents.length + 1;
                    content.sum = data.length;
                }
                if(this.options.custom_template_code) {
                    content.customData = this.options.custom_data;
                }
                documents.push(content);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
            this.SCPPrint.setPrinter(spooler);
            var tasks = []; 
            var taskDocNum = 10;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = new Object();
                objWaybill.accessToken=this.options.access_token;
                objWaybill.templateCode=this.options.template_code;
                objWaybill.customTemplateCode=this.options.custom_template_code;
                objWaybill.requestID=this.getUUID(8, 16);
                objWaybill.documents = [];
                objWaybill.waybill = [];
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.waybill.push(val.branchWaybillNo ? val.branchWaybillNo : val.masterWaybillNo);
                    objWaybill.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            return tasks;
        },
        getUUID:function(len, radix){
            var chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'.split('');
            var uuid = [], i;
            radix = radix || chars.length;
            if (len) {
                for (i = 0; i < len; i++) uuid[i] = chars[0 | Math.random()*radix];
            } else {
                var r;
                uuid[8] = uuid[13] = uuid[18] = uuid[23] = '-';
                uuid[14] = '4';
                for (i = 0; i < 36; i++) {
                    if (!uuid[i]) {
                        r = 0 | Math.random()*16;
                        uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
                    }
                }
            }
            return uuid.join('');
        }
    });

    var XhsPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            if (options.billVersion == 2) {
                // 新版本
                this.webSocket = new WebSocket('ws://localhost:10818');
            } else {
                this.webSocket = new WebSocket('ws://localhost:14528');
            }

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log('====onmessage:', event);
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'print':
                            if (data.previewImage) {
                                this.fireEvent('preview', data);break;
                            } else {
                                this.fireEvent('printComplete', data);break;
                            }
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.taskStatus == 'failed') {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接小红书打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'))
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                var contents = [];
                // contents.push({
                //     encryptedData: jsonPacket.printData,
                //     signature: jsonPacket.signature,
                //     key: jsonPacket.key,
                //     templateURL: this.options.template
                // });
                if (typeof jsonPacket.printData === 'string') {
                    var printDataArr = JSON.decode(jsonPacket.printData.replace(/“/g, '"'))
                } else {
                    var printDataArr = jsonPacket.printData;
                }
                console.log('======printData:',printDataArr);
                contents.push(printDataArr);
                if (this.options.custom_template_url) {

                    var custom = {
                        // templateURL:this.options.custom_template_url,
                        data:{}
                    };
                    if (jsonPacket.customerPrintData) {
                        console.log('======customerPrintJson:',jsonPacket.customerPrintData);
                        custom = JSON.decode(jsonPacket.customerPrintData.replace(/“/g, '"'))
                    }
                    custom['templateURL'] = this.options.custom_template_url;

                    if (this.options.custom_data) {
                        this.options.custom_data.each(function(d){
                            custom['data'][d] = printData[d];
                        });
                    }
                    console.log('======CustomerPrintData:',custom);
                    contents.push(custom);
                } 

                doc = {
                    documentID:(is_preview ? '1_' : '0_') + documentID,
                    contents:contents,
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = []; var taskID = 0;
          
            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject('print');
                objWaybill.task = {};
                objWaybill.task.printer =  spooler;
                objWaybill.task.preview =  is_preview ? true : false;
                if(is_preview) {
                    objWaybill.task.previewType = 'image';
                }
                objWaybill.task.taskID =  this.getUUID(8);
                objWaybill.task.documents = [];
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log(tasks);
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var AikucunPrinter = new Class({
        Extends: Printer,
        printerInfoQueryResolved: false,
        aikucunTaskMap: {},
        aikucunHandledResponseMap: {},
        refreshingEncryptData: false,
        signVerifyDialog: null,
        initialize: function(options){
            this.parent(options);
            this.warmupAikucunPrepareData();
            this.webSocket = new WebSocket('ws://localhost:2750');

            this.webSocket.onopen = function(event) {

                this.webSocket.onmessage = function(event)
                {
                    console.log('====onmessage:', event);
                    eval('var data = ' + event.data);
                    if (this.handleAikucunPrinterInfoQueryResponse(data)) {
                        return;
                    }
                    if (this.handleAikucunPrintResponse(data)) {
                        return;
                    }
                    switch(data.cmd) {
                        case 'print':
                            if (data.previewImage) {
                                this.fireEvent('preview', data);break;
                            } else {
                                this.fireEvent('printComplete', data);break;
                            }
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.taskStatus == 'failed') {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接爱库存打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        showAikucunSignVerifyDialog:function(){
            if (this.signVerifyDialog) {
                return;
            }
            this.signVerifyDialog = new Dialog(new Element("div.tableform",{html:'<div class="division"><div><h4>数据验证中......</h4><div style="margin-left: 5px;">正在请求签名参数，请稍等。</div></div></div>'}),
            {
                title:'打印数据校验',
                width:420,
                height:120,
                resizeable:false,
            });
        },
        closeAikucunSignVerifyDialog:function(){
            if (!this.signVerifyDialog) {
                return;
            }
            this.signVerifyDialog.close();
            this.signVerifyDialog = null;
        },
        parseAikucunJsonPacket:function(jsonPacket){
            if (typeof jsonPacket === 'string') {
                return JSON.decode(jsonPacket.replace(/“/g, '"'));
            }
            return jsonPacket;
        },
        getAikucunOptionData:function(){
            var data = this.options.data;
            if (typeof data === 'string'){
                data = JSON.decode(data);
            }
            return data || [];
        },
        requestAikucunPrepareData:function(printRow, callback){
            new Request.JSON({
                url:'index.php?app=logisticsmanager&ctl=admin_waybill&act=getEncryptPrintData',
                data:{
                    'logi_no':printRow['logi_no'],
                    'batch_logi_no':printRow['batch_logi_no'],
                    'delivery_id':printRow['delivery_id'],
                    'channel_id':printRow['channel_id'],
                    'mode':'prepare'
                },
                onComplete:function(rs) {
                    if (rs && rs.rsp == 'succ' && rs.data) {
                        callback(true, rs.data);
                        return;
                    }
                    callback(false, null);
                }
            }).send();
        },
        warmupAikucunPrepareData:function(){
            var data = this.getAikucunOptionData();
            if (!data || !data.length) {
                return;
            }
            var queueNum = 5;
            var total = data.length;
            var finished = 0;
            var _this = this;
            var process = function(idx){
                if (idx >= total) {
                    finished++;
                    if (finished < queueNum) {
                        return;
                    }
                    _this.options.data = typeof _this.options.data === 'string' ? _this.toUnicode(JSON.encode(data)) : data;
                    return;
                }
                if (!data[idx]) {
                    process(idx + queueNum);
                    return;
                }
                var jsonPacket = _this.parseAikucunJsonPacket(data[idx]['json_packet']);
                var prepareData = _this.resolveAikucunPrepareData(jsonPacket, data[idx]);
                if (prepareData && prepareData.mxEctData) {
                    process(idx + queueNum);
                    return;
                }
                _this.requestAikucunPrepareData(data[idx], function(ok, prepareRsp){
                    if (ok) {
                        data[idx]['json_packet'] = prepareRsp;
                    }
                    process(idx + queueNum);
                });
            };
            for (var i = 0; i < queueNum; i++) {
                process(i);
            }
        },
        refreshAikucunSignData:function(printName, onComplete){
            var data = this.getAikucunOptionData();
            var iTotal = data.length;
            if (!iTotal) {
                if (typeOf(onComplete) === 'function') {
                    onComplete({success: true, failCount: 0, total: 0});
                }
                return;
            }
            var iFail = 0;
            var queueNum = 5;
            var finishQueueNum = 0;
            var _this = this;
            var process = function(idx){
                if (idx >= iTotal) {
                    finishQueueNum++;
                    if (finishQueueNum < queueNum) {
                        return;
                    }
                    _this.options.data = typeof _this.options.data === 'string' ? _this.toUnicode(JSON.encode(data)) : data;
                    if (typeOf(onComplete) === 'function') {
                        onComplete({success: iFail === 0, failCount: iFail, total: iTotal});
                    }
                    return;
                }
                if (!data[idx]) {
                    process(idx + queueNum);
                    return;
                }
                var jsonPacket = this.parseAikucunJsonPacket(data[idx]['json_packet']);
                var prepareData = this.resolveAikucunPrepareData(jsonPacket, data[idx]);
                var continueSign = function(currentPrepareData){
                    if (!currentPrepareData || !currentPrepareData.mxEctData) {
                        iFail++;
                        process(idx + queueNum);
                        return;
                    }
                    new Request.JSON({
                        url:'index.php?app=logisticsmanager&ctl=admin_waybill&act=getEncryptPrintData',
                        data:{
                            'logi_no':data[idx]['logi_no'],
                            'batch_logi_no':data[idx]['batch_logi_no'],
                            'delivery_id':data[idx]['delivery_id'],
                            'channel_id':data[idx]['channel_id'],
                            'print_name':printName ? printName : '',
                            'mode':'sign',
                            'prepare_data':JSON.encode(currentPrepareData)
                        },
                        onComplete:function(rs) {
                            if(rs.rsp == 'succ') {
                                rs.data.prepareData = currentPrepareData;
                                data[idx]['json_packet'] = rs.data;
                            } else {
                                iFail++;
                            }
                            process(idx + queueNum);
                        }
                    }).send();
                };
                if (!prepareData || !prepareData.mxEctData) {
                    this.requestAikucunPrepareData(data[idx], function(ok, prepareRsp){
                        if (!ok) {
                            iFail++;
                            process(idx + queueNum);
                            return;
                        }
                        data[idx]['json_packet'] = prepareRsp;
                        var fallbackPacket = this.parseAikucunJsonPacket(data[idx]['json_packet']);
                        continueSign(this.resolveAikucunPrepareData(fallbackPacket, data[idx]));
                    }.bind(this));
                    return;
                }
                continueSign(prepareData);
            }.bind(this);
            for (var i = 0; i < queueNum; i++) {
                process(i);
            }
        },
        resolveAikucunPrepareData:function(jsonPacket, printData){
            if (!jsonPacket || typeof jsonPacket !== 'object') {
                return null;
            }
            if (jsonPacket.prepareData && typeof jsonPacket.prepareData === 'object' && jsonPacket.prepareData.mxEctData) {
                return JSON.decode(JSON.encode(jsonPacket.prepareData));
            }
            var pd = jsonPacket.printData && typeof jsonPacket.printData === 'object' ? jsonPacket.printData : null;
            if (!pd || !pd.mxEctData) {
                return null;
            }
            return {
                orderId: jsonPacket.orderId ? jsonPacket.orderId : '',
                logisticsNo: pd.logisticsNo ? pd.logisticsNo : (jsonPacket.logisticsNo ? jsonPacket.logisticsNo : (printData.logi_no ? printData.logi_no : '')),
                mxEctData: pd.mxEctData
            };
        },
        executeAikucunPrint:function(printer, isPreview){
            if (!this.isReady()) {
               return false;
            }
            if (this.refreshingEncryptData) {
                this.fireEvent('error', {errmsg:'正在刷新打印签名数据，请稍后再试'});
                return false;
            }
            this.refreshingEncryptData = true;
            this.showAikucunSignVerifyDialog();
            this.refreshAikucunSignData(printer.name, function(result){
                    this.refreshingEncryptData = false;
                    this.closeAikucunSignVerifyDialog();
                    if (!result || result.failCount > 0) {
                        this.fireEvent('error', {errmsg:'获取打印签名数据失败，请重试'});
                        return;
                    }
                    var tasks = this.formatData(this.options.data, isPreview);
                    tasks.each(function(task){
                        this.aikucunTaskMap[task.requestId] = {
                            documentID: task.documentID,
                            is_preview: isPreview
                        };
                        this.aikucunHandledResponseMap[task.requestId] = false;
                        console.log(
                            isPreview
                                ? '======Aikucun send preview payload json(with params):'
                                : '======Aikucun send print payload json(with params):',
                            JSON.stringify(task.payload)
                        );
                        this.webSocket.send(JSON.stringify(task.payload));
                    }.bind(this));
                }.bind(this));
            return true;
        },
        print:function(printer){
            return this.executeAikucunPrint(printer, false);
        },
        preview:function(printer){
            return this.executeAikucunPrint(printer, true);
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            this.printerInfoQueryResolved = false;
            var req = {esubrc: 'printerInfoQuery'};
            this.webSocket.send(JSON.stringify(req));

            return true;
        },
        markPrinterInfoQueryResolved:function(){
            this.printerInfoQueryResolved = true;
        },
        handleAikucunPrinterInfoQueryResponse:function(data){
            if (!data || data.cmd) {
                return false;
            }
            var isPrinterInfoRsp = data.esubrc === 'printerInfoQuery' || typeof data.printers !== 'undefined';
            if (!isPrinterInfoRsp) {
                return false;
            }
            if (String(data.code || '') !== '' && String(data.code) !== '00000') {
                this.markPrinterInfoQueryResolved();
                this.fireEvent('error', {errmsg: data.message ? data.message : '获取打印机列表失败'});
                return true;
            }
            if (this.printerInfoQueryResolved) {
                return true;
            }
            var normalized = this.normalizeAikucunPrintersResponse(data);
            if (!normalized) {
                this.markPrinterInfoQueryResolved();
                this.fireEvent('error', {errmsg: data.message ? data.message : '获取打印机列表失败'});
                return true;
            }
            this.markPrinterInfoQueryResolved();
            this.fireEvent('getPrinters', normalized);

            return true;
        },
        normalizeAikucunPrintersResponse:function(data){
            if (!data || !data.printers || !data.printers.length) {
                return false;
            }
            var printers = [];
            var defaultPrinter = '';
            data.printers.each(function(item){
                var name = item.printName ? item.printName : item.name;
                if (!name) {
                    return;
                }
                printers.push({name: name});
                if (!defaultPrinter && String(item.defaultPrinter) === '1') {
                    defaultPrinter = name;
                }
            });
            if (!printers.length) {
                return false;
            }
            if (!defaultPrinter) {
                defaultPrinter = printers[0].name;
            }

            return {
                printers: printers,
                defaultPrinter: defaultPrinter,
                msg: data.message ? data.message : ''
            };
        },
        extractAikucunPushMessage:function(data){
            var push = data.pushBackMessage ? data.pushBackMessage : {};
            if (push && push.length && push[0]) {
                return push[0];
            }
            return push;
        },
        resolveAikucunResponseRequestId:function(data, push){
            if (push && push.requestId) {
                return String(push.requestId);
            }
            if (data.requestId) {
                return String(data.requestId);
            }
            return '';
        },
        buildAikucunResponsePayload:function(data, meta, push, isSuccess){
            var logiNo = (push && push.logisticsNo) ? push.logisticsNo : '';
            var documentID = meta && meta.documentID ? meta.documentID : ((meta && meta.is_preview ? '1_' : '0_') + (logiNo || this.getUUID(8, 16)));
            var rsp = {
                status: isSuccess ? 'success' : 'fail',
                msg: data.message ? data.message : '',
                printStatus: [{
                    documentID: documentID,
                    status: isSuccess ? 'success' : 'fail',
                    detail: data.message ? data.message : ''
                }],
                taskID: documentID
            };
            if (data.previewImage) {
                rsp.previewImage = data.previewImage;
            }
            return rsp;
        },
        fireAikucunResultEvent:function(meta, isSuccess, rsp){
            if (meta && meta.is_preview) {
                this.fireEvent('preview', rsp);
                return;
            }
            this.fireEvent(isSuccess ? 'printSuccess' : 'printFailure', rsp);
        },
        formatData:function(data,is_preview){
            var tasks = [];
            data.each(function(printData){
                var jsonPacket = this.parseAikucunJsonPacket(printData.json_packet);
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                if (typeof jsonPacket.printData === 'string') {
                    var printDataObj = JSON.decode(jsonPacket.printData.replace(/“/g, '"'))
                } else {
                    var printDataObj = jsonPacket.printData;
                }
                if (!printDataObj || typeof printDataObj !== 'object') {
                    return;
                }
                var payload = JSON.decode(JSON.encode(printDataObj));
                var reqId = payload.requestId ? String(payload.requestId) : this.getUUID(16, 16);
                payload.requestId = reqId;

                var logiNo = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                var docId = logiNo ? logiNo : reqId;
                tasks.push({
                    documentID:(is_preview ? '1_' : '0_') + docId,
                    requestId: reqId,
                    payload: payload,
                    logisticsNo: logiNo
                });
            }.bind(this));

            return tasks;
        },
        handleAikucunPrintResponse:function(data){
            if (!data || data.cmd || (typeof data.code === 'undefined' && typeof data.success === 'undefined')) {
                return false;
            }
            var code = String(data.code);
            var isSuccess = (code === '00000') || (code === '200') || (data.success === true);
            var push = this.extractAikucunPushMessage(data);
            var reqId = this.resolveAikucunResponseRequestId(data, push);
            if (reqId && this.aikucunHandledResponseMap[reqId]) {
                return true;
            }
            var meta = reqId && this.aikucunTaskMap[reqId] ? this.aikucunTaskMap[reqId] : null;
            var rsp = this.buildAikucunResponsePayload(data, meta, push, isSuccess);
            if (reqId && this.aikucunTaskMap[reqId]) {
                delete this.aikucunTaskMap[reqId];
            }
            if (reqId) {
                this.aikucunHandledResponseMap[reqId] = true;
            }
            this.fireAikucunResultEvent(meta, isSuccess, rsp);

            return true;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var WxshipinPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            this.webSocket = new WebSocket('ws://127.0.0.1:12705');

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log('====onmessage:', event);
                    eval('var data = ' + event.data);
                    switch(data.command) {
                        case 'getPrinterList':
                            this.fireEvent('getPrinterList', data);break;
                        case 'print':
                            if(data.results) {
                                this.fireEvent('printSuccess', data);break;
                            } else {
                                this.fireEvent('printFailure', data);break;
                            }
                        case 'preview':
                                this.fireEvent('preview', data);break;
                            
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接视频号打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinterList:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinterList');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'))
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }                

                doc = {
                    taskID:(is_preview ? '1_' : '0_') + printData.logi_no,
                    printInfo:jsonPacket.print_info,
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = [];

            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {            
                var version = '';
                if (is_preview) {
                    var objWaybill = this.getRequestObject('preview');
                } else {
                    var objWaybill = this.getRequestObject('print');
                    objWaybill.printer  = spooler;
                    version = '2.0'; // 打印面单必传
                }
                objWaybill.taskList = [];

                documents.slice(i,i+taskDocNum).each(function(val){
                    val.printNum = {};
                    val.printNum.curNum = i+taskDocNum; // 打印计数-当前张数
                    val.printNum.sumNum = len; // 打印计数-总张数
                    if (version) {
                        objWaybill.version = version;
                    }
                    objWaybill.taskList.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log('======request:', tasks);
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.command=cmd;
            // request.version="1.0";
            return request;
        },
    });

    var DewuppzfPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
        },

        print:function(printer){
            var dewuDataList = this.formatData(this.options.data);

            var sdk_res = [];
            dewuDataList.each(function(dewuData){            
                console.log('======request to dewu sdk:', dewuData);
                tmsPrint(dewuData, callback);

                doc = {
                    waybill_no:dewuData[0].waybill_no,
                };
                sdk_res.push(doc)
            });

            function callback (p){
                console.log('======dewu返回:',p);
                alert(p);
                return false;
            }
            for (var i = 0,len=sdk_res.length;i<len;i+=1) {
                this.fireEvent('printSuccess', sdk_res[i]);
            }

            return true;
        },

        preview:function(){
            var dewuDataList = this.formatData(this.options.data);
            var pdf_url = '';
            var pdf_list = [];

            dewuDataList.each(function(dewuData){
                doc = {
                    pdf_url:dewuData[0].pdf_url,
                };
                pdf_list.push(doc)
            });
            console.log('pdf_url:', pdf_list);
            return pdf_list;
        },

        formatData:function(data){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'));
                jsonPacket = jsonPacket['dewu_express'];

                // var jsonPacket = printData.json_packet;

                // jsonPacket = JSON.parse('"'+jsonPacket+'"');
                // jsonPacket = jsonPacket.replace(/“/g, '"');
                // jsonPacket = jsonPacket.replace(/”/g, '"');
                // jsonPacket = JSON.parse(jsonPacket);
                // jsonPacket = jsonPacket['dewu_express'];

                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return documents;
                }
                documents.push(jsonPacket);
            });
            // console.log(documents);
            return documents;
        },
    });

    var DewuppzfzyPrinter = new Class({
        Extends: Printer,
        requestIdLogiNumber : {},
        initialize: function(options){
            this.parent(options);
            this.webSocket = new WebSocket('ws://127.0.0.1:23825');

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log('====onmessage:', event);
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'print':
                            data.logiNumber = this.requestIdLogiNumber[data.requestID];
                            if(data.status == 'success') {
                                this.fireEvent('printSuccess', data);break;
                            } else {
                                this.fireEvent('printFailure', data);break;
                            }
                        case 'preview':
                            this.fireEvent('preview', data);break;
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接得物自研打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = JSON.decode(printData.json_packet.replace(/“/g, '"'))
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }                

                doc = {
                    logiNumber : printData.logi_no,
                    printData : jsonPacket['dewu_express'][0],
                    customContent : ''
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = [];

            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {            
                if (is_preview) {
                    var objWaybill = this.getRequestObject('preview');
                } else {
                    var objWaybill = this.getRequestObject('print');
                }
                this.requestIdLogiNumber[objWaybill.requestID] = [];
                objWaybill.task = {
                    'isvName' : '商派ERP-云收订服务',
                    'taskID' : this.getUUID(8),
                    'printer' : spooler,
                    'firstDocumentNumber' : i+1,
                    'totalDocumentCount' : documents.length,
                    'documents' : []
                };
                var _this = this;
                documents.slice(i,i+taskDocNum).each(function(val){
                    _this.requestIdLogiNumber[objWaybill.requestID].push(val.logiNumber);
                    delete val.logiNumber;
                    objWaybill.task.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log('======request:', tasks);
            return tasks;
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });

    var Meituan4bulkpurchasingPrinter = new Class({
        Extends: Printer,
        initialize: function(options){
            this.parent(options);
            this.getEncryptPrintData();
            this.webSocket = new WebSocket('ws://127.0.0.1:28613/websocket?'+options.access_token);

            this.webSocket.onopen = function(event) {
             
                // 监听消息
                this.webSocket.onmessage = function(event)
                {
                    console.log(event);
                    eval('var data = ' + event.data);
                    switch(data.cmd) {
                        case 'getPrinters':
                            this.fireEvent('getPrinters', data);break;
                        case 'rendered':
                            this.fireEvent('preview', data);break;
                        case 'notifyPrintResult':
                            if(data.taskStatus == 'printed') {
                                this.fireEvent('printSuccess', data);break;
                            } else if(data.taskStatus == 'failed') {
                                this.fireEvent('printFailure', data);break;
                            }
                    }
                }.bind(this);

                // 监听Socket的关闭
                this.webSocket.onclose = function(event)
                {
                    this.fireEvent('close');
                }.bind(this);

                this.fireEvent('open');
            }.bind(this);

            this.webSocket.onerror = function(event)
            {
                this.fireEvent('error');
            }.bind(this);
        },
        getEncryptRequestData: function(oriData) {
            var ret = {};
            Object.each(this.options.custom_data, function(val) {
                ret[val] = oriData[val];
            })
            return ret;
        },
        isReady:function(){
            if (this.webSocket.readyState == 0) {
                this.fireEvent('error',{errmsg:'正在连接抖音打印组件，请稍后再试！'});

                return false;
            }

            return true;
        },
        print:function(printer){
            if (!this.isReady()) {
               return false;
            }
            this.formatData(this.options.data,printer.name,false).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        preview:function(printer){
            if (!this.isReady()) {
                return false;
            }
            this.formatData(this.options.data,printer.name,true).each(function(task){
                this.webSocket.send(JSON.stringify(task));
            }.bind(this));

            return true;
        },
        getPrinters:function(){
            if (!this.isReady()) {
                return false;
            }
            var text = this.getRequestObject('getPrinters');
            this.webSocket.send(JSON.stringify(text));

            return true;
        },
        formatData:function(data,spooler,is_preview){
            var documents = [];
            data.each(function(printData){
                var jsonPacket = printData.json_packet;
                if(!jsonPacket) {
                    alert('数据结构不完整，\n无法打印');
                    return;
                }
                var documentID = printData.batch_logi_no ? printData.batch_logi_no : printData.logi_no;
                var contents = [];
                contents.push({
                    data: jsonPacket,
                    templateURL: this.options.custom_template_url ? this.options.custom_template_url : this.options.template
                });
                doc = {
                    documentID:(is_preview ? '1_' : '0_') + documentID,
                    contents:contents,
                };
                
                documents.push(doc);
               
            }.bind(this));

            return this.transPrintData(documents,spooler,is_preview);
        },
        transPrintData:function(documents,spooler,is_preview){
          
            var tasks = []; var taskID = 0;
          
            var taskDocNum = 1;
            for (var i = 0,len=documents.length;i<len;i+=taskDocNum) {
                var objWaybill = this.getRequestObject('print');
                objWaybill.task = {};
                objWaybill.task.printer =  spooler;
                objWaybill.task.preview =  is_preview ? true : false;
                if(is_preview) {
                    objWaybill.task.previewType = 'image';
                }
                objWaybill.task.taskID =  this.getUUID(8);
                objWaybill.task.documents = [];
                documents.slice(i,i+taskDocNum).each(function(val){
                    objWaybill.task.documents.push(val);
                });
              
                tasks.push(objWaybill);
            }
            console.log(tasks);
            return tasks;
        },
        getUUID:function(len, radix){
            var chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'.split('');
            var uuid = [], i;
            radix = radix || chars.length;
            if (len) {
                for (i = 0; i < len; i++) uuid[i] = chars[0 | Math.random()*radix];
            } else {
                var r;
                uuid[8] = uuid[13] = uuid[18] = uuid[23] = '-';
                uuid[14] = '4';
                for (i = 0; i < 36; i++) {
                    if (!uuid[i]) {
                        r = 0 | Math.random()*16;
                        uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
                    }
                }
            }
            return uuid.join('');
        },
        getRequestObject:function(cmd){
            var request  = new Object();
            request.requestID=this.getUUID(8, 16);
            request.cmd=cmd;
            request.version="1.0";
            return request;
        },
    });
  
})();

