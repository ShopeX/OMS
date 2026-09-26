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
 * 自定义Select组件 - 兼容微信小程序端
 * 通用版本，适用于整个项目
 * 解决原生select在微信小程序端的兼容性问题
 */

// 性能优化：防抖函数
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// 检测环境函数
function detectEnvironment() {
  const ua = navigator.userAgent.toLowerCase();
  const isWechat = /micromessenger/i.test(ua);
  const isMac = /macintosh|mac os x/i.test(ua);
  const isWindows = /windows/i.test(ua);
  const isMobile = /mobile|android|iphone|ipad|phone/i.test(ua);

  return {
    isWechat: isWechat,
    isMobile: isMobile,
    isIOS: /iphone|ipad|ipod/i.test(ua),
    isAndroid: /android/i.test(ua),
    isMac: isMac,
    isWindows: isWindows,
    // 专门检测Mac版微信小程序
    isMacWechat: isWechat && isMac && !isMobile,
    // 检测Windows版微信小程序
    isWindowsWechat: isWechat && isWindows && !isMobile,
    // 检测桌面版微信小程序
    isDesktopWechat: isWechat && !isMobile && (isMac || isWindows)
  };
}

// 全局实例管理
window.customSelectInstances = window.customSelectInstances || [];

class CustomSelect {
  constructor(element, options = {}) {
    // 验证输入
    if (!element) {
      throw new Error('CustomSelect: element is required');
    }
    
    this.element = element;
    this.options = {
      placeholder: '请选择',
      searchable: false,
      disabled: false,
      clearable: false,
      maxHeight: '12.5rem',
      onChange: null,
      onOpen: null,
      onClose: null,
      ...options
    };
    
    this.isOpen = false;
    this.selectedValue = '';
    this.selectedText = '';
    this.originalSelect = null;
    this.id = 'custom-select-' + Math.random().toString(36).substr(2, 9);
    
    // 防抖处理
    this.debouncedClose = debounce(() => this.close(), 100);
    
    try {
      this.init();
      // 添加到全局实例管理
      window.customSelectInstances.push(this);
    } catch (error) {
      console.error('CustomSelect initialization failed:', error);
      throw error;
    }
  }
  
  init() {
    // 如果传入的是原生select元素，保存引用并隐藏
    if (this.element.tagName === 'SELECT') {
      this.originalSelect = this.element;
      this.originalSelect.style.display = 'none';
      
      // 获取原生select的选项
      this.optionsData = Array.from(this.originalSelect.options).map(option => ({
        value: option.value,
        text: option.textContent,
        selected: option.selected,
        disabled: option.disabled
      }));
      
      // 获取默认选中项
      const selectedOption = this.optionsData.find(opt => opt.selected);
      if (selectedOption) {
        this.selectedValue = selectedOption.value;
        this.selectedText = selectedOption.text;
      }
      
      // 在原生select后面插入自定义组件
      this.container = this.createCustomSelect();
      
      // 检查是否在.image-text_2容器中
      const imageTextContainer = this.originalSelect.closest('.image-text_2');
      if (imageTextContainer) {
        // 如果在.image-text_2容器中，直接替换到容器内
        imageTextContainer.appendChild(this.container);
      } else {
        // 否则在原生select后面插入
        this.originalSelect.parentNode.insertBefore(this.container, this.originalSelect.nextSibling);
      }
    } else {
      // 如果传入的是容器元素
      this.container = this.element;
      this.optionsData = this.options.data || [];
    }
    
    this.bindEvents();
    this.updateDisplay();
  }
  
