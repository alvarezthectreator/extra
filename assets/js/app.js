document.addEventListener('DOMContentLoaded', () => {
  if (!window.ExtraStore) return;

  const store = window.ExtraStore;

  const existingModal = document.getElementById('productModal');
  if (existingModal) {
    const freshModal = existingModal.cloneNode(true);
    existingModal.replaceWith(freshModal);
  }

  document.querySelectorAll('.product-detail-trigger').forEach((trigger) => {
    const freshTrigger = trigger.cloneNode(true);
    trigger.replaceWith(freshTrigger);
  });

  const modal = document.getElementById('productModal');
  const detailMainImage = document.getElementById('detailMainImage');
  const detailGallery = document.getElementById('detailGallery');
  const detailTitle = document.getElementById('detailTitle');
  const detailPrice = document.getElementById('detailPrice');
  const detailDesc = document.getElementById('detailDesc');
  const detailQty = document.getElementById('detailQty');
  const detailAdd = document.getElementById('detailAdd');
  const detailRemove = document.getElementById('detailRemove');
  const detailCartLinks = document.getElementById('detailCartLinks');
  const qtyInc = document.getElementById('qtyInc');
  const qtyDec = document.getElementById('qtyDec');
  const detailClose = document.getElementById('detailClose');
  const detailModalPanel = modal?.querySelector('.detail-modal-panel') || modal;
  const newsletterForm = document.getElementById('newsletterForm');
  const newsletterEmail = document.getElementById('newsletterEmail');

  const cartDrawer = document.getElementById('cartDrawer');
  const cartOverlay = document.querySelector('[data-cart-overlay]');
  const cartCloseButtons = document.querySelectorAll('[data-cart-close]');
  const cartToggleButtons = document.querySelectorAll('[data-cart-toggle]');
  const cartDrawerItems = document.getElementById('cartDrawerItems');
  const cartDrawerSubtotal = document.getElementById('cartDrawerSubtotal');
  const cartDrawerCount = document.getElementById('cartDrawerCount');
  const cartDrawerEmpty = document.getElementById('cartDrawerEmpty');
  const cartDrawerHeading = document.getElementById('cartDrawerHeading');
  const productSearch = document.getElementById('productSearch');
  const productColorFilter = document.getElementById('productColorFilter');
  const productSort = document.getElementById('productSort');
  const productGrid = document.getElementById('productGrid');
  const productResultsCount = document.getElementById('productResultsCount');
  const productResultsEmpty = document.getElementById('productResultsEmpty');
  const clearProductFilters = document.getElementById('clearProductFilters');
  const productCards = Array.from(document.querySelectorAll('[data-product-card]'));
  const cartDrawerPanel = cartDrawer?.querySelector('.cart-drawer-panel') || cartDrawer;

  let activeProduct = null;
  let activeQty = 1;
  let modalTrigger = null;
  let modalFocusCleanup = null;
  let cartTrigger = null;
  let cartFocusCleanup = null;

  function findCartEntry(id) {
    return store.getCart().find((item) => item.id === id) || null;
  }

  function applyCatalogFilters() {
    if (!productGrid || !productCards.length) return;

    const query = (productSearch?.value || '').trim().toLowerCase();
    const selectedColor = (productColorFilter?.value || 'all').toLowerCase();
    const sortMode = productSort?.value || 'featured';

    const matches = productCards.filter((card) => {
      const haystack = [
        card.dataset.productName,
        card.dataset.productCategory,
        card.dataset.productColor,
        card.dataset.productTags
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      const matchesQuery = !query || haystack.includes(query);
      const matchesColor = selectedColor === 'all' || (card.dataset.productColor || '').toLowerCase() === selectedColor;
      return matchesQuery && matchesColor;
    });

    const sorted = [...matches].sort((left, right) => {
      if (sortMode === 'price-low') return Number(left.dataset.productPrice || 0) - Number(right.dataset.productPrice || 0);
      if (sortMode === 'price-high') return Number(right.dataset.productPrice || 0) - Number(left.dataset.productPrice || 0);
      if (sortMode === 'name') return (left.dataset.productName || '').localeCompare(right.dataset.productName || '');
      return Number(left.dataset.sortOrder || 0) - Number(right.dataset.sortOrder || 0);
    });

    const visibleCards = new Set(sorted);
    productCards.forEach((card) => {
      card.classList.toggle('is-hidden', !visibleCards.has(card));
    });

    sorted.forEach((card) => {
      productGrid.appendChild(card);
    });

    if (productResultsCount) {
      productResultsCount.textContent = `${sorted.length} product${sorted.length === 1 ? '' : 's'} shown`;
    }

    if (productResultsEmpty) {
      productResultsEmpty.classList.toggle('hidden', sorted.length !== 0);
    }
  }

  function syncCardSlider(root, nextIndex) {
    if (!root) return;

    const track = root.querySelector('[data-card-slider-track]');
    const slides = Array.from(root.querySelectorAll('[data-card-slider-slide]'));
    const dots = Array.from(root.querySelectorAll('[data-card-slider-dot]'));

    if (!track || !slides.length) return;

    const total = slides.length;
    const activeIndex = ((Number(nextIndex) || 0) % total + total) % total;

    root.dataset.activeSlide = String(activeIndex);
    track.style.transform = `translateX(-${activeIndex * 100}%)`;

    dots.forEach((dot, index) => {
      const isActive = index === activeIndex;
      dot.classList.toggle('active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
      dot.setAttribute('aria-current', isActive ? 'true' : 'false');
    });
  }

  function initCardSliders() {
    document.querySelectorAll('[data-card-slider]').forEach((root) => {
      syncCardSlider(root, Number(root.dataset.activeSlide || 0));
    });
  }

  function initReadMoreBlocks() {
    document.querySelectorAll('[data-read-more-toggle]').forEach((button) => {
      const section = button.closest('div');
      const list = section?.querySelector('[data-read-more-list]');
      const items = list ? Array.from(list.querySelectorAll('[data-read-more-item]')) : [];
      const label = button.querySelector('[data-read-more-label]');
      let expanded = false;

      if (!items.length) return;

      function sync() {
        items.forEach((item) => {
          item.classList.toggle('hidden', !expanded);
        });
        if (label) {
          label.textContent = expanded ? 'Read less' : 'Read more';
        }
        button.lastElementChild.textContent = expanded ? '−' : '+';
      }

      button.addEventListener('click', () => {
        expanded = !expanded;
        sync();
      });

      sync();
    });
  }

  function openModal(productId, trigger = null) {
    const product = store.getProduct(productId);
    if (!product || !modal) return;

    activeProduct = product;
    modalTrigger = trigger;
    const existingEntry = findCartEntry(product.id);
    activeQty = existingEntry ? existingEntry.qty : 1;
    const hasItemsInCart = store.getCartCount() > 0;

    detailTitle.textContent = product.name;
    detailPrice.textContent = store.formatMoney(product.price);
    detailDesc.textContent = product.description;
    detailQty.textContent = String(activeQty);
    detailAdd.textContent = existingEntry ? 'In Cart' : 'Add to Cart';
    detailRemove.classList.toggle('hidden', !existingEntry);
    if (detailCartLinks) {
      detailCartLinks.classList.toggle('hidden', !hasItemsInCart);
    }

    if (detailGallery) {
      detailGallery.innerHTML = '';
      product.images.forEach((src, index) => {
        const thumb = document.createElement('button');
        thumb.type = 'button';
        thumb.className = `detail-thumb${index === 0 ? ' active' : ''}`;
        thumb.innerHTML = `<img src="${src}" alt="${product.name} thumbnail ${index + 1}">`;
        thumb.addEventListener('click', () => {
          detailGallery.querySelectorAll('.detail-thumb').forEach((item) => item.classList.remove('active'));
          thumb.classList.add('active');
          detailMainImage.src = src;
        });
        detailGallery.appendChild(thumb);
      });
    }

    detailMainImage.src = product.images[0];
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');

    if (modalFocusCleanup) {
      modalFocusCleanup();
      modalFocusCleanup = null;
    }

    if (window.ExtraA11y) {
      modalFocusCleanup = window.ExtraA11y.trapFocus(modal, {
        initialFocus: detailClose || detailModalPanel,
        returnFocusTo: modalTrigger,
        onEscape: closeModal
      });
    }
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
    if (modalFocusCleanup) {
      modalFocusCleanup();
      modalFocusCleanup = null;
    }
    activeProduct = null;
    modalTrigger = null;
  }

  function openDrawer(trigger = null) {
    if (!cartDrawer) return;
    cartTrigger = trigger;
    renderDrawer();
    cartDrawer.classList.remove('hidden');
    cartDrawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');

    if (cartFocusCleanup) {
      cartFocusCleanup();
      cartFocusCleanup = null;
    }

    if (window.ExtraA11y) {
      cartFocusCleanup = window.ExtraA11y.trapFocus(cartDrawer, {
        initialFocus: cartDrawerPanel || cartDrawer,
        returnFocusTo: cartTrigger,
        onEscape: closeDrawer
      });
    }
  }

  function closeDrawer() {
    if (!cartDrawer) return;
    cartDrawer.classList.add('hidden');
    cartDrawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
    if (cartFocusCleanup) {
      cartFocusCleanup();
      cartFocusCleanup = null;
    }
    cartTrigger = null;
  }

  function renderDrawer() {
    if (!cartDrawerItems) return;

    const items = store.getCartItems();
    const subtotal = store.getCartSubtotal();

    if (cartDrawerHeading) {
      cartDrawerHeading.textContent = `${store.getCartCount()} item${store.getCartCount() === 1 ? '' : 's'} in cart`;
    }

    if (cartDrawerCount) {
      cartDrawerCount.textContent = String(store.getCartCount());
      cartDrawerCount.classList.toggle('hidden', store.getCartCount() === 0);
    }

    if (!items.length) {
      cartDrawerItems.innerHTML = `
        <div id="cartDrawerEmpty" class="rounded-[1.4rem] border border-dashed border-[#e2c8c0] bg-[#fff8f7] p-6 text-center">
          <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#7a1f2b] text-lg text-white">🛒</div>
          <h4 class="font-serif text-xl text-ink">Your cart is empty</h4>
          <p class="mt-2 text-sm leading-6 text-muted">Add a few essentials and they will show up here right away.</p>
          <a href="index.html#featured-products" class="mt-4 inline-flex items-center justify-center rounded-full bg-[#7a1f2b] px-5 py-2.5 text-sm font-semibold text-white">
            Start Shopping
          </a>
        </div>
      `;
    } else {
      cartDrawerItems.innerHTML = items
        .map((item) => {
          const product = item.product;
          return `
            <div class="rounded-[1.4rem] border border-line bg-white p-4 shadow-sm">
              <div class="flex gap-4">
                <img src="${product.images[0]}" alt="${product.name}" class="h-20 w-20 flex-none rounded-[1rem] object-cover bg-[#f4efe8]">
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <h4 class="text-sm font-semibold text-ink">${product.name}</h4>
                      <p class="mt-1 text-xs uppercase tracking-[0.12em] text-terracottaDark">${product.category}</p>
                    </div>
                    <button type="button" class="text-xs font-semibold text-[#7a1f2b]" data-cart-action="remove" data-product-id="${product.id}">
                      Remove
                    </button>
                  </div>
                  <div class="mt-3 flex items-center justify-between gap-3">
                    <div>
                      <div class="text-sm font-semibold text-[#7a1f2b]">${store.formatMoney(product.price)}</div>
                      <div class="text-xs text-muted">Color: ${product.color}</div>
                    </div>
                    <div class="qty-box">
                      <button type="button" class="qty-btn" data-cart-action="decrease" data-product-id="${product.id}">−</button>
                      <span class="qty-value">${item.qty}</span>
                      <button type="button" class="qty-btn" data-cart-action="increase" data-product-id="${product.id}">+</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        })
        .join('');
    }

    if (cartDrawerSubtotal) {
      cartDrawerSubtotal.textContent = store.formatMoney(subtotal);
    }
  }

  function syncModalState() {
    if (!activeProduct) return;
    const entry = findCartEntry(activeProduct.id);
    const hasItemsInCart = store.getCartCount() > 0;
    if (entry) {
      detailAdd.textContent = 'In Cart';
      detailRemove.classList.remove('hidden');
      detailQty.textContent = String(entry.qty);
      activeQty = entry.qty;
    } else {
      detailAdd.textContent = 'Add to Cart';
      detailRemove.classList.add('hidden');
      detailQty.textContent = String(activeQty);
    }

    if (detailCartLinks) {
      detailCartLinks.classList.toggle('hidden', !hasItemsInCart);
    }
  }

  document.querySelectorAll('.product-detail-trigger').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const productId = trigger.dataset.product || trigger.dataset.productId || '';
      openModal(productId, trigger);
    });
  });

  document.querySelectorAll('[data-add-to-cart]').forEach((button) => {
    button.addEventListener('click', () => {
      const productId = button.dataset.productId;
      if (!productId) return;

      store.addItem(productId, 1);
      store.showToast(`${store.getCartCount()} item${store.getCartCount() === 1 ? '' : 's'} in cart`);
      renderDrawer();
      syncModalState();
    });
  });

  document.querySelectorAll('[data-buy-now]').forEach((button) => {
    button.addEventListener('click', () => {
      const productId = button.dataset.productId;
      if (productId) {
        store.addItem(productId, 1);
      }
      window.location.href = 'checkout.html';
    });
  });

  if (productSearch) {
    productSearch.addEventListener('input', applyCatalogFilters);
  }

  if (productColorFilter) {
    productColorFilter.addEventListener('change', applyCatalogFilters);
  }

  if (productSort) {
    productSort.addEventListener('change', applyCatalogFilters);
  }

  if (clearProductFilters) {
    clearProductFilters.addEventListener('click', () => {
      if (productSearch) productSearch.value = '';
      if (productColorFilter) productColorFilter.value = 'all';
      if (productSort) productSort.value = 'featured';
      applyCatalogFilters();
      if (productSearch) productSearch.focus();
    });
  }

  if (detailClose) {
    detailClose.addEventListener('click', closeModal);
  }

  if (modal) {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });
  }

  if (qtyInc) {
    qtyInc.addEventListener('click', () => {
      activeQty = Math.min(99, activeQty + 1);
      detailQty.textContent = String(activeQty);
    });
  }

  if (qtyDec) {
    qtyDec.addEventListener('click', () => {
      activeQty = Math.max(1, activeQty - 1);
      detailQty.textContent = String(activeQty);
    });
  }

  if (detailAdd) {
    detailAdd.addEventListener('click', () => {
      if (!activeProduct) return;

      store.addItem(activeProduct.id, activeQty);
      store.showToast(`${store.getCartCount()} item${store.getCartCount() === 1 ? '' : 's'} in cart`);
      renderDrawer();
      syncModalState();
    });
  }

  if (detailRemove) {
    detailRemove.addEventListener('click', () => {
      if (!activeProduct) return;

      store.removeItem(activeProduct.id);
      activeQty = 1;
      renderDrawer();
      syncModalState();
    });
  }

  document.addEventListener('click', (event) => {
    const control = event.target.closest('[data-card-slider-prev], [data-card-slider-next], [data-card-slider-dot]');
    if (!control) return;

    const root = control.closest('[data-card-slider]');
    if (!root) return;

    event.preventDefault();

    const currentIndex = Number(root.dataset.activeSlide || 0);
    if (control.hasAttribute('data-card-slider-prev')) {
      syncCardSlider(root, currentIndex - 1);
    } else if (control.hasAttribute('data-card-slider-next')) {
      syncCardSlider(root, currentIndex + 1);
    } else if (control.hasAttribute('data-card-slider-dot')) {
      syncCardSlider(root, Number(control.dataset.slideTo || 0));
    }
  });

  if (cartToggleButtons.length) {
    cartToggleButtons.forEach((button) => {
      button.addEventListener('click', () => openDrawer(button));
    });
  }

  if (cartCloseButtons.length) {
    cartCloseButtons.forEach((button) => {
      button.addEventListener('click', closeDrawer);
    });
  }

  if (cartOverlay) {
    cartOverlay.addEventListener('click', closeDrawer);
  }

  if (cartDrawer) {
    cartDrawer.addEventListener('click', (event) => {
      const actionButton = event.target.closest('[data-cart-action]');
      if (!actionButton) return;

      const productId = actionButton.dataset.productId;
      const action = actionButton.dataset.cartAction;
      const cartEntry = store.getCart().find((item) => item.id === productId);
      if (!cartEntry) return;

      if (action === 'increase') {
        store.setItemQty(productId, cartEntry.qty + 1);
      } else if (action === 'decrease') {
        store.setItemQty(productId, cartEntry.qty - 1);
      } else if (action === 'remove') {
        store.removeItem(productId);
      }

      renderDrawer();
      syncModalState();
    });
  }

  if (newsletterForm) {
    newsletterForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const email = (newsletterEmail?.value || '').trim();
      if (!email) {
        newsletterEmail?.focus();
        return;
      }

      store.showToast('Thanks for joining the Extra Store list');
      newsletterForm.reset();
    });
  }

  window.addEventListener('extra-store:cart-changed', () => {
    renderDrawer();
    syncModalState();
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal();
      closeDrawer();
    }
  });

  store.updateCartBadges();
  initCardSliders();
  initReadMoreBlocks();
  renderDrawer();
  syncModalState();
  applyCatalogFilters();
  // Hero entrance animations — run after other app initialization
  (function(){
    try {
      var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (prefersReduced) return;

      var left = document.querySelector('[data-hero-line="left"]');
      var right = document.querySelector('[data-hero-line="right"]');
      var support = document.querySelector('[data-hero-support]');
      if (left || right) {
        if (left) left.classList.add('animate-slide-in-left');
        if (right) setTimeout(function(){ right.classList.add('animate-slide-in-right'); }, 120);
        if (support) setTimeout(function(){ support.classList.add('animate-fade-up'); }, 420);
      } else {
        var h1 = document.querySelector('main h1');
        var p = h1 ? h1.nextElementSibling : null;
        if (h1) h1.classList.add('animate-slide-in-right');
        if (p) setTimeout(function(){ p.classList.add('animate-slide-in-left'); }, 120);
        if (p && p.nextElementSibling) setTimeout(function(){ p.nextElementSibling.classList.add('animate-fade-up'); }, 420);
      }
    } catch (e) {
      /* ignore */
    }
  })();
  // Animate hero image and CTAs with a float-in
  (function(){
    try {
      var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (prefersReduced) return;
      var img = document.querySelector('[data-hero-image]') || document.querySelector('main img[alt="Featured product"]') || document.querySelector('.relative.z-10 img');
      var buttons = Array.from(document.querySelectorAll('[data-hero-button]'));
      if (!buttons.length) {
        var b1 = document.querySelector('a[href="#featured-products"]');
        var b2 = document.querySelector('a[href="#new-arrivals"]');
        [b1, b2].forEach(function(b){ if (b) buttons.push(b); });
      }
      // final fallback: any prominent hero buttons inside the hero area
      if (!buttons.length) {
        var hero = document.querySelector('main') || document.body;
        buttons = Array.from(hero.querySelectorAll('a')).filter(a => a.classList.contains('inline-flex') || a.classList.contains('btn') || /explore|browse|shop/i.test(a.textContent));
      }
      if (img) {
        // apply a much slower float-in for the main hero image
        setTimeout(function(){ img.classList.add('animate-float-in-slow'); }, 700);
      }
      buttons.forEach(function(btn, i){
        setTimeout(function(){ btn.classList.add('animate-float-in'); }, 900 + (i * 160));
      });
    } catch (e) { /* ignore */ }
  })();
  // Find and animate the specific paragraph text (bottom-up)
  (function(){
    try {
      var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (prefersReduced) return;
      var targets = Array.from(document.querySelectorAll('p'));
      var found = targets.find(function(el){
        var txt = (el.textContent || '').replace(/\s+/g,' ').trim();
        return txt.indexOf('Convenience, comfort') !== -1 || txt.indexOf('Extra Store keeps everyday') !== -1;
      });
      if (found) {
        // small delay so it appears after hero
        setTimeout(function(){ found.classList.add('animate-slide-in-up'); }, 600);
      }
    } catch (e) { /* ignore */ }
  })();
  // Initialize product carousel (minimal, unobtrusive)
  (function(){
    try {
      var carousel = document.getElementById('productCarousel');
      if (!carousel) return;
      var track = carousel.querySelector('.carousel-track');
      var prev = carousel.querySelector('.carousel-nav.prev');
      var next = carousel.querySelector('.carousel-nav.next');
      var dots = carousel.querySelector('.carousel-dots');
      var cards = Array.from(track.children || []);
      // create dots
      cards.forEach(function(_, i){
        var btn = document.createElement('button');
        if (i===0) btn.classList.add('active');
        btn.addEventListener('click', function(){
          cards[i].scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'});
          setActiveDot(i);
        });
        dots.appendChild(btn);
      });
      function setActiveDot(i){
        Array.from(dots.children).forEach(function(b,idx){ b.classList.toggle('active', idx===i); });
      }
      prev.addEventListener('click', function(){
        var idx = Math.max(0, visibleIndex()-1);
        cards[idx].scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'});
        setActiveDot(idx);
      });
      next.addEventListener('click', function(){
        var idx = Math.min(cards.length-1, visibleIndex()+1);
        cards[idx].scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'});
        setActiveDot(idx);
      });
      function visibleIndex(){
        var center = track.scrollLeft + (track.clientWidth/2);
        var idx = cards.findIndex(function(card){
          var rect = card.getBoundingClientRect();
          var cardLeft = card.offsetLeft;
          var cardRight = cardLeft + card.offsetWidth;
          return cardLeft <= center && cardRight >= center;
        });
        return idx === -1 ? 0 : idx;
      }
      // update dots on scroll
      var onScroll = function(){ setActiveDot(visibleIndex()); };
      track.addEventListener('scroll', debounce(onScroll, 100));
      function debounce(fn, wait){ var t; return function(){ clearTimeout(t); t=setTimeout(fn, wait); }; }
    } catch(e){ /* ignore */ }
  })();
});
