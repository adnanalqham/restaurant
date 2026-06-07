/**
 * js/native-bridge.js
 * The 'Gold Standard' Bridge between Web and Android Native.
 * Handles Bluetooth SPP printing with automatic fallback and state management.
 */

class NativePrinterBridge {
  constructor() {
    this.retryLimit = 3;
    this.timeoutMs = 5000;
    
    // Check for Android native channels (Flutter or Java WebView)
    this.isNative = !!(window.AndroidPrint || window.FlutterPrinter);
    
    // Check if the user is using the Flutter PrinterBridge channel
    // If the flutter developer used name: 'PrinterBridge' and postMessage
    if (window.PrinterBridge && typeof window.PrinterBridge.postMessage === 'function') {
        this.isNative = true;
        this.flutterLegacy = true; 
    }

    // Check if we need to use Android Intent (Normal Chrome Browser)
    const isAndroid = /Android/i.test(navigator.userAgent);
    if (isAndroid && !this.isNative) {
        this.isIntentBased = true;
        this.isNative = true; // Act as native so we don't fall back globally
    }

    this.statusContainer = null;
    this.init();
  }

  init() {
    console.log('Printer Bridge initialized. Native mode:', this.isNative);
    this.createStatusUI();
    
    // Listen for events FROM Android
    window.onPrinterStatusChange = (status) => this.updateStatusUI(status);
  }

  createStatusUI() {
    // Simple floating status indicator for waiters
    const div = document.createElement('div');
    div.id = 'printer-status-box';
    div.style = 'position:fixed; bottom:10px; right:10px; padding:8px 15px; border-radius:30px; background:#333; color:#fff; font-size:12px; z-index:9999; display:flex; align-items:center; gap:8px; border:1px solid #444;';
    div.innerHTML = `<i class="fas fa-print"></i> <span id="printer-status-label">الجاري التحقق...</span>`;
    document.body.appendChild(div);
    this.statusContainer = div;
    
    if (!this.isNative) {
        this.updateStatusUI('offline_web');
    } else if (this.isIntentBased) {
        this.updateStatusUI('intent_mode');
    }
  }

  updateStatusUI(status) {
    const label = document.getElementById('printer-status-label');
    const box = this.statusContainer;
    
    const states = {
      'connected':    { text: 'طابعة البلوتوث متصلة', color: '#2ecc71' },
      'disconnected': { text: 'طابعة البلوتوث منفصلة', color: '#e74c3c' },
      'connecting':   { text: 'جاري الاتصال بالطابعة...', color: '#f1c40f' },
      'offline_web':  { text: 'الطباعة عبر الشبكة (IP)', color: '#3498db' },
      'intent_mode':  { text: 'وضع تطبيق الطباعة الخارجي', color: '#9b59b6' }
    };

    const state = states[status] || { text: status, color: '#333' };
    label.textContent = state.text;
    box.style.borderColor = state.color;
  }

  /**
   * Enqueues a print job
   * @param {Object} unifiedJob - The Gold Standard JSON schema
   */
  async print(unifiedJob) {
    if (!this.isNative) {
      console.warn('Not in native app, falling back to IP printing immediately.');
      return this.fallbackToIP(unifiedJob);
    }

    let attempt = 1;
    while (attempt <= this.retryLimit) {
      console.log(`Printing attempt ${attempt}...`);
      try {
        const result = await this.sendToNative(unifiedJob);
        if (result.success) {
          showToast('تمت الطباعة بنجاح', 'success');
          this.logResult(unifiedJob.orderId, 'success');
          return true;
        }
      } catch (e) {
        console.error(`Attempt ${attempt} failed:`, e);
      }
      
      attempt++;
      if (attempt <= this.retryLimit) {
        showToast(`فشلت المحاولة ${attempt-1}، جاري إعادة المحاولة...`, 'warning');
        await new Promise(r => setTimeout(r, 1000));
      }
    }

    // All retries failed
    showToast('فشلت طباعة البلوتوث، يتم التحويل للطابعة المركزية...', 'danger');
    this.logResult(unifiedJob.orderId, 'failed', 'Bluetooth max retries reached');
    return this.fallbackToIP(unifiedJob);
  }

