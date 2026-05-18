/**
 * GCST Order Scanner Module
 * Handles QR/Barcode scanning for Order Retrieval.
 */
const OrderScanner = {
    instance: null,
    isBusy: false,
    lastScannedData: null,
    lastScanTime: 0,

    async open() {
        if (this.isBusy || (this.instance && this.instance.getState() === 2)) return;

        const modal = document.getElementById('qr-modal');
        if (!modal) return;
        const loader = document.getElementById('camera-loading-placeholder');
        const errorDiv = document.getElementById('camera-error-message');

        modal.classList.remove('hidden');
        loader.classList.remove('hidden');
        errorDiv.classList.add('hidden');
        this.isBusy = true;

        // Setup Hardware Input focus
        const hwInput = document.getElementById('hardware-scan-input');
        if (hwInput) {
            hwInput.value = ''; 
            setTimeout(() => hwInput.focus(), 200);
        }

        try {   
            if (!this.instance) {
                this.instance = new Html5Qrcode("reader");
            }

            await this.instance.start(
                { facingMode: "environment" },
                { 
                    fps: 20, 
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                (text) => this.handleScanSuccess(text)
            );
            loader.classList.add('hidden');
        } catch (err) {
            console.error("Scanner Initialization Error:", err);
            loader.classList.add('hidden');
            errorDiv.textContent = "Camera Error: " + err;
            errorDiv.classList.remove('hidden');
        } finally {
            this.isBusy = false;
        }
    },

    async close() {
        if (this.instance) {
            try { await this.instance.stop(); } catch (e) {}
            this.instance = null; // Reset instance to ensure fresh initialization next time
        }
        document.getElementById('qr-modal').classList.add('hidden');
        document.getElementById('camera-loading-placeholder').classList.add('hidden');
        document.getElementById('camera-error-message').classList.add('hidden');
        const successOverlay = document.getElementById('scan-success-overlay');
        if (successOverlay) successOverlay.classList.add('hidden');
        const errorOverlay = document.getElementById('scan-error-overlay');
        if (errorOverlay) errorOverlay.classList.add('hidden');
        const manualOverlay = document.getElementById('manual-entry-overlay');
        if (manualOverlay) manualOverlay.classList.add('hidden');
    },

    resetFromError() {
        const errorOverlay = document.getElementById('scan-error-overlay');
        if (errorOverlay) errorOverlay.classList.add('hidden');
        this.lastScannedData = null; // Clear debounce to allow immediate retry
    },

    showManualEntry() {
        this.resetFromError();
        const manualOverlay = document.getElementById('manual-entry-overlay');
        if (manualOverlay) {
            manualOverlay.classList.remove('hidden');
            const input = document.getElementById('manual-order-input');
            if (input) {
                input.value = '';
                setTimeout(() => input.focus(), 100);
            }
        }
    },

    hideManualEntry() {
        const manualOverlay = document.getElementById('manual-entry-overlay');
        if (manualOverlay) manualOverlay.classList.add('hidden');
        this.lastScannedData = null;
    },

    async handleScanSuccess(decodedText) {
        // Sanitize: trim and remove hidden control characters (common in hardware scanners)
        const cleanText = decodedText.trim().replace(/[\u0000-\x1F\x7F-\x9F]/g, "");
        if (!cleanText) return;

        // Debounce: ignore scans of the same data within 2 seconds
        const now = Date.now();
        if (cleanText === this.lastScannedData && (now - this.lastScanTime) < 2000) return;

        this.lastScannedData = cleanText;
        this.lastScanTime = now;

        // If the scanned text is a renewal reference, route it to the renewal handler instead
        if (cleanText.toUpperCase().startsWith('RENEW-')) {
            if (typeof SCAN_SOUND !== 'undefined') {
                SCAN_SOUND.currentTime = 0;
                SCAN_SOUND.play().catch(() => {});
            }
            this.close();
            if (typeof window.loadRenewalByQR === 'function') window.loadRenewalByQR(cleanText);
            return;
        }

        // Validation pattern for GCST Orders (ORDER-YYYY-XXXX or just the sequence)
        const isValidFormat = /^(ORDER-)?[A-Z0-9-]+$/i.test(cleanText);
        if (!isValidFormat) {
            console.warn("Invalid QR format detected:", cleanText);
            return; // Silently ignore non-system codes to reduce false positives
        }

        await this.loadOrder(cleanText);
    },

    async submitManualEntry() {
        const input = document.getElementById('manual-order-input');
        const code = input?.value.trim().toUpperCase();
        if (!code) {
            alert("Please enter an order number.");
            return;
        }
        this.hideManualEntry();
        await this.loadOrder(code);
    },

    async loadOrder(txnNumber) {
        try {
            if (!txnNumber) return;

            const response = await fetch(`${API_ROOT}/get_order_by_qr.php?transaction_number=${encodeURIComponent(txnNumber)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (!result?.success || !result?.order || !Array.isArray(result?.order?.items)) {
                const errorOverlay = document.getElementById('scan-error-overlay');
                const modal = document.getElementById('qr-modal');
                
                // If modal is open, show visual X animation
                if (errorOverlay && modal && !modal.classList.contains('hidden')) {
                    const errorText = document.getElementById('scan-error-text');
                    if (errorText) errorText.textContent = result?.message || 'Order Not Found';
                    errorOverlay.classList.remove('hidden');
                    
                    if (typeof ERROR_SOUND !== 'undefined') {
                        ERROR_SOUND.currentTime = 0;
                        ERROR_SOUND.play().catch(() => {});
                    }
                    
                    this.lastScannedData = null; // Clear debounce to allow immediate retry
                    return;
                } else {
                    throw new Error(result?.message || 'Order details not found.');
                }
            }

            // SUCCESS handling for Order Found
            const successOverlay = document.getElementById('scan-success-overlay');
            if (successOverlay) successOverlay.classList.remove('hidden');
            
            if (typeof SCAN_SOUND !== 'undefined') {
                SCAN_SOUND.currentTime = 0;
                SCAN_SOUND.play().catch(() => {});
            }

            // Set verification state
            if (typeof state !== 'undefined') state.isQRScanned = true;

            await new Promise(resolve => setTimeout(resolve, 800));
            this.close();

            const order = result.order;

            // Synchronize Page State
            state.transactionType = order.transaction_type;
            const typeEl = document.getElementById('transaction-type');
            if (typeEl) typeEl.value = order.transaction_type;
            
            state.discountPercent = parseFloat(order.discount_percent);
            const discountEl = document.getElementById('discount-percent');
            if (discountEl) discountEl.value = order.discount_percent;

            if (order.student_id) {
                state.studentId = order.student_id;
                const studentEl = document.getElementById('checkout-student-id');
                if (studentEl) studentEl.value = order.student_id;
            }

            // Map items to Cart
            cart = order.items.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: parseInt(item.quantity) || 0,
                unitPrice: 0, // Will be synced with current prices
                selected: true
            }));

            if (typeof updateTransactionSettings === 'function') updateTransactionSettings();
            if (typeof updateCartSummary === 'function') updateCartSummary();
            if (typeof openCheckoutModal === 'function') openCheckoutModal();
            
            alert(`Successfully loaded Order: ${txnNumber}`);
        } catch (err) {
            if (typeof ERROR_SOUND !== 'undefined') {
                ERROR_SOUND.currentTime = 0;
                ERROR_SOUND.play().catch(() => {});
            }
            alert(err.message);
        }
    }
};

// Expose functions for legacy button attributes if necessary
window.openQRScanner = () => OrderScanner.open();
window.closeQRScanner = () => OrderScanner.close();
window.loadOrderByQR = (txn) => OrderScanner.loadOrder(txn);

// Initialize the button listener after the script loads
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('qr-scan-btn');
    if (btn) btn.addEventListener('click', () => OrderScanner.open());
});