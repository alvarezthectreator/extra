document.addEventListener('DOMContentLoaded', () => {
  const products = {
    'umbrella-red': {
      id: 'umbrella-red',
      name: 'Charging Intelligence Electric Umbrella',
      price: 39.99,
      images: [
        'assets/red-product-transparent-v4.png',
        'assets/red-product-clean.png',
        'assets/red-product-transparent.png'
      ],
      desc: `Fully-automatic operation with intelligent charging. Compact 3-folding design. Windproof & UV protective with durable frame. Large 55–61cm radius. Available in multiple stylish colors. High-quality nylon material with no harmful chemicals. Lightweight at 0.600 kg.`
    },
    'umbrella-green': {
      id: 'umbrella-green',
      name: 'Green Windproof Umbrella',
      price: 23.19,
      images: ['assets/green.jpg','assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg'],
      desc: 'Compact, windproof umbrella with UV protection.'
    },
    'umbrella-blue': {
      id: 'umbrella-blue',
      name: 'Blue Compact Auto Umbrella',
      price: 27.14,
      images: ['assets/imgi_366_Hab9faf1e4c674af48cef4986f2494d0e7.jpg','assets/tfgh.png'],
      desc: 'Stylish compact umbrella with automatic open/close.'
    },
    'umbrella-pink': {
      id: 'umbrella-pink',
      name: 'Pink Compact Umbrella',
      price: 29.99,
      images: ['assets/WhatsApp Image 2026-08-13 at 18.35.25.jpeg'],
      desc: 'Colorful umbrella with protective coating.'
    }
  };

  let cart = JSON.parse(localStorage.getItem('cart') || '[]');

  // helpers
  function saveCart() { localStorage.setItem('cart', JSON.stringify(cart)); }
  function findInCart(id) { return cart.find(i => i.id === id); }

  // modal elements
  const modal = document.getElementById('productModal');
  const detailMain = document.getElementById('detailMain');
  const detailThumbs = document.getElementById('detailThumbs');
  const detailTitle = document.getElementById('detailTitle');
  const detailPrice = document.getElementById('detailPrice');
  const detailDesc = document.getElementById('detailDesc');
  const qtyValue = document.getElementById('qtyValue');
  const qtyInc = document.getElementById('qtyInc');
  const qtyDec = document.getElementById('qtyDec');
  const detailAdd = document.getElementById('detailAdd');
  const detailRemove = document.getElementById('detailRemove');
  const detailClose = document.getElementById('detailClose');

  let activeProduct = null;
  let activeQty = 1;

  function openModal(productId) {
    const p = products[productId];
    if (!p) return;
    activeProduct = p;
    activeQty = 1;
    detailTitle.textContent = p.name;
    detailPrice.textContent = `$${p.price.toFixed(2)}`;
    detailDesc.textContent = p.desc;
    detailMain.src = p.images[0];
    detailThumbs.innerHTML = '';
    p.images.forEach((src, i) => {
      const el = document.createElement('div');
      el.className = 'detail-thumb' + (i===0? ' active':'');
      el.dataset.src = src;
      el.innerHTML = `<img src="${src}" alt="thumb">`;
      el.addEventListener('click', () => {
        document.querySelectorAll('.detail-thumb').forEach(t=>t.classList.remove('active'));
        el.classList.add('active');
        detailMain.src = src;
      });
      detailThumbs.appendChild(el);
    });

    // update qty and cart buttons
    qtyValue.textContent = String(activeQty);
    const inCart = findInCart(p.id);
    if (inCart) {
      detailRemove.classList.remove('hidden');
      detailAdd.textContent = 'In Cart';
      qtyValue.textContent = String(inCart.qty);
      activeQty = inCart.qty;
    } else {
      detailRemove.classList.add('hidden');
      detailAdd.textContent = 'Add to Cart';
    }

    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
  }

  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    activeProduct = null;
  }

  // attach triggers
  document.querySelectorAll('.product-detail-trigger').forEach(img => {
    img.addEventListener('click', e => {
      const id = img.dataset.product;
      openModal(id);
    });
  });

  detailClose.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  qtyInc.addEventListener('click', ()=>{
    activeQty = Math.min(99, activeQty+1);
    qtyValue.textContent = String(activeQty);
  });
  qtyDec.addEventListener('click', ()=>{
    activeQty = Math.max(1, activeQty-1);
    qtyValue.textContent = String(activeQty);
  });

  detailAdd.addEventListener('click', ()=>{
    if (!activeProduct) return;
    const existing = findInCart(activeProduct.id);
    if (existing) {
      existing.qty = activeQty;
    } else {
      cart.push({ id: activeProduct.id, name: activeProduct.name, price: activeProduct.price, qty: activeQty });
    }
    saveCart();
    detailAdd.textContent = 'In Cart';
    detailRemove.classList.remove('hidden');
    alert('Added to cart');
  });

  detailRemove.addEventListener('click', ()=>{
    if (!activeProduct) return;
    cart = cart.filter(i => i.id !== activeProduct.id);
    saveCart();
    detailRemove.classList.add('hidden');
    detailAdd.textContent = 'Add to Cart';
    qtyValue.textContent = '1';
    activeQty = 1;
    alert('Removed from cart');
  });

  // expose cart for debugging
  window.__SHOP = { products, cart };
});