  createCustomSelect() {
    const container = document.createElement('div');
    container.className = 'custom-select';
    
    // 复制原生select的类名和属性
    if (this.originalSelect) {
      const classes = this.originalSelect.className.split(' ').filter(cls => cls !== 'select-box');
      container.className += ' ' + classes.join(' ');
      
      // 复制name属性
      if (this.originalSelect.name) {
        container.setAttribute('data-name', this.originalSelect.name);
      }
    }
    
    // 检查是否在.image-text_2容器中，如果是则不显示箭头（使用原有的箭头）
    const isInImageText = this.originalSelect && this.originalSelect.closest('.image-text_2');
    
    // 获取箭头图标路径
    const arrowSrc = this.getArrowIconPath();
    
    container.innerHTML = `
      <div class="custom-select-trigger text-group_2">
        <span class="custom-select-text">${this.selectedText || this.options.placeholder}</span>
        ${!isInImageText ? `<img class="custom-select-arrow" src="${arrowSrc}" alt="arrow" />` : ''}
      </div>
      <div class="custom-select-mask"></div>
      <div class="custom-select-options">
        ${this.renderOptions()}
      </div>
    `;
    
    return container;
  }
  
  getArrowIconPath() {
    // 尝试获取正确的箭头图标路径
    const possiblePaths = [
      './icon-arrow-down.png',
      '../img/icon-arrow-down.png',
      '/app/wap/statics/img/icon-arrow-down.png'
    ];
    
    // 如果页面中已有箭头图标，使用相同路径
    const existingArrow = document.querySelector('.thumbnail_2, img[src*="arrow-down"]');
    if (existingArrow && existingArrow.src) {
      return existingArrow.src;
    }
    
    return possiblePaths[0]; // 默认使用相对路径
  }
  
  renderOptions() {
    if (!this.optionsData || this.optionsData.length === 0) {
      return '<div class="custom-select-empty">暂无选项</div>';
    }
    
    return this.optionsData.map(option => `
      <div class="custom-select-option ${option.selected ? 'selected' : ''}" 
           data-value="${option.value}" 
           ${option.disabled ? 'data-disabled="true"' : ''}>
        ${option.text}
      </div>
    `).join('');
  }
  
  bindEvents() {
    const trigger = this.container.querySelector('.custom-select-trigger');
    const mask = this.container.querySelector('.custom-select-mask');
    const options = this.container.querySelectorAll('.custom-select-option');
    
    // 点击触发器 - 支持触摸和点击
    const handleTriggerActivation = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.toggle();
    };
    
    trigger.addEventListener('click', handleTriggerActivation);
    trigger.addEventListener('touchend', handleTriggerActivation);
    
    // 防止触摸滚动时意外触发
    trigger.addEventListener('touchstart', (e) => {
      this.touchStartY = e.touches[0].clientY;
    });
    
    trigger.addEventListener('touchmove', (e) => {
      if (this.touchStartY) {
        const touchMoveY = e.touches[0].clientY;
        const diff = Math.abs(touchMoveY - this.touchStartY);
        if (diff > 10) { // 如果移动超过10px，认为是滚动
          this.isTouchScrolling = true;
        }
      }
    });
    
    trigger.addEventListener('touchend', (e) => {
      if (this.isTouchScrolling) {
        this.isTouchScrolling = false;
        e.preventDefault();
        return;
      }
      this.touchStartY = null;
    });
    
