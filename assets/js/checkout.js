document.addEventListener('DOMContentLoaded', () => {
  if (!window.ExtraStore) return;

  const store = window.ExtraStore;
  const itemsEl = document.getElementById('checkoutItems');
  const emptyEl = document.getElementById('checkoutEmpty');
  const subtotalEls = document.querySelectorAll('[data-checkout-subtotal]');
  const shippingEls = document.querySelectorAll('[data-checkout-shipping]');
  const totalEls = document.querySelectorAll('[data-checkout-total]');
  const form = document.getElementById('checkoutForm');
  const successPanel = document.getElementById('checkoutSuccess');
  const successClose = document.querySelector('[data-popup-close]');
  const orderButton = document.getElementById('placeOrderBtn');
  let successFocusCleanup = null;

  function render() {
    const items = store.getCartItems();
    const subtotal = store.getCartSubtotal();
    const shipping = items.length ? 6 : 0;
    const total = subtotal + shipping;

    store.updateCartBadges();

    subtotalEls.forEach((node) => {
      node.textContent = store.formatMoney(subtotal);
    });
    shippingEls.forEach((node) => {
      node.textContent = items.length ? store.formatMoney(shipping) : store.formatMoney(0);
    });
    totalEls.forEach((node) => {
      node.textContent = store.formatMoney(total);
    });

    if (!items.length) {
      if (itemsEl) itemsEl.innerHTML = '';
      if (emptyEl) emptyEl.classList.remove('hidden');
      if (form) form.classList.add('pointer-events-none', 'opacity-40');
      if (orderButton) orderButton.disabled = true;
      return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    if (form) form.classList.remove('pointer-events-none', 'opacity-40');
    if (orderButton) orderButton.disabled = false;

    if (itemsEl) {
      itemsEl.innerHTML = items
        .map((item) => {
          const product = item.product;
          return `
            <div class="scale-in flex items-center justify-between gap-4 rounded-[1rem] border border-line bg-[#fffdf8] p-3">
              <div class="flex items-center gap-3">
                <img src="${product.images[0]}" alt="${product.name}" class="h-16 w-16 rounded-[0.9rem] object-cover bg-[#f4efe8]">
                <div>
                  <div class="text-sm font-semibold text-ink">${product.name}</div>
                  <div class="text-xs text-muted">${item.qty} x ${store.formatMoney(product.price)}</div>
                </div>
              </div>
              <div class="text-sm font-semibold text-[#7a1f2b]">${store.formatMoney(item.lineTotal)}</div>
            </div>
          `;
        })
        .join('');
    }
  }

  function openSuccessPopup() {
    if (!successPanel) return;
    const returnFocusTarget = document.querySelector('a[href="cart.html"]') || orderButton;

    successPanel.classList.add('open');
    successPanel.setAttribute('aria-hidden', 'false');

    if (successFocusCleanup) {
      successFocusCleanup();
      successFocusCleanup = null;
    }

    if (window.ExtraA11y) {
      successFocusCleanup = window.ExtraA11y.trapFocus(successPanel, {
        initialFocus: successClose || successPanel,
        returnFocusTo: returnFocusTarget,
        onEscape: closeSuccessPopup
      });
    }
  }

  function closeSuccessPopup() {
    if (!successPanel) return;

    successPanel.classList.remove('open');
    successPanel.setAttribute('aria-hidden', 'true');

    if (successFocusCleanup) {
      successFocusCleanup();
      successFocusCleanup = null;
    }
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const items = store.getCartItems();
      if (!items.length) return;

      const orderNumber = Math.floor(100000 + Math.random() * 900000);
      store.clearCart();
      store.showToast(`Order ${orderNumber} placed`);
      render();
      openSuccessPopup();
    });
  }

  if (successPanel) {
    successPanel.addEventListener('click', (event) => {
      if (event.target === successPanel) {
        closeSuccessPopup();
      }
    });
  }

  if (successClose) {
    successClose.addEventListener('click', closeSuccessPopup);
  }

  window.addEventListener('extra-store:cart-changed', render);
  render();
});
