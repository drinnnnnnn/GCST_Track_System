/**
 * GCST Order Scanner Module
 * Handles QR/Barcode scanning for Order Retrieval.
 */
const OrderScanner = {
    instance: null,
    isBusy: false,

    async open() {
        if (this.isBusy || (this.instance && this.instance.getState() === 2)) return;

        const modal = document.getElementById('qr-modal');
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
                { fps: 15, qrbox: { width: 250, height: 250 } },
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
    },

    handleScanSuccess(decodedText) {
        // Provide audio feedback
        if (typeof SCAN_SOUND !== 'undefined') {
            SCAN_SOUND.currentTime = 0;
            SCAN_SOUND.play().catch(() => {});
        }
        this.close();

        // If the scanned text is a renewal reference, route it to the renewal handler instead
        if (decodedText.startsWith('RENEW-') && typeof window.loadRenewalByQR === 'function') {
            window.loadRenewalByQR(decodedText);
            return;
        }

        this.loadOrder(decodedText);
    },

    async loadOrder(txnNumber) {
        try {
            if (!txnNumber) return;

            const response = await fetch(`${API_ROOT}/get_order_by_qr.php?transaction_number=${encodeURIComponent(txnNumber)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            // Hardened check using optional chaining and items array validation
            if (!result?.success || !result?.data?.order || !Array.isArray(result?.data?.order?.items)) {
                console.error("Order Load Failure. Server Response:", result);
                throw new Error(result?.message || 'Order details not found or invalid in server response.');
            }

            const order = result.data.order;

            // Synchronize Page State
            state.transactionType = order.transaction_type;
            document.getElementById('transaction-type').value = order.transaction_type;
            
            state.discountPercent = parseFloat(order.discount_percent);
            const discountEl = document.getElementById('discount-percent');
            if (discountEl) discountEl.value = order.discount_percent;

            if (order.student_id) {
                state.studentId = order.student_id;
                const studentEl = document.getElementById('checkout-student-id');
                if (studentEl) studentEl.value = order.student_id;
                if (typeof checkStudentId === 'function') checkStudentId(); 
            }

            // Map items to Cart
            cart = order.items.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: parseInt(item.quantity) || 0,
                unitPrice: parseFloat(item.unit_price) || 0,
                selected: true
            }));

            if (typeof updateCartSummary === 'function') updateCartSummary();
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