    // 点击遮罩关闭
    mask.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.close();
    });
    
    // 点击选项 - 优化触摸体验
    options.forEach(option => {
      const handleOptionSelect = (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (option.getAttribute('data-disabled') === 'true') {
          return;
        }
        
        const value = option.getAttribute('data-value');
        const text = option.textContent.trim();
        this.select(value, text);
      };
      
      // 添加触摸反馈
      option.addEventListener('touchstart', (e) => {
        if (option.getAttribute('data-disabled') !== 'true') {
          option.style.backgroundColor = '#f0f0f0';
        }
      });
      
      option.addEventListener('touchend', (e) => {
        option.style.backgroundColor = '';
        handleOptionSelect(e);
      });
      
      option.addEventListener('touchcancel', (e) => {
        option.style.backgroundColor = '';
      });
      
      option.addEventListener('click', handleOptionSelect);
    });
    
    // 阻止选项容器的点击事件冒泡
    const optionsContainer = this.container.querySelector('.custom-select-options');
    optionsContainer.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    // 键盘事件支持
    trigger.addEventListener('keydown', (e) => {
      switch (e.key) {
        case 'Enter':
        case ' ':
          e.preventDefault();
          this.toggle();
          break;
        case 'Escape':
          this.close();
          break;
        case 'ArrowDown':
          e.preventDefault();
          if (!this.isOpen) {
            this.open();
          } else {
            this.selectNext();
          }
          break;
        case 'ArrowUp':
          e.preventDefault();
          if (this.isOpen) {
            this.selectPrevious();
          }
          break;
      }
    });
    
    // 全局点击关闭
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target)) {
        this.close();
      }
    });
  }
  
  toggle() {
    if (this.options.disabled) return;
    
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }
  
  isInCommonModal() {
    return !!(this.container && this.container.closest('#commonModal'));
  }

  setModalSelectExpanded(expanded) {
    if (!this.isInCommonModal()) {
      return;
    }
    const modalContainer = this.container.closest('#commonModal')?.querySelector('.modal-container');
    const fieldWrap = this.container.closest('.select-field-wrap');
    if (modalContainer) {
      modalContainer.classList.toggle('modal-select-open', expanded);
    }
    if (fieldWrap) {
      fieldWrap.classList.toggle('select-field-wrap--expanded', expanded);
    }
  }

  open() {
    if (this.options.disabled || this.isOpen) return;
    
    this.isOpen = true;
    this.container.classList.add('open');
    this.setModalSelectExpanded(true);
    
    // 触发回调
    if (this.options.onOpen) {
      this.options.onOpen();
    }
    
    // 滚动到选中项
    this.scrollToSelected();

    if (this.isInCommonModal()) {
      requestAnimationFrame(() => {
        const optionsEl = this.container.querySelector('.custom-select-options');
        if (optionsEl) {
          optionsEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      });
    }
  }
  
  close() {
    if (!this.isOpen) return;
    
    this.isOpen = false;
    this.container.classList.remove('open');
    this.setModalSelectExpanded(false);
    
    // 触发回调
    if (this.options.onClose) {
      this.options.onClose();
    }
  }
  
  select(value, text) {
    const oldValue = this.selectedValue;
    
    this.selectedValue = value;
    this.selectedText = text;
    
    // 更新显示
    this.updateDisplay();
    
    // 更新原生select的值
    if (this.originalSelect) {
      this.originalSelect.value = value;
      
      // 触发原生select的change事件
      const changeEvent = new Event('change', { bubbles: true });
      this.originalSelect.dispatchEvent(changeEvent);
    }
    
    // 更新选中状态
    this.updateSelectedState();
    
    // 关闭下拉框
    this.close();
    
    // 触发回调
    if (this.options.onChange && oldValue !== value) {
      this.options.onChange(value, text);
    }
  }
  
  updateDisplay() {
    const textElement = this.container.querySelector('.custom-select-text');
    textElement.textContent = this.selectedText || this.options.placeholder;
  }
  
  updateSelectedState() {
    const options = this.container.querySelectorAll('.custom-select-option');
    options.forEach(option => {
      const value = option.getAttribute('data-value');
      if (value === this.selectedValue) {
        option.classList.add('selected');
      } else {
        option.classList.remove('selected');
      }
    });
  }
  
  scrollToSelected() {
    const selectedOption = this.container.querySelector('.custom-select-option.selected');
    if (selectedOption) {
      selectedOption.scrollIntoView({ block: 'nearest' });
    }
  }
  
  selectNext() {
    const options = Array.from(this.container.querySelectorAll('.custom-select-option:not([data-disabled])'));
    const currentIndex = options.findIndex(opt => opt.classList.contains('selected'));
    const nextIndex = currentIndex < options.length - 1 ? currentIndex + 1 : 0;
    
    if (options[nextIndex]) {
      const value = options[nextIndex].getAttribute('data-value');
      const text = options[nextIndex].textContent;
      this.select(value, text);
    }
  }
  
  selectPrevious() {
    const options = Array.from(this.container.querySelectorAll('.custom-select-option:not([data-disabled])'));
    const currentIndex = options.findIndex(opt => opt.classList.contains('selected'));
    const prevIndex = currentIndex > 0 ? currentIndex - 1 : options.length - 1;
    
    if (options[prevIndex]) {
      const value = options[prevIndex].getAttribute('data-value');
      const text = options[prevIndex].textContent;
      this.select(value, text);
    }
  }
  
  // 公共方法
  getValue() {
    return this.selectedValue;
  }
  
  setText(text) {
    this.selectedText = text;
    this.updateDisplay();
  }
  
  setValue(value) {
    const option = this.optionsData.find(opt => opt.value === value);
    if (option) {
      this.select(value, option.text);
    }
  }
  
  disable() {
    this.options.disabled = true;
    this.container.classList.add('disabled');
    this.close();
  }
  
  enable() {
    this.options.disabled = false;
    this.container.classList.remove('disabled');
  }
  
  destroy() {
    try {
      // 关闭下拉框
      this.close();
      
      // 移除全局实例引用
      const index = window.customSelectInstances.indexOf(this);
      if (index > -1) {
        window.customSelectInstances.splice(index, 1);
      }
      
      // 移除DOM元素
      if (this.container && this.container.parentNode) {
        this.container.remove();
      }
      
      // 显示原生select
      if (this.originalSelect) {
        this.originalSelect.style.display = '';
      }
      
      // 清理引用
      this.element = null;
      this.container = null;
      this.originalSelect = null;
      this.optionsData = null;
      
    } catch (error) {
      console.error('CustomSelect destroy failed:', error);
    }
  }
}