  sendToNative(job) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject('Timeout'), this.timeoutMs);
      
      // Callback for Android to call back (if supported)
      const callbackName = 'print_cb_' + Date.now();
      window[callbackName] = (res) => {
        clearTimeout(timer);
        delete window[callbackName];
        resolve(res ? JSON.parse(res) : {success:true});
      };

      try {
          const jsonStr = JSON.stringify(job);
          if (this.flutterLegacy && window.PrinterBridge) {
              window.PrinterBridge.postMessage(jsonStr);
              // Flutter postMessage doesn't return anything or use callbacks easily
              clearTimeout(timer);
              resolve({success: true});
          } else if (window.FlutterPrinter) {
              window.FlutterPrinter.postMessage(jsonStr);
              clearTimeout(timer);
              resolve({success: true});
          } else if (window.AndroidPrint) {
              window.AndroidPrint.enqueueJob(jsonStr, callbackName);
          } else if (this.isIntentBased) {
              const encodedData = encodeURIComponent(jsonStr);
              // Shebaprint scheme defined by the Flutter Developer
              const intentUrl = `intent://print?data=${encodedData}#Intent;scheme=shebaprint;package=com.example.print;end`;
              
              // Secure Modal injected directly into the DOM
              const modal = document.createElement('div');
              modal.style = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:999999; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(5px);';
              modal.innerHTML = `
                <div style="background:#fff; padding:30px; border-radius:15px; text-align:center; max-width:85%; width:400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                    <h3 style="margin-top:0; color:#2c3e50; font-size:1.5rem;"><i class="fas fa-print" style="color:#3498db; margin-left:10px;"></i> تأكيد الطباعة</h3>
                    <p style="color:#7f8c8d; margin-bottom:25px; line-height:1.5;">أنت تستخدم المتصفح. لفتح تطبيق الطباعة آلياً، اضغط على الزر أدناه.</p>
                    <a id="intentPrintBtn" href="${intentUrl}" style="display:block; background:#2ecc71; color:#fff; padding:15px; border-radius:8px; text-decoration:none; font-size:18px; font-weight:bold; box-shadow: 0 4px 6px rgba(46,204,113,0.3); transition:all 0.2s;">
                        تأكيد الطباعة 🖨
                    </a>
                    <button id="cancelIntentBtn" style="margin-top:20px; background:none; border:none; color:#95a5a6; font-size:16px; cursor:pointer; text-decoration:underline;">إلغاء الأمر</button>
                </div>
              `;
              document.body.appendChild(modal);
              
              document.getElementById('intentPrintBtn').onclick = () => {
                  setTimeout(() => {
                      document.body.removeChild(modal);
                      clearTimeout(timer);
                      resolve({success: true});
                  }, 500); // Give browser time to execute intent
              };
              
              document.getElementById('cancelIntentBtn').onclick = () => {
                  document.body.removeChild(modal);
                  clearTimeout(timer);
                  reject('تم الإلغاء');
              };
          } else {
              reject('No native channel found');
          }
      } catch (e) {
          reject(e.message);
      }
    });
  }

  async fallbackToIP(job) {
    // No local print server in this deployment.
    // The Sheba Print companion app handles all printing via Bluetooth polling.
    // If we reach here, it means the intent/channel failed.
    showToast(
      '⚠️ تعذّر الوصول للطابعة. تأكد أن تطبيق Sheba Print مفتوح والـ Polling مُفعَّل.',
      'warning'
    );
    return false;
  }

  async logResult(orderId, status, error = '') {
    await apiCall('/api/print_logs.php', 'POST', {
        order_id: orderId,
        status: status,
        error_message: error,
        printer_type: 'bluetooth'
    });
  }
}

// Global instance
// Make sure not to overwrite the channel if the flutter dev named it exactly PrinterBridge
if (window.PrinterBridge && typeof window.PrinterBridge.postMessage === 'function') {
    // Save flutter channel
    window._flutterPrinterBridge = window.PrinterBridge;
    window.PrinterBridge = new NativePrinterBridge();
    window.PrinterBridge.flutterLegacy = true;
    window.PrinterBridge.isNative = true;
    // Restore postMessage inside our class just in case!
    window.PrinterBridge.postMessage = function(val) { window._flutterPrinterBridge.postMessage(val); };
} else {
    window.PrinterBridge = new NativePrinterBridge();
}
