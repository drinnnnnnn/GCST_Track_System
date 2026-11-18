(() => {
  const state = {
    csrfToken: '',
    transactionNumber: '',
    page: 1,
    totalPages: 1,
    adminPage: 1,
    adminTotalPages: 1,
    pendingAdminAction: null
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[char]));

  const formatDate = (value) => {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString();
  };

  const formatStatus = (status) => ({
    pending: 'Awaiting review',
    refund_pending: 'Refund to be paid',
    refunded: 'Refunded',
    replacement_pending: 'Replacement to be fulfilled',
    completed: 'Completed',
    rejected: 'Rejected'
  }[status] || status);

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, { cache: 'no-store', ...options });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'The request could not be completed.');
    }
    if (result.csrf_token) state.csrfToken = result.csrf_token;
    return result;
  }

  function setReturnFeedback(message, isError = false) {
    const feedback = document.getElementById('product-return-feedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = isError ? 'text-sm text-rose-600' : 'text-sm text-emerald-700';
  }

  async function openReturnModal(transactionNumber) {
    const modal = document.getElementById('product-return-modal');
    const select = document.getElementById('product-return-item');
    const quantity = document.getElementById('product-return-quantity');
    const form = document.getElementById('product-return-form');
    if (!modal || !select || !form) return;

    state.transactionNumber = transactionNumber;
    form.reset();
    const submit = document.getElementById('product-return-submit');
    if (submit) {
      submit.disabled = false;
      submit.textContent = 'Submit return request';
    }
    select.innerHTML = '<option value="">Loading purchased items...</option>';
    document.getElementById('product-return-order-number').textContent = transactionNumber;
    setReturnFeedback('');
    modal.classList.remove('hidden');

    try {
      const result = await fetchJson(`../../actions/get_product_return_items.php?transaction_number=${encodeURIComponent(transactionNumber)}`);
      select.innerHTML = '<option value="">Select a product</option>';
      result.items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = `${item.product_name} — ${item.remaining_quantity} ${item.unit_name || ''} available`;
        option.dataset.remaining = item.remaining_quantity;
        select.appendChild(option);
      });
      if (result.items.length === 0) {
        select.innerHTML = '<option value="">No quantity remains eligible for return</option>';
        setReturnFeedback('All quantities from this order already have return requests.', true);
      }
      if (quantity) {
        quantity.disabled = result.items.length === 0;
        quantity.value = '';
      }
    } catch (error) {
      select.innerHTML = '<option value="">Unable to load purchased items</option>';
      setReturnFeedback(error.message, true);
    }
  }

  function closeReturnModal() {
    document.getElementById('product-return-modal')?.classList.add('hidden');
  }

  async function submitProductReturn(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = document.getElementById('product-return-submit');
    const formData = new FormData(form);
    formData.set('transaction_number', state.transactionNumber);

    if (!state.csrfToken) {
      setReturnFeedback('Refresh the page and try again.', true);
      return;
    }

    submit.disabled = true;
    submit.textContent = 'Submitting...';
    let requestCreated = false;
    try {
      const result = await fetchJson('../../actions/create_product_return.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': state.csrfToken },
        body: formData
      });
      requestCreated = true;
      const emailFailed = result.email_status === 'failed';
      setReturnFeedback(result.message, emailFailed);
      await Promise.all([loadCustomerReturns(1), window.loadTransactionHistory?.(window.__txnCurrentPage || 1)]);
      if (emailFailed) {
        submit.textContent = 'Request submitted';
      } else {
        window.setTimeout(closeReturnModal, 900);
      }
    } catch (error) {
      setReturnFeedback(error.message, true);
    } finally {
      if (!requestCreated) {
        submit.disabled = false;
        submit.textContent = 'Submit return request';
      }
    }
  }

  async function loadCustomerReturns(page = 1) {
    const body = document.getElementById('product-return-history-body');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="6" class="px-4 py-4 text-center text-slate-500">Loading return requests...</td></tr>';

    try {
      const params = new URLSearchParams({ page: String(page), limit: '10' });
      const status = document.getElementById('product-return-history-status')?.value || 'all';
      const search = document.getElementById('product-return-history-search')?.value.trim() || '';
      if (status !== 'all') params.set('status', status);
      if (search) params.set('search', search);
      const result = await fetchJson(`../../actions/get_product_returns.php?${params.toString()}`);
      state.page = result.current_page;
      state.totalPages = result.total_pages;
      if (!result.requests.length) {
        const emptyMessage = status !== 'all' || search ? 'No matching return requests.' : 'No return requests yet.';
        body.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center text-slate-500">${emptyMessage}</td></tr>`;
      } else {
        body.innerHTML = result.requests.map((request) => {
          const handoff = request.handoff_received_at
            ? `Received<br><small>${escapeHtml(formatDate(request.handoff_received_at))}</small>`
            : 'Awaiting handoff';
          const status = request.status === 'rejected' && request.rejection_reason
            ? `${escapeHtml(formatStatus(request.status))}<br><small>Reason: ${escapeHtml(request.rejection_reason)}</small>`
            : escapeHtml(formatStatus(request.status));
          const receipt = request.status === 'refunded'
            ? `<details class="mt-2"><summary class="cursor-pointer font-semibold text-indigo-700">View refund receipt · PR-${escapeHtml(request.id)}</summary><div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm"><div><strong>Refund amount:</strong> ₱${Number(request.refund_amount || 0).toFixed(2)}</div><div><strong>Refund method:</strong> Cash</div><div><strong>Completed:</strong> ${escapeHtml(formatDate(request.fulfilled_at))}</div><div><strong>Processed by:</strong> ${escapeHtml(request.processor_name || 'Cashier')}</div></div></details>`
            : '';
          return `
            <tr>
              <td class="px-4 py-3">${escapeHtml(formatDate(request.created_at))}</td>
              <td class="px-4 py-3">${escapeHtml(request.transaction_number)}</td>
              <td class="px-4 py-3">${escapeHtml(request.product_name)} (${escapeHtml(request.quantity)} ${escapeHtml(request.unit_name || '')})</td>
              <td class="px-4 py-3">${escapeHtml(request.request_type)}</td>
              <td class="px-4 py-3">${status}${receipt}</td>
              <td class="px-4 py-3">${handoff}</td>
            </tr>
          `;
        }).join('');
      }
      document.getElementById('product-return-history-page').textContent = `${state.page} / ${state.totalPages}`;
      document.getElementById('product-return-history-prev').disabled = state.page <= 1;
      document.getElementById('product-return-history-next').disabled = state.page >= state.totalPages;
    } catch (error) {
      body.innerHTML = `<tr><td colspan="6" class="px-4 py-4 text-center text-rose-600">${escapeHtml(error.message)}</td></tr>`;
    }
  }

  function renderAdminReturns(requests) {
    const body = document.getElementById('admin-product-return-body');
    if (!body) return;
    if (!requests.length) {
      body.innerHTML = '<tr><td colspan="8" class="empty-state">No product return requests found.</td></tr>';
      return;
    }

    body.innerHTML = requests.map((request) => {
      const customer = [request.customer_name, request.student_id].filter(Boolean).join(' · ') || 'Customer';
      const photo = `../../actions/view_product_return_photo.php?id=${encodeURIComponent(request.id)}`;
      const handoff = request.handoff_received_at
        ? `Received<br><small>${escapeHtml(formatDate(request.handoff_received_at))}${request.handoff_receiver_name ? `<br>by ${escapeHtml(request.handoff_receiver_name)}` : ''}</small>`
        : 'Awaiting handoff';
      let actions = '';
      if (request.status === 'pending') {
        actions = `
          <button type="button" class="btn btn-primary btn-sm" data-return-action="approve" data-return-id="${request.id}">Approve</button>
          <button type="button" class="btn btn-secondary btn-sm" data-return-action="reject" data-return-id="${request.id}">Reject</button>`;
      } else if (request.status === 'refund_pending') {
        actions = request.handoff_received_at
          ? `<button type="button" class="btn btn-primary btn-sm" data-return-action="confirm_refund" data-return-id="${request.id}">Confirm refund paid</button>`
          : '<button type="button" class="btn btn-secondary btn-sm" disabled title="Scan the return QR to record item receipt first">Awaiting handoff</button>';
      } else if (request.status === 'replacement_pending') {
        actions = request.handoff_received_at
          ? `<button type="button" class="btn btn-primary btn-sm" data-return-action="fulfill_replacement" data-return-id="${request.id}">Fulfill replacement</button>`
          : '<button type="button" class="btn btn-secondary btn-sm" disabled title="Scan the return QR to record item receipt first">Awaiting handoff</button>';
      } else if (request.status === 'refunded') {
        actions = `<button type="button" class="btn btn-secondary btn-sm" data-return-action="resend_refund_receipt" data-return-id="${request.id}">Resend receipt</button>`;
      }
      const outcome = request.request_type === 'refund'
        ? `Refund · ₱${Number(request.refund_amount).toFixed(2)}`
        : 'Replacement';
      const status = request.status === 'rejected' && request.rejection_reason
        ? `${escapeHtml(formatStatus(request.status))}<br><small>Reason: ${escapeHtml(request.rejection_reason)}</small>`
        : escapeHtml(formatStatus(request.status));

      return `
        <tr data-product-return-id="${escapeHtml(request.id)}">
          <td>${escapeHtml(formatDate(request.created_at))}</td>
          <td>${escapeHtml(customer)}<br><small>${escapeHtml(request.transaction_number)}</small></td>
          <td>${escapeHtml(request.product_name)}<br><small>${escapeHtml(request.quantity)} ${escapeHtml(request.unit_name || '')}</small></td>
          <td>${escapeHtml(outcome)}</td>
          <td><span>${escapeHtml(request.damage_description)}</span><br><a href="${photo}" target="_blank" rel="noopener noreferrer">View photo</a></td>
          <td>${handoff}</td>
          <td>${status}</td>
          <td>${actions || '—'}</td>
        </tr>`;
    }).join('');
  }

  async function loadAdminReturns(page = 1, locateRequestId = null) {
    const body = document.getElementById('admin-product-return-body');
    if (!body) return false;
    body.innerHTML = '<tr><td colspan="8" class="empty-state">Loading return requests...</td></tr>';
    const status = document.getElementById('admin-product-return-status')?.value || 'all';
    try {
      const params = new URLSearchParams({ page: String(page), limit: '20', status });
      if (locateRequestId !== null) params.set('locate_id', String(locateRequestId));
      const result = await fetchJson(`../../actions/get_product_returns.php?${params.toString()}`);
      state.adminPage = result.current_page;
      state.adminTotalPages = result.total_pages;
      renderAdminReturns(result.requests);
      document.getElementById('admin-product-return-page').textContent = `${state.adminPage} / ${state.adminTotalPages}`;
      document.getElementById('admin-product-return-prev').disabled = state.adminPage <= 1;
      document.getElementById('admin-product-return-next').disabled = state.adminPage >= state.adminTotalPages;
      return true;
    } catch (error) {
      body.innerHTML = `<tr><td colspan="8" class="empty-state">${escapeHtml(error.message)}</td></tr>`;
      return false;
    }
  }

  function setAdminReturnNavigationStatus(message, isError = false) {
    const navigationStatus = document.getElementById('admin-product-return-navigation-status');
    if (!navigationStatus) return;
    navigationStatus.className = isError ? 'text-sm text-rose-600' : 'text-sm text-indigo-600';
    navigationStatus.textContent = message;
    navigationStatus.hidden = false;
  }

  async function focusAdminProductReturn(requestId) {
    const section = document.getElementById('admin-product-returns');
    const body = document.getElementById('admin-product-return-body');
    const navigationStatus = document.getElementById('admin-product-return-navigation-status');
    const requestIdNumber = Number(requestId);
    if (!section || !body || !Number.isSafeInteger(requestIdNumber) || requestIdNumber <= 0) return;

    const statusFilter = document.getElementById('admin-product-return-status');
    if (statusFilter) statusFilter.value = 'all';
    if (navigationStatus) {
      navigationStatus.hidden = true;
      navigationStatus.textContent = '';
    }

    try {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const loaded = await loadAdminReturns(1, requestIdNumber);
      if (!loaded) throw new Error('The return list could not be loaded.');
      const row = Array.from(body.querySelectorAll('tr[data-product-return-id]'))
        .find((item) => Number(item.dataset.productReturnId) === requestIdNumber);
      if (!row) throw new Error('The scanned return is no longer in the return list.');

      row.classList.add('return-scan-highlight');
      row.setAttribute('tabindex', '-1');
      row.focus({ preventScroll: true });
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setAdminReturnNavigationStatus('The scanned return is highlighted below.');
      window.setTimeout(() => row.classList.remove('return-scan-highlight'), 5000);
    } catch (error) {
      console.error('Unable to navigate to scanned product return:', error);
      setAdminReturnNavigationStatus(`Handoff recorded, but the return could not be located: ${error.message}`, true);
    }
  }

  function closeAdminReturnConfirmation(restoreFocus = true) {
    const modal = document.getElementById('product-return-action-modal');
    const trigger = state.pendingAdminAction?.trigger;
    modal?.classList.remove('active');
    modal?.setAttribute('aria-hidden', 'true');
    state.pendingAdminAction = null;
    if (restoreFocus && trigger?.isConnected) trigger.focus();
  }

  function openAdminReturnConfirmation(button) {
    const action = button.dataset.returnAction;
    const confirmations = {
      approve: 'Approve this damaged-item return?',
      reject: 'Reject this damaged-item return? Explain the reason below; it will be emailed to the user.',
      confirm_refund: 'Confirm that the refund has been paid to the customer?',
      fulfill_replacement: 'Hand over the replacement and deduct it from inventory?',
      resend_refund_receipt: 'Send the cash refund receipt to the customer again?'
    };
    const actionLabels = {
      approve: 'Approve return',
      reject: 'Reject return',
      confirm_refund: 'Confirm refund',
      fulfill_replacement: 'Fulfill replacement',
      resend_refund_receipt: 'Resend receipt'
    };
    if (!confirmations[action]) return;

    const modal = document.getElementById('product-return-action-modal');
    const message = document.getElementById('product-return-action-message');
    const error = document.getElementById('product-return-action-error');
    const confirmButton = document.getElementById('product-return-action-confirm');
    const cancelButton = document.getElementById('product-return-action-cancel');
    const rejectionField = document.getElementById('product-return-rejection-field');
    const rejectionReason = document.getElementById('product-return-rejection-reason');
    const resolutionField = document.getElementById('product-return-resolution-field');
    const resolutionSelect = document.getElementById('product-return-resolution');
    if (!modal || !message || !error || !confirmButton || !cancelButton) return;

    state.pendingAdminAction = {
      action,
      requestId: Number(button.dataset.returnId),
      trigger: button
    };
    const isRejecting = action === 'reject';
    const isApproving = action === 'approve';
    document.getElementById('product-return-action-title').textContent = isRejecting ? 'Reject return request' : 'Confirm return action';
    message.textContent = confirmations[action];
    if (resolutionField) resolutionField.hidden = !isApproving;
    if (resolutionSelect) {
      resolutionSelect.value = 'refund';
      resolutionSelect.disabled = false;
    }
    if (rejectionField) rejectionField.hidden = !isRejecting;
    if (rejectionReason) {
      rejectionReason.value = '';
      rejectionReason.required = isRejecting;
    }
    error.textContent = '';
    error.hidden = true;
    confirmButton.textContent = actionLabels[action];
    confirmButton.hidden = false;
    confirmButton.disabled = false;
    cancelButton.textContent = 'Cancel';
    cancelButton.disabled = false;
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    if (isRejecting && rejectionReason) rejectionReason.focus();
    else confirmButton.focus();
  }

  async function confirmAdminReturnAction() {
    const pending = state.pendingAdminAction;
    if (!pending) return;

    const confirmButton = document.getElementById('product-return-action-confirm');
    const cancelButton = document.getElementById('product-return-action-cancel');
    const error = document.getElementById('product-return-action-error');
    const rejectionReason = document.getElementById('product-return-rejection-reason');
    const resolutionSelect = document.getElementById('product-return-resolution');
    const rejectionReasonText = pending.action === 'reject' ? (rejectionReason?.value.trim() || '') : '';
    const resolution = pending.action === 'approve' ? (resolutionSelect?.value || 'refund') : '';
    if (pending.action === 'reject' && (!rejectionReasonText || rejectionReasonText.length > 1000)) {
      error.textContent = rejectionReasonText
        ? 'The rejection reason must be 1,000 characters or fewer.'
        : 'Enter a reason so the user understands why the return was rejected.';
      error.hidden = false;
      rejectionReason?.focus();
      return;
    }
    confirmButton.disabled = true;
    cancelButton.disabled = true;
    pending.trigger.disabled = true;
    try {
      const result = await fetchJson('../../actions/update_product_return.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrfToken
        },
        body: JSON.stringify({
          request_id: pending.requestId,
          action: pending.action,
          rejection_reason: rejectionReasonText,
          resolution
        })
      });
      closeAdminReturnConfirmation(false);
      await loadAdminReturns(state.adminPage);
      if (result.email_status === 'sent' || result.email_status === 'failed') {
        setAdminReturnNavigationStatus(result.message, result.email_status === 'failed');
      }
      document.getElementById('admin-product-return-status')?.focus();
    } catch (error) {
      pending.trigger.disabled = false;
      document.getElementById('product-return-action-title').textContent = 'Unable to update return';
      document.getElementById('product-return-action-error').textContent = error.message;
      document.getElementById('product-return-action-error').hidden = false;
      confirmButton.hidden = true;
      cancelButton.textContent = 'Close';
      cancelButton.disabled = false;
      cancelButton.focus();
    }
  }

  function handleAdminReturnAction(event) {
    const button = event.target.closest('[data-return-action]');
    if (button) openAdminReturnConfirmation(button);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const customerBody = document.getElementById('txn-history-body');
    if (customerBody) {
      customerBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-request-damaged-return]');
        if (button) openReturnModal(button.dataset.requestDamagedReturn);
      });
      document.getElementById('txn-mobile-list')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-request-damaged-return]');
        if (button) openReturnModal(button.dataset.requestDamagedReturn);
      });
      document.getElementById('product-return-form')?.addEventListener('submit', submitProductReturn);
      document.getElementById('product-return-close')?.addEventListener('click', closeReturnModal);
      document.getElementById('product-return-modal')?.addEventListener('click', (event) => {
        if (event.target.id === 'product-return-modal') closeReturnModal();
      });
      document.getElementById('product-return-item')?.addEventListener('change', (event) => {
        const option = event.target.selectedOptions[0];
        const quantity = document.getElementById('product-return-quantity');
        if (!quantity) return;
        quantity.max = option?.dataset.remaining || '';
        quantity.disabled = !option?.value;
      });
      document.getElementById('product-return-history-prev')?.addEventListener('click', () => loadCustomerReturns(state.page - 1));
      document.getElementById('product-return-history-next')?.addEventListener('click', () => loadCustomerReturns(state.page + 1));
      document.getElementById('product-return-history-search-btn')?.addEventListener('click', () => loadCustomerReturns(1));
      document.getElementById('product-return-history-status')?.addEventListener('change', () => loadCustomerReturns(1));
      document.getElementById('product-return-history-search')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          loadCustomerReturns(1);
        }
      });
      loadCustomerReturns();
    }

    const adminBody = document.getElementById('admin-product-return-body');
    if (adminBody) {
      window.scanProductReturnQr = async (token) => {
        if (!state.csrfToken) {
          await fetchJson('../../actions/get_product_returns.php?page=1&limit=1');
        }
        const result = await fetchJson('../../actions/scan_product_return.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken
          },
          body: JSON.stringify({ token })
        });
        return result;
      };
      window.focusAdminProductReturn = focusAdminProductReturn;
      window.showAdminProductReturnScanFeedback = (message) => {
        document.getElementById('admin-product-returns')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setAdminReturnNavigationStatus(message, true);
      };

      const modal = document.getElementById('product-return-action-modal');
      document.getElementById('product-return-action-confirm')?.addEventListener('click', confirmAdminReturnAction);
      document.getElementById('product-return-action-cancel')?.addEventListener('click', () => closeAdminReturnConfirmation());
      modal?.addEventListener('click', (event) => {
        if (event.target === modal && !document.getElementById('product-return-action-cancel')?.disabled) {
          closeAdminReturnConfirmation();
        }
      });
      document.addEventListener('keydown', (event) => {
        if (
          event.key === 'Escape' &&
          modal?.classList.contains('active') &&
          !document.getElementById('product-return-action-cancel')?.disabled
        ) {
          closeAdminReturnConfirmation();
        }
      });
      adminBody.addEventListener('click', handleAdminReturnAction);
      document.getElementById('admin-product-return-refresh')?.addEventListener('click', () => loadAdminReturns(1));
      document.getElementById('admin-product-return-status')?.addEventListener('change', () => loadAdminReturns(1));
      document.getElementById('admin-product-return-prev')?.addEventListener('click', () => loadAdminReturns(state.adminPage - 1));
      document.getElementById('admin-product-return-next')?.addEventListener('click', () => loadAdminReturns(state.adminPage + 1));
      loadAdminReturns();
    }
  });
})();
