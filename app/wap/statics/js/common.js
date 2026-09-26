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

class Modal {
  constructor() {
    this.modal = document.getElementById('commonModal')
    this.mask = this.modal.querySelector('.modal-mask')
    this.container = this.modal.querySelector('.modal-container')
    this.closeBtn = this.modal.querySelector('.modal-close')
    this.confirmBtn = this.modal.querySelector('.modal-confirm')
    this.cancleBtn = this.modal.querySelector('.modal-cancle')
    this.title = this.modal.querySelector('.modal-title')
    this.tips = this.modal.querySelector('.modal-tips')
    this.content = this.modal.querySelector('.modal-content')

    this.bindEvents()
  }

  bindEvents() {
    this.closeBtn.onclick = () => modal.hide()
    this.mask.onclick = () => modal.hide()
  }

  show(options) {
    const {
      title = '',
      tips = '',
      fields = [], // 表单字段配置数组
      onConfirm = () => {},
      onCancel = () => {},
      confirmText = '确定',
      cancelText = '取消',
      isShowCancleBtn = false
    } = options

    this.title.textContent = title

    if (tips) {
      this.tips.textContent = tips
    }

    // 生成表单内容
    const formContent = fields
      .map((field) => {
        const {
          type,
          name,
          label,
          placeholder = '',
          options = [],
          required = false,
          html = '',
          append = ''
        } = field

        let inputHtml = ''
        if (type === 'input') {
          const inputId = `modal-input-${name}-${Date.now()}`
          const inputEl = `
        <input type="text" 
               id="${inputId}"
               class="modal-input${append ? ' modal-input--append' : ''}" 
               name="${name}" 
               placeholder="${placeholder}"
               autocomplete="off"
               autocorrect="off"
               autocapitalize="off"
               enterkeyhint="done"
               ${required ? 'required' : ''}>
      `
          if (append) {
            inputHtml = `
        <div class="input-field-wrap input-with-append flex-row align-center">
          ${inputEl}
          ${append}
        </div>
      `
          } else {
            inputHtml = `
        <div class="input-field-wrap">
          ${inputEl}
        </div>
      `
          }
          return `
      <div class="modal-form-item modal-form-item--input">
        <div class="input-box-vertical flex-col">
          <label class="modal-form-label" for="${inputId}">${label}${required ? ' <span class="required-mark">*</span>' : ''}</label>
          ${inputHtml}
        </div>
      </div>
    `
        } else if (type === 'textarea') {
          inputHtml = `
        <textarea class="modal-textarea" 
                  name="${name}" 
                  placeholder="${placeholder}"
                  ${required ? 'required' : ''}></textarea>
      `
          return `
      <div class="modal-form-item">
        <div class="flex-col">
          <label>${label}${required ? ' <span style="color: red">*</span>' : ''}</label>
          ${inputHtml}
        </div>
      </div>
    `
        } else if (type === 'select') {
          const optionsHtml = options
            .map((opt) => `<option value="${opt.value}">${opt.label}</option>`)
            .join('')

          inputHtml = `
        <select class="modal-select select-box"
                name="${name}"
                ${required ? 'required' : ''}>
          <option value="">请选择${label}</option>
          ${optionsHtml}
        </select>
      `
          return `
      <div class="modal-form-item modal-form-item--select">
        <div class="select-box-vertical flex-col">
          <label class="modal-form-label">${label}${required ? ' <span class="required-mark">*</span>' : ''}</label>
          <div class="select-field-wrap">
            ${inputHtml}
          </div>
        </div>
      </div>
    `
        } else if (type === 'date') {
          inputHtml = `
        <input type="date" 
               class="date-input"
               max="${new Date().toISOString().split('T')[0]}" 
               name="${name}" 
               placeholder="${placeholder}"
               ${required ? 'required' : ''}>
      `
          return `
      <div class="modal-form-item">
        <div class="input-box flex-row align-center justify-between">
          <label>${label}${required ? ' <span style="color: red">*</span>' : ''}</label>
          ${inputHtml}
        </div>
      </div>
    `
        } else if (type === 'html') {
          inputHtml = html
          return `
      <div class="modal-form-item">
        ${inputHtml}
      </div>
    `
        } else if (type === 'image') {
          return `
          <div class="modal-form-item">
            ${field.label ? `<label class="modal-form-label">${field.label}${field.required ? ' <span class="required">*</span>' : ''}</label>` : ''}
            <div class="modal-form-file-wrapper">
              <input type="file" 
                class="modal-form-file" 
                name="${field.name}" 
                accept="image/*"
                ${field.required ? 'required' : ''}
                multiple
              >
              <div class="file-preview-container"></div>
            </div>
          </div>
        `;
        }
      })
      .join('')

    this.content.innerHTML = formContent
    this.confirmBtn.innerHTML = confirmText
    this.cancleBtn.innerHTML = cancelText
    this.confirmBtn.style.display = 'block'
    this.confirmBtn.disabled = false
    this.confirmBtn.style.opacity = '1'
    this.cancleBtn.style.display = isShowCancleBtn ? 'block' : 'none'
    // 绑定确认按钮事件
    const submitForm = async (cb) => {
      const formData = {}
      let isValid = true

      for (const field of fields) {
        if (field.type === 'image') { 
          const fileInput = this.modal.querySelector('input[type="file"]');
          // 直接使用保存的 base64 列表
          if (field.required && fileInput.uploadedBase64List.length === 0) {
            showToast(`请上传${field.label}`)
            isValid = false
            return
          }
          if (field.required && fileInput.uploadedBase64List.length > 4) {
            showToast(`图片最多上传4张`)
            isValid = false
            return
          }
          formData[field.name] = fileInput.uploadedBase64List || [];
        } else if (field.type !== 'html') {
          const element = this.content.querySelector(`[name="${field.name}"]`)
          const value = element.value.trim()

          if (field.required && !value) {
            showToast(`请${field.type === 'select' ? '选择' : '填写'}${field.label}`)
            isValid = false
            return
          }

          formData[field.name] = value
        }
      }

      if (isValid && cb) {
        cb(formData)
      }
    }

    this.confirmBtn.onclick = () => {
      submitForm(onConfirm)
    }

    this.cancleBtn.onclick = () => {
      // 取消按钮事件
      submitForm(onCancel)
    }

    this.modal.style.display = 'block'

    // 初始化自定义select组件 - 解决Mac版微信小程序兼容性问题
    this.initCustomSelects();

    // 扩大 iOS 输入框可点击区域，点击容器即可聚焦
    this.initInputFocus();

    // 初始化图片上传
    this.initFileUpload();
  }

