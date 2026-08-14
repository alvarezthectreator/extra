document.addEventListener('DOMContentLoaded', () => {
  if (!window.ExtraStore) return;

  const store = window.ExtraStore;
  const itemsEl = document.getElementById('cartPageItems');
  const emptyEl = document.getElementById('cartPageEmpty');
  const subtotalEl = document.querySelectorAll('[data-cart-summary-subtotal]');
  const shippingEl = document.querySelectorAll('[data-cart-summary-shipping]');
  const totalEl = document.querySelectorAll('[data-cart-summary-total]');
  const countEls = document.querySelectorAll('#cartPageCount, #cartPageSummary');
  const clearBtn = document.getElementById('clearCartBtn');

  function renderSummary() {
    const items = store.getCartItems();
    const subtotal = store.getCartSubtotal();
    const shipping = items.length ? 6.0 : 0;
    const total = subtotal + shipping;

    store.updateCartBadges();

    countEls.forEach((node) => {
      node.textContent = `${store.getCartCount()} item${store.getCartCount() === 1 ? '' : 's'}`;
    });

    subtotalEl.forEach((node) => {
      node.textContent = store.formatMoney(subtotal);
    });
    shippingEl.forEach((node) => {
      node.textContent = items.length ? store.formatMoney(shipping) : '$0.00';
    });
    totalEl.forEach((node) => {
      node.textContent = store.formatMoney(total);
    });

    if (!items.length) {
      if (itemsEl) itemsEl.innerHTML = '';
      if (emptyEl) emptyEl.classList.remove('hidden');
      if (clearBtn) clearBtn.classList.add('pointer-events-none', 'opacity-40');
      return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');
    if (clearBtn) clearBtn.classList.remove('pointer-events-none', 'opacity-40');

    if (itemsEl) {
      itemsEl.innerHTML = items
        .map((item) => {
          const product = item.product;
          return `
            <article class="catalog-card fade-in-up rounded-[1.4rem] border border-line bg-white p-4 shadow-sm sm:p-5">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <img src="${product.images[0]}" alt="${product.name}" class="h-24 w-24 flex-none rounded-[1rem] object-cover bg-[#f4efe8]">
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="text-[0.7rem] uppercase tracking-[0.12em] text-terracottaDark">${product.category}</div>
                      <h3 class="mt-1 text-lg font-semibold text-ink">${product.name}</h3>
                      <p class="mt-1 text-sm text-muted">${product.color} finish</p>
                    </div>
                    <div class="text-right">
                      <div class="text-sm font-semibold text-[#7a1f2b]">${store.formatMoney(product.price)}</div>
                      <button type="button" class="mt-2 text-xs font-semibold text-[#7a1f2b]" data-cart-action="remove" data-product-id="${product.id}">
                        Remove
                      </button>
                    </div>
                  </div>
                  <div class="mt-4 flex items-center justify-between gap-4">
                    <div class="text-sm text-muted">Item total: <span class="font-semibold text-ink">${store.formatMoney(item.lineTotal)}</span></div>
                    <div class="qty-box">
                      <button type="button" class="qty-btn" data-cart-action="decrease" data-product-id="${product.id}">−</button>
                      <span class="qty-value">${item.qty}</span>
                      <button type="button" class="qty-btn" data-cart-action="increase" data-product-id="${product.id}">+</button>
                    </div>
                  </div>
                </div>
              </div>
            </article>
          `;
        })
        .join('');
    }
  }

  if (itemsEl) {
    itemsEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-cart-action]');
      if (!button) return;

      const productId = button.dataset.productId;
      const action = button.dataset.cartAction;
      const entry = store.getCart().find((item) => item.id === productId);
      if (!entry) return;

      if (action === 'increase') {
        store.setItemQty(productId, entry.qty + 1);
      } else if (action === 'decrease') {
        store.setItemQty(productId, entry.qty - 1);
      } else if (action === 'remove') {
        store.removeItem(productId);
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      store.clearCart();
      store.showToast('Cart cleared');
    });
  }

  window.addEventListener('extra-store:cart-changed', renderSummary);
  renderSummary();
});
