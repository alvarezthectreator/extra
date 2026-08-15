(function () {
  const STORAGE_KEY = 'extra-store-cart';

  const DEFAULT_PRODUCTS = {
    'umbrella-red': {
      id: 'umbrella-red',
      name: 'Classic Red Auto Umbrella',
      price: 24000,
      color: 'Red',
      category: 'Travel Essential',
      images: ['assets/red-product-clean.png', 'assets/pink.jpg'],
      description: 'A compact automatic umbrella with windproof protection and a polished red finish for everyday carry.'
    },
    'umbrella-green': {
      id: 'umbrella-green',
      name: 'Green Windproof Umbrella',
      price: 24000,
      color: 'Green',
      category: 'Travel Essential',
      images: ['assets/green.jpg', 'assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg'],
      description: 'A lightweight umbrella built for rain-ready protection and easy storage.'
    },
    'umbrella-blue': {
      id: 'umbrella-blue',
      name: 'Blue Compact Auto Umbrella',
      price: 24000,
      color: 'Blue',
      category: 'Travel Essential',
      images: ['assets/blue.jpg', 'assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg', 'assets/tfgh.png'],
      description: 'A stylish compact umbrella with automatic open and close convenience.'
    },
    'umbrella-pink': {
      id: 'umbrella-pink',
      name: 'Pink Compact Umbrella',
      price: 24000,
      color: 'Pink',
      category: 'Travel Essential',
      images: ['assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg'],
      description: 'A bright, practical umbrella with a soft finish and travel-friendly format.'
    }
  };

  function safeParse(value, fallback) {
    try {
      return JSON.parse(value ?? '');
    } catch (error) {
      return fallback;
    }
  }

  function normalizeImages(images, primaryImage = '') {
    const list = Array.isArray(images) ? images : [];
    const normalized = [];

    if (primaryImage) {
      normalized.push(String(primaryImage).trim());
    }

    list.forEach((image) => {
      const value = String(image ?? '').trim();
      if (value) {
        normalized.push(value);
      }
    });

    return Array.from(new Set(normalized.filter(Boolean)));
  }

  function normalizeProduct(product) {
    if (!product) return null;

    const id = String(product.id ?? '').trim();
    if (!id) return null;

    const imagePrimary = String(product.image_primary ?? product.imagePrimary ?? '').trim();
    const images = normalizeImages(product.images, imagePrimary);
    const fallbackImage = images[0] || imagePrimary || '';

    return {
      id,
      name: String(product.name ?? '').trim(),
      price: Number(product.price) || 0,
      color: String(product.color ?? '').trim(),
      category: String(product.category ?? '').trim(),
      image_primary: imagePrimary || fallbackImage,
      images: images.length ? images : (fallbackImage ? [fallbackImage] : []),
      description: String(product.description ?? '').trim()
    };
  }

  function normalizeProductMap(source) {
    const entries = Array.isArray(source) ? source.map((product) => [product?.id, product]) : Object.entries(source || {});

    return entries.reduce((accumulator, [id, product]) => {
      const normalized = normalizeProduct(product);
      if (!normalized || !id) {
        return accumulator;
      }

      accumulator[id] = normalized;
      return accumulator;
    }, {});
  }

  let PRODUCTS = normalizeProductMap(DEFAULT_PRODUCTS);
  let resolveReady;
  const ready = new Promise((resolve) => {
    resolveReady = resolve;
  });
  let readyResolved = false;

  function finishReady() {
    if (readyResolved) return;
    readyResolved = true;
    if (typeof resolveReady === 'function') {
      resolveReady(PRODUCTS);
    }
  }

  function getCart() {
    const cart = safeParse(localStorage.getItem(STORAGE_KEY), []);
    return Array.isArray(cart) ? cart : [];
  }

  function setCart(cart) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    notifyCartChange();
  }

  function getProduct(id) {
    return PRODUCTS[id] || null;
  }

  function listProducts() {
    return Object.values(PRODUCTS);
  }

  function getCartItems() {
    return getCart()
      .map((item) => {
        const product = getProduct(item.id);
        if (!product) return null;

        const qty = Math.max(1, Math.min(99, Number(item.qty) || 1));
        return {
          id: item.id,
          qty,
          product,
          lineTotal: product.price * qty
        };
      })
      .filter(Boolean);
  }

  function getCartCount(cart = getCart()) {
    return cart.reduce((sum, item) => sum + Math.max(1, Number(item.qty) || 1), 0);
  }

  function getCartSubtotal(cart = getCart()) {
    return cart.reduce((sum, item) => {
      const product = getProduct(item.id);
      if (!product) return sum;
      return sum + product.price * Math.max(1, Number(item.qty) || 1);
    }, 0);
  }

  function formatMoney(amount) {
    const value = Number(amount || 0);
    return `₦${value.toLocaleString('en-NG', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    })}`;
  }

  function setItemQty(id, qty) {
    const nextQty = Math.max(0, Math.min(99, Number(qty) || 0));
    const cart = getCart();
    const index = cart.findIndex((item) => item.id === id);

    if (index === -1) {
      if (nextQty > 0) {
        cart.push({ id, qty: nextQty });
      }
    } else if (nextQty === 0) {
      cart.splice(index, 1);
    } else {
      cart[index].qty = nextQty;
    }

    setCart(cart);
    return cart;
  }

  function addItem(id, qty = 1) {
    const product = getProduct(id);
    if (!product) return getCart();

    const cart = getCart();
    const index = cart.findIndex((item) => item.id === id);
    const increment = Math.max(1, Math.min(99, Number(qty) || 1));

    if (index === -1) {
      cart.push({ id, qty: increment });
    } else {
      cart[index].qty = Math.min(99, Math.max(1, Number(cart[index].qty) || 1) + increment);
    }

    setCart(cart);
    return cart;
  }

  function removeItem(id) {
    const cart = getCart().filter((item) => item.id !== id);
    setCart(cart);
    return cart;
  }

  function clearCart() {
    setCart([]);
  }

  function updateCartBadges() {
    const count = getCartCount();
    document.querySelectorAll('[data-cart-count]').forEach((node) => {
      node.textContent = String(count);
      node.classList.toggle('hidden', count === 0);
    });
    return count;
  }

  function notifyCartChange() {
    const cart = getCartItems();
    const detail = {
      cart,
      count: getCartCount(),
      subtotal: getCartSubtotal()
    };

    window.dispatchEvent(new CustomEvent('extra-store:cart-changed', { detail }));
    updateCartBadges();
  }

  function showToast(message) {
    let toast = document.getElementById('extra-store-toast');

    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'extra-store-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.style.position = 'fixed';
      toast.style.right = '1rem';
      toast.style.bottom = '1rem';
      toast.style.zIndex = '999';
      toast.style.padding = '0.85rem 1rem';
      toast.style.borderRadius = '999px';
      toast.style.background = '#7a1f2b';
      toast.style.color = '#fff';
      toast.style.fontWeight = '700';
      toast.style.fontSize = '0.875rem';
      toast.style.boxShadow = '0 18px 40px rgba(0,0,0,0.28)';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(16px)';
      toast.style.transition = 'opacity 180ms ease, transform 180ms ease';
      document.body.appendChild(toast);
    }

    toast.textContent = message;
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });

    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(16px)';
    }, 1800);
  }

  async function loadProducts() {
    try {
      const response = await fetch('products.php', {
        headers: {
          Accept: 'application/json'
        },
        cache: 'no-store'
      });

      if (!response.ok) {
        throw new Error(`Products request failed with status ${response.status}`);
      }

      const payload = await response.json();
      if (!payload || !payload.ok || !Array.isArray(payload.products)) {
        throw new Error('Products payload was not valid.');
      }

      PRODUCTS = normalizeProductMap(payload.products);
      if (window.ExtraStore) {
        window.ExtraStore.PRODUCTS = PRODUCTS;
      }
      window.dispatchEvent(new CustomEvent('extra-store:products-changed', {
        detail: { products: listProducts() }
      }));
    } catch (error) {
      PRODUCTS = normalizeProductMap(DEFAULT_PRODUCTS);
      if (window.ExtraStore) {
        window.ExtraStore.PRODUCTS = PRODUCTS;
      }
    } finally {
      finishReady();
    }

    return PRODUCTS;
  }

  function whenReady(callback) {
    ready.then(() => {
      if (typeof callback === 'function') {
        callback(PRODUCTS);
      }
    });
  }

  window.ExtraStore = {
    DEFAULT_PRODUCTS,
    STORAGE_KEY,
    PRODUCTS,
    addItem,
    clearCart,
    formatMoney,
    getCart,
    getCartCount,
    getCartItems,
    getCartSubtotal,
    getProduct,
    listProducts,
    loadProducts,
    notifyCartChange,
    removeItem,
    setCart,
    setItemQty,
    showToast,
    ready,
    whenReady,
    updateCartBadges
  };

  document.addEventListener('DOMContentLoaded', updateCartBadges);
  window.addEventListener('storage', updateCartBadges);

  loadProducts();
})();