  initInputFocus() {
    this.content.querySelectorAll('.modal-form-item--input .input-field-wrap').forEach((wrap) => {
      wrap.addEventListener('click', (e) => {
        if (e.target.closest('.scan-icon') || e.target.closest('.logi-no-confirm-btn')) {
          return;
        }
        const input = wrap.querySelector('input');
        if (!input || document.activeElement === input) {
          return;
        }
        input.focus();
        if (typeof input.setSelectionRange === 'function') {
          const len = input.value.length;
          try {
            input.setSelectionRange(len, len);
          } catch (err) {}
        }
      });
    });
  }

  hide() {
    this.modal.style.display = 'none'
  }

  // 图片上传
  initFileUpload() {
    const fileInputs = this.modal.querySelectorAll('input[type="file"]');
    if(fileInputs.length === 0) {
      return;
    }

    fileInputs.forEach(input => {
      const previewContainer = input.parentElement.querySelector('.file-preview-container');
      const uploadBtn = document.createElement('label');
      uploadBtn.className = 'upload-btn';
      uploadBtn.setAttribute('for', `file-input-${Date.now()}`); // 生成唯一ID
      uploadBtn.innerHTML = `<i class="upload-icon">+</i>`;

      input.id = uploadBtn.getAttribute('for'); // 设置对应的ID
      
      previewContainer.appendChild(uploadBtn);
      input.style.display = 'none';
      
      // 存储所有已上传文件的 base64 数据
      input.uploadedBase64List = [];
      // 存储已上传的文件对象
      let uploadedFiles = [];
      
      uploadBtn.addEventListener('click', (e) => {
        e.preventDefault(); // 阻止事件冒泡
        e.stopPropagation(); // 阻止事件传播
        input.click();
      });
      
      input.addEventListener('change', (e) => {
        const newFiles = Array.from(e.target.files);
        
        newFiles.forEach(file => {
          if (!file.type.startsWith('image/')) {
            showToast('请上传图片文件');
            return;
          }
          
          if (uploadedFiles.length >= 3) {
            showToast('最多上传4张图片');
            return;
          }
          
          const reader = new FileReader();
          reader.onload = (e) => {
            const base64Data = e.target.result;
            // 保存 base64 数据
            input.uploadedBase64List.push(base64Data);
            
            const previewWrapper = document.createElement('div');
            previewWrapper.className = 'file-preview-wrapper';
            
            const preview = document.createElement('img');
            preview.src = base64Data;
            preview.className = 'file-preview';
            
            const deleteBtn = document.createElement('span');
            deleteBtn.className = 'file-delete-btn';
            deleteBtn.innerHTML = '×';
            deleteBtn.onclick = () => {
              previewWrapper.remove();
              // 从已上传文件中移除
              const index = uploadedFiles.indexOf(file);
              if (index > -1) {
                uploadedFiles.splice(index, 1);
                input.uploadedBase64List.splice(index, 1);
              }
              
              // 显示上传按钮
              if(uploadedFiles.length < 4) {
                uploadBtn.style.display = 'flex';
              }
            };
            
            previewWrapper.appendChild(preview);
            previewWrapper.appendChild(deleteBtn);
            previewContainer.insertBefore(previewWrapper, uploadBtn);
            
            // 添加到已上传文件
            uploadedFiles.push(file);
            
            // 隐藏上传按钮
            if(uploadedFiles.length >= 3) {
              uploadBtn.style.display = 'none'; 
            }
          };
          reader.readAsDataURL(file);
        });
        
      });
    });
  }

