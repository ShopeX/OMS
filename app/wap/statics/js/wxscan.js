/**
 * WAP 微信 / 企业微信扫码公共方法
 */
(function (window) {
  'use strict';

  var SDK_READY_TIMEOUT_MS = 8000;

  function parseScanResult(resultStr) {
    var finalValue = resultStr || '';
    if (finalValue.indexOf(',') >= 0) {
      var parts = finalValue.split(',');
      if (parts.length >= 2) {
        finalValue = parts[1];
      }
    }
    return finalValue;
  }

  function isWeChatRuntime() {
    var ua = navigator.userAgent || '';
    return /MicroMessenger/i.test(ua) || /wxwork/i.test(ua);
  }

  function isWorkRuntime() {
    return /wxwork/i.test(navigator.userAgent || '');
  }

  function getRuntimeHint() {
    if (isWorkRuntime()) {
      return '请在企业微信内置浏览器中打开（不要用系统浏览器复制链接）';
    }
    return '请在微信/企业微信中打开此页面';
  }

  function invokeScan(config, onSuccess, onFail) {
    if (typeof wx === 'undefined') {
      onFail && onFail(getRuntimeHint());
      return;
    }
    if (!isWeChatRuntime()) {
      onFail && onFail(getRuntimeHint());
      return;
    }

    var isWork = config.scanMode === 'work' || !!config.agentId;
    var wxConfig = {
      debug: false,
      appId: config.appId,
      timestamp: config.timestamp,
      nonceStr: config.nonceStr,
      signature: config.signature,
      jsApiList: ['scanQRCode']
    };
    if (isWork) {
      wxConfig.beta = true;
    }

    var finished = false;
    var readyTimer = null;

    function markDone() {
      if (finished) {
        return false;
      }
      finished = true;
      if (readyTimer) {
        clearTimeout(readyTimer);
        readyTimer = null;
      }
      return true;
    }

    function fail(msg) {
      if (!markDone()) {
        return;
      }
      onFail && onFail(msg);
    }

    function succeed(val) {
      if (!markDone()) {
        return;
      }
      onSuccess && onSuccess(val);
    }

    readyTimer = setTimeout(function () {
      fail('扫码初始化超时，请确认使用可信域名在企业微信中打开后重试');
    }, SDK_READY_TIMEOUT_MS);

    function doScan() {
      wx.scanQRCode({
        needResult: 1,
        scanType: ['qrCode', 'barCode'],
        success: function (scanRes) {
          succeed(parseScanResult(scanRes.resultStr));
        },
        fail: function (res) {
          var msg = (res && res.errMsg) ? res.errMsg : '扫码失败，请重试';
          if (msg.indexOf('function_not_exist') >= 0) {
            msg = '当前企业微信版本过低，请升级后重试';
          }
          fail(msg);
        }
      });
    }

    function runAgentConfig() {
      if (!config.agentId || !config.agentSignature || typeof wx.agentConfig !== 'function') {
        if (isWork && typeof wx.agentConfig !== 'function') {
          fail('企业微信 JS-SDK 未加载完整，请刷新页面重试');
          return;
        }
        doScan();
        return;
      }
      wx.agentConfig({
        corpid: config.appId,
        agentid: String(config.agentId),
        timestamp: config.agentTimestamp || config.timestamp,
        nonceStr: config.agentNonceStr || config.nonceStr,
        signature: config.agentSignature,
        jsApiList: ['scanQRCode'],
        success: doScan,
        fail: function (res) {
          var msg = (res && res.errMsg) ? res.errMsg : '应用配置失败，请刷新重试';
          fail(msg);
        }
      });
    }

    wx.config(wxConfig);
    wx.ready(function () {
      if (isWork) {
        runAgentConfig();
      } else {
        doScan();
      }
    });
    wx.error(function (res) {
      var msg = '微信配置失败，请刷新页面重试';
      if (res && res.errMsg) {
        msg = res.errMsg;
      }
      fail(msg);
    });
  }

  function requestScan(signUrl, options) {
    options = options || {};
    var onSuccess = options.onSuccess;
    var onFail = options.onFail || function () {};
    var pageUrl = window.location.href.split('#')[0];

    if (!signUrl) {
      onFail('扫码签名地址未配置');
      return;
    }

    if (typeof wx === 'undefined') {
      onFail(getRuntimeHint());
      return;
    }
    if (!isWeChatRuntime()) {
      onFail(getRuntimeHint());
      return;
    }

    function handleResponse(res) {
      if (typeof res === 'string') {
        try {
          res = JSON.parse(res);
        } catch (e) {
          onFail('解析响应数据失败');
          return;
        }
      }
      if (!res || res.rsp !== 'succ' || !res.data) {
        onFail((res && res.msg) ? res.msg : '网络错误，请重试');
        return;
      }
      invokeScan(res.data, onSuccess, onFail);
    }

    if (window.jQuery && window.jQuery.ajax) {
      window.jQuery.ajax({
        url: signUrl,
        type: 'POST',
        data: { url: pageUrl },
        timeout: 15000,
        success: handleResponse,
        error: function (xhr, status) {
          if (status === 'timeout') {
            onFail('签名请求超时，请检查网络');
            return;
          }
          onFail('网络错误，请重试');
        }
      });
      return;
    }

    fetch(signUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'url=' + encodeURIComponent(pageUrl)
    })
      .then(function (res) { return res.json(); })
      .then(handleResponse)
      .catch(function () {
        onFail('网络错误，请重试');
      });
  }

  window.wapWxScan = {
    parseScanResult: parseScanResult,
    invokeScan: invokeScan,
    requestScan: requestScan,
    isWeChatRuntime: isWeChatRuntime,
    isWorkRuntime: isWorkRuntime
  };
})(window);