// 全局初始化函数
function initCustomSelects(selector = 'select.select-box') {
  const selects = document.querySelectorAll(selector);
  const customSelects = [];
  const errors = [];

  if (selects.length === 0) {
    console.warn('CustomSelect: No select elements found with selector:', selector);
    return customSelects;
  }

  selects.forEach((select, index) => {
    try {
      // 检查是否已经初始化过
      if (select.dataset.customSelectInitialized === 'true') {
        console.warn('CustomSelect: Element already initialized, skipping:', select);
        return;
      }

      const customSelect = new CustomSelect(select, {
        onChange: (value, text) => {
          // 触发自定义事件
          const customEvent = new CustomEvent('customSelectChange', {
            detail: { value, text, element: select },
            bubbles: true
          });
          select.dispatchEvent(customEvent);
        }
      });

      // 标记为已初始化
      select.dataset.customSelectInitialized = 'true';
      customSelects.push(customSelect);

    } catch (error) {
      console.error(`CustomSelect: Failed to initialize select at index ${index}:`, error);
      errors.push({ index, element: select, error });
    }
  });

  if (window.customSelectDebug) {
    console.log(`CustomSelect: Initialized ${customSelects.length} of ${selects.length} selects`);
    if (errors.length > 0) {
      console.warn('CustomSelect: Initialization errors:', errors);
    }
  }

  return customSelects;
}

// 自动初始化
document.addEventListener('DOMContentLoaded', () => {
  const env = detectEnvironment();

  // 专门针对Mac版微信小程序和移动端微信的兼容性问题
  let shouldInitCustomSelect = false;
  let initReason = '';

  if (env.isMacWechat) {
    shouldInitCustomSelect = true;
    initReason = 'Mac版微信小程序select兼容性问题';
  } else if (env.isWindowsWechat) {
    shouldInitCustomSelect = true;
    initReason = 'Windows版微信小程序select兼容性问题';
  } else if (env.isWechat && env.isMobile) {
    shouldInitCustomSelect = true;
    initReason = '移动端微信select兼容性问题';
  } else if (env.isMobile) {
    shouldInitCustomSelect = true;
    initReason = '移动端浏览器select体验优化';
  }

  if (shouldInitCustomSelect) {
    initCustomSelects();

    if (window.customSelectDebug) {
      console.log('CustomSelect 启用原因:', initReason);
    }
  }

  // 调试信息
  if (window.customSelectDebug) {
    console.log('CustomSelect Environment:', env);
    console.log('CustomSelect 是否启用:', shouldInitCustomSelect);
    console.log('CustomSelect 启用原因:', initReason);
    console.log('CustomSelect Instances:', window.customSelectInstances);
  }
});

// 导出类和初始化函数
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { CustomSelect, initCustomSelects };
} else {
  window.CustomSelect = CustomSelect;
  window.initCustomSelects = initCustomSelects;
}