  // 初始化自定义select组件 - 解决Mac版微信小程序兼容性问题
  initCustomSelects() {
    // 检查是否有自定义select组件可用
    if (typeof window.initCustomSelects === 'function') {
      // 只初始化弹出框内的select组件
      const modalSelects = this.modal.querySelectorAll('select.select-box');
      if (modalSelects.length > 0) {
        modalSelects.forEach(select => {
          // 检查是否已经初始化过
          if (select.dataset.customSelectInitialized !== 'true') {
            try {
              new window.CustomSelect(select, {
                onChange: (value, text) => {
                  // 触发原生select的change事件，保持兼容性
                  const changeEvent = new Event('change', { bubbles: true });
                  select.dispatchEvent(changeEvent);

                  if (window.customSelectDebug) {
                    console.log('Modal select changed:', select.name, value, text);
                  }
                }
              });
              select.dataset.customSelectInitialized = 'true';
            } catch (error) {
              console.error('Failed to initialize custom select in modal:', error);
            }
          }
        });
      }
    }
  }
}

// 请求配置和方法封装
const request = axios.create({
  baseURL: '/api', // 设置基础URL
  timeout: 30000, // 设置超时时间
  headers: {
    'Content-Type': 'multipart/form-data'
  },

  // 兼容对象数组
  // transformRequest: [
  //   function (data) {
  //     // 将请求数据转换为FormData格式
  //     const formData = new FormData()
      
  //     const appendData = (key, value) => {
  //       if (value === null || value === undefined) {
  //         return
  //       }
        
  //       if (Array.isArray(value)) {
  //         // 处理数组
  //         value.forEach((item, index) => {
  //           if (typeof item === 'object' && item !== null) {
  //             // 处理对象数组，使用 key[index][property] 的格式
  //             Object.keys(item).forEach(prop => {
  //               formData.append(`${key}[${index}][${prop}]`, item[prop])
  //             })
  //           } else {
  //             // 处理普通数组
  //             formData.append(`${key}[${index}]`, item)
  //           }
  //         })
  //       } else if (typeof value === 'object' && value !== null) {
  //         // 处理对象
  //         Object.keys(value).forEach(prop => {
  //           appendData(`[${prop}][${key}]`, value[prop])
  //         })
  //       } else {
  //         // 处理基本类型
  //         formData.append(key, value)
  //       }
  //     }
      
  //     Object.keys(data).forEach(key => {
  //       appendData(key, data[key])
  //     })
      
  //     return formData
  //   }
  // ]
})

