document.addEventListener('DOMContentLoaded', () => {
  if (!window.ExtraStore) return;

  const store = window.ExtraStore;
  const purchaseDraftKey = 'extra-store-purchase-draft';
  const products = typeof store.listProducts === 'function' ? store.listProducts() : Object.values(store.PRODUCTS || {});

  const form = document.getElementById('checkoutForm');
  const productSelect = document.getElementById('purchaseProduct');
  const qtyInput = document.getElementById('purchaseQty');
  const qtyInc = document.getElementById('qtyInc');
  const qtyDec = document.getElementById('qtyDec');
  const selectedName = document.getElementById('purchaseSelectedName');
  const selectedPrice = document.getElementById('purchaseSelectedPrice');
  const summaryImage = document.getElementById('purchaseSummaryImage');
  const summaryName = document.getElementById('purchaseSummaryName');
  const summaryCategory = document.getElementById('purchaseSummaryCategory');
  const summaryPrice = document.getElementById('purchaseSummaryPrice');
  const summaryQty = document.getElementById('purchaseSummaryQty');
  const summaryTotal = document.getElementById('purchaseSummaryTotal');
  const receiptInput = document.getElementById('paymentReceipt');
  const submitButton = document.getElementById('placeOrderBtn');
  const successPanel = document.getElementById('checkoutSuccess');
  const successClose = document.querySelector('[data-popup-close]');
  const successOrderNumber = document.querySelector('[data-order-number]');
  const successCopy = document.getElementById('checkoutSuccessCopy');

  const defaultSubmitLabel = submitButton?.textContent?.trim() || 'Send Order';

  let activeFocus = null;
  let isSubmitting = false;

  function clampQty(value) {
    const qty = Number(value) || 1;
    return Math.max(1, Math.min(99, qty));
  }

  function readDraft() {
    const params = new URLSearchParams(window.location.search);
    const fallback = products[0] || null;
    let draft = null;

    try {
      draft = JSON.parse(sessionStorage.getItem(purchaseDraftKey) || 'null');
    } catch (error) {
      draft = null;
    }

    const productId = params.get('product') || draft?.productId || fallback?.id || '';
    const qty = clampQty(params.get('qty') || draft?.qty || 1);
    return { productId, qty };
  }

  function getSelectedProduct() {
    const productId = productSelect?.value || '';
    return store.getProduct(productId) || products[0] || null;
  }

  function getFieldValue(selector) {
    return form?.querySelector(selector)?.value?.trim() || '';
  }

  function syncSummary() {
    const product = getSelectedProduct();
    if (!product) return;

    const qty = clampQty(qtyInput?.value || 1);
    const total = product.price * qty;

    if (selectedName) selectedName.textContent = product.name;
    if (selectedPrice) selectedPrice.textContent = store.formatMoney(product.price);
    if (summaryImage) {
      summaryImage.src = product.images[0];
      summaryImage.alt = product.name;
    }
    if (summaryName) summaryName.textContent = product.name;
    if (summaryCategory) summaryCategory.textContent = product.category;
    if (summaryPrice) summaryPrice.textContent = store.formatMoney(product.price);
    if (summaryQty) summaryQty.textContent = String(qty);
    if (summaryTotal) summaryTotal.textContent = store.formatMoney(total);
  }

  function saveDraft() {
    try {
      sessionStorage.setItem(
        purchaseDraftKey,
        JSON.stringify({
          productId: productSelect?.value || '',
          qty: clampQty(qtyInput?.value || 1)
        })
      );
    } catch (error) {
      /* ignore storage write failures */
    }
  }

  function buildProductOptions() {
    if (!productSelect) return;

    productSelect.innerHTML = products
      .map((product) => `<option value="${product.id}">${product.name} - ${store.formatMoney(product.price)}</option>`)
      .join('');
  }

  function applyDraft() {
    const draft = readDraft();
    if (productSelect && draft.productId) {
      productSelect.value = draft.productId;
    }
    if (qtyInput) {
      qtyInput.value = String(draft.qty);
    }
    syncSummary();
  }

  function setSubmitting(submitting) {
    isSubmitting = submitting;
    if (submitButton) {
      submitButton.disabled = submitting;
      submitButton.textContent = submitting ? 'Sending...' : defaultSubmitLabel;
    }
  }

  function openSuccessPanel(orderNumber) {
    if (!successPanel) return;

    activeFocus = document.activeElement;
    if (successOrderNumber) {
      successOrderNumber.textContent = `Order #${orderNumber}`;
    }
    if (successCopy) {
      successCopy.textContent = 'Your order email has been sent successfully. Our team will review your receipt and confirm delivery soon.';
    }

    successPanel.classList.add('open');
    successPanel.setAttribute('aria-hidden', 'false');

    if (successClose) {
      successClose.focus();
    }
  }

  function closeSuccessPanel() {
    if (!successPanel) return;

    successPanel.classList.remove('open');
    successPanel.setAttribute('aria-hidden', 'true');

    if (activeFocus && typeof activeFocus.focus === 'function') {
      activeFocus.focus();
    }
    activeFocus = null;
  }

  buildProductOptions();
  applyDraft();
  store.updateCartBadges();

  if (productSelect) {
    productSelect.addEventListener('change', () => {
      syncSummary();
      saveDraft();
    });
  }

  if (qtyInput) {
    qtyInput.addEventListener('input', () => {
      qtyInput.value = String(clampQty(qtyInput.value));
      syncSummary();
      saveDraft();
    });
  }

  if (qtyInc && qtyInput) {
    qtyInc.addEventListener('click', () => {
      qtyInput.value = String(Math.min(99, clampQty(qtyInput.value) + 1));
      syncSummary();
      saveDraft();
    });
  }

  if (qtyDec && qtyInput) {
    qtyDec.addEventListener('click', () => {
      qtyInput.value = String(Math.max(1, clampQty(qtyInput.value) - 1));
      syncSummary();
      saveDraft();
    });
  }

  if (successClose) {
    successClose.addEventListener('click', closeSuccessPanel);
  }

  if (successPanel) {
    successPanel.addEventListener('click', (event) => {
      if (event.target === successPanel) {
        closeSuccessPanel();
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && successPanel?.classList.contains('open')) {
      closeSuccessPanel();
    }
  });

  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (isSubmitting) return;

      const product = getSelectedProduct();
      if (!product) {
        store.showToast('Please choose a product first.');
        return;
      }

      if (!receiptInput || receiptInput.files.length === 0) {
        receiptInput?.focus();
        store.showToast('Please upload your payment receipt.');
        return;
      }

      const qty = clampQty(qtyInput?.value || 1);
      const orderNumber = Math.floor(100000 + Math.random() * 900000);
      const formData = new FormData(form);
      const endpoint = form.getAttribute('action') || 'send-order.php';

      formData.set('product', product.id);
      formData.set('qty', String(qty));
      formData.append('order_number', String(orderNumber));
      formData.append('product_name', product.name);
      formData.append('product_price', String(product.price));
      formData.append('product_total', String(product.price * qty));
      formData.append('product_category', product.category);
      formData.append('customer_name', [getFieldValue('input[autocomplete="given-name"]'), getFieldValue('input[autocomplete="family-name"]')].filter(Boolean).join(' '));

      setSubmitting(true);

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          }
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
          throw new Error(payload?.message || 'Could not send your order email right now.');
        }

        try {
          sessionStorage.removeItem(purchaseDraftKey);
        } catch (error) {
          /* ignore storage cleanup failures */
        }

        form.reset();
        applyDraft();
        store.showToast(`Order email sent for ${payload.orderNumber || `#${orderNumber}`}`);
        openSuccessPanel(payload.orderNumber || orderNumber);
      } catch (error) {
        store.showToast(error?.message || 'Unable to send your order email.');
      } finally {
        setSubmitting(false);
      }
    });
  }

  window.addEventListener('extra-store:cart-changed', () => {
    store.updateCartBadges();
  });
});