// 请求拦截器
request.interceptors.request.use(
  (config) => {
    // 可以在这里添加loading状态
    // 添加token等通用headers
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
request.interceptors.response.use(
  (response) => {
    // 可以统一处理响应数据
    if (response.data.rsp != 'fail') {
      return response.data
    }
    const errMsg = response.data.msg || '请求失败'
    if (typeof showToast === 'function') {
      showToast(errMsg)
    } else {
      console.error(errMsg)
    }
    return Promise.reject(response.data)
  },
  (error) => {
    const errMsg = (error && error.msg) ? error.msg : '网络请求失败，请稍后重试'
    if (typeof showToast === 'function') {
      showToast(errMsg)
    } else {
      console.error(errMsg)
    }
    return Promise.reject(error)
  }
)

// Toast提示组件
const Toast = {
  error(msg) {
    if (typeof showToast === 'function') {
      showToast(msg)
    } else {
      console.error(msg)
    }
  }
}

// 提示Toast封装
const showToast = (msg) => {
  const toast = document.getElementById('toast')
  toast.classList.add('show')
  toast.textContent = msg
  // 动画结束后移除类名和隐藏元素
  setTimeout(() => {
    toast.classList.remove('show')
  }, 1500)
}

const showLoading = (msg) => {
  const toast = document.getElementById('toast')
  toast.classList.add('show')
  toast.textContent = msg
}

const hideLoading = (msg) => {
  const toast = document.getElementById('toast')
  toast.classList.remove('show')
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('.icon-back').onclick = () => {
    history.back()
  }
  document.querySelector('.icon-refresh').onclick = () => {
    location.reload()
  }
})

  // 备注展开收缩功能
const toggleRemark = () => {
  const remarkContents = document.querySelectorAll('.remark-content');

  if (remarkContents.length > 0) {
    remarkContents.forEach(content => {
      // 默认收起
      content.classList.add('collapsed');

      // 找到相邻的箭头图标
      const arrowIcon = content.nextElementSibling;
      if (arrowIcon && arrowIcon.classList.contains('arrow-icon')) {
        // 默认箭头朝上
        // arrowIcon.classList.add('expanded');

        // 添加点击事件
        arrowIcon.addEventListener('click', function() {
          // 切换展开/收缩状态
          if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            content.classList.add('collapsed');
            arrowIcon.classList.remove('expanded');
          } else {
            content.classList.remove('collapsed');
            content.classList.add('expanded');
            arrowIcon.classList.add('expanded');
          }
        });
      }
    });
  }
}

/**
 * 商品缩略图点击预览大图
 * 用法：给 img 加上 class="js-img-preview"，或 data-preview-src
 */
var ImagePreview = {
  overlay: null,
  init: function () {
    if (this._bound) return
    this._bound = true
    var self = this
    document.addEventListener('click', function (e) {
      var target = e.target
      if (!target || target.tagName !== 'IMG') return
      var previewSrc = target.getAttribute('data-preview-src')
      if (!previewSrc && !target.classList.contains('js-img-preview')) return
      e.preventDefault()
      e.stopPropagation()
      self.show(previewSrc || target.getAttribute('src') || target.src)
    }, true)
  },
  ensureOverlay: function () {
    if (this.overlay) return this.overlay
    var overlay = document.createElement('div')
    overlay.className = 'goods-img-preview-mask'
    overlay.innerHTML = [
      '<div class="goods-img-preview-box">',
      '  <img class="goods-img-preview-img" alt="商品大图" referrerpolicy="no-referrer" />',
      '  <button type="button" class="goods-img-preview-close" aria-label="关闭">×</button>',
      '</div>'
    ].join('')
    document.body.appendChild(overlay)
    var self = this
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.classList.contains('goods-img-preview-close')) {
        self.hide()
      }
    })
    this.overlay = overlay
    return overlay
  },
  show: function (src) {
    if (!src) return
    var overlay = this.ensureOverlay()
    var img = overlay.querySelector('.goods-img-preview-img')
    img.src = src
    overlay.style.display = 'flex'
    document.body.style.overflow = 'hidden'
  },
  hide: function () {
    if (!this.overlay) return
    this.overlay.style.display = 'none'
    document.body.style.overflow = ''
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    ImagePreview.init()
  })
} else {
  ImagePreview.init()
}

// export { Modal, request, Toast, showToast }
// 如果你想让这些在全局可用，可以添加到 window 对象
window.Modal = Modal
window.request = request
window.Toast = Toast
window.showToast = showToast
window.showLoading = showLoading
window.hideLoading = hideLoading
window.toggleRemark = toggleRemark
window.ImagePreview = ImagePreview
