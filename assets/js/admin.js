(function () {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const sidebar = document.querySelector('[data-admin-sidebar]');
  const sidebarBackdrop = document.querySelector('[data-admin-backdrop]');
  const sidebarToggle = document.querySelector('[data-admin-sidebar-toggle]');
  const sidebarClose = document.querySelector('[data-admin-sidebar-close]');
  const searchInputs = Array.from(document.querySelectorAll('[data-admin-search]'));
  const filterInputs = {
    category: document.querySelector('[data-admin-filter="category"]'),
    store: document.querySelector('[data-admin-filter="store"]'),
    color: document.querySelector('[data-admin-filter="color"]'),
    price: document.querySelector('[data-admin-filter="price"]'),
    sort: document.querySelector('[data-admin-filter="sort"]')
  };
  const clearFiltersButton = document.querySelector('[data-admin-clear-filters]');
  const tableBody = document.querySelector('[data-product-table-body]');
  const emptyState = document.querySelector('[data-product-empty]');
  const visibleCounts = Array.from(document.querySelectorAll('[data-product-visible-count]'));
  const productRows = () => Array.from(tableBody?.querySelectorAll('[data-product-row]') || []);

  const form = document.querySelector('[data-product-form]');
  const actionTypeField = document.querySelector('[data-product-action-type]');
  const idField = document.querySelector('[data-product-id]');
  const nameField = document.querySelector('[data-product-name-input]');
  const priceField = document.querySelector('[data-product-price-input]');
  const colorField = document.querySelector('[data-product-color-input]');
  const categoryField = document.querySelector('[data-product-category-input]');
  const storefrontField = document.querySelector('[data-product-storefront-input]');
  const imageField = document.querySelector('[data-product-image-input]');
  const imageUploadField = document.querySelector('[data-product-image-upload]');
  const galleryField = document.querySelector('[data-product-gallery-input]');
  const galleryUploadField = document.querySelector('[data-product-gallery-upload]');
  const descriptionField = document.querySelector('[data-product-description-input]');
  const preview = document.querySelector('[data-product-preview]');
  const editorMode = document.querySelector('[data-editor-mode]');
  const submitButton = document.querySelector('[data-product-submit]');
  const newProductButtons = Array.from(document.querySelectorAll('[data-new-product]'));
  const editButtons = Array.from(document.querySelectorAll('[data-edit-product]'));
  const deleteForms = Array.from(document.querySelectorAll('[data-delete-form]'));
  const sidebarLinks = Array.from(document.querySelectorAll('[data-admin-nav-link]'));
  const sectionTargets = sidebarLinks
    .map((link) => {
      const href = link.getAttribute('href') || '';
      return href.startsWith('#') ? document.querySelector(href) : null;
    })
    .filter(Boolean);

  let previewBlobUrl = '';

  function syncSidebarState() {
    if (!sidebar) return;

    if (window.innerWidth > 1280) {
      document.body.classList.remove('sidebar-open');
      sidebar.setAttribute('aria-hidden', 'false');
      if (sidebarBackdrop) {
        sidebarBackdrop.hidden = true;
      }
      if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', 'false');
      }
      return;
    }

    sidebar.setAttribute('aria-hidden', 'true');
    if (sidebarBackdrop) {
      sidebarBackdrop.hidden = true;
    }
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-expanded', 'false');
    }
  }

  function normalize(value) {
    return String(value ?? '').trim().toLowerCase();
  }

  function openSidebar() {
    document.body.classList.add('sidebar-open');
    if (sidebarBackdrop) {
      sidebarBackdrop.hidden = false;
    }
    if (sidebar) {
      sidebar.setAttribute('aria-hidden', 'false');
    }
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-expanded', 'true');
    }
  }

  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    if (sidebarBackdrop) {
      sidebarBackdrop.hidden = true;
    }
    if (sidebar) {
      sidebar.setAttribute('aria-hidden', 'true');
    }
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-expanded', 'false');
    }
  }

  function setPreviewSource(src) {
    if (!preview) return;
    if (previewBlobUrl) {
      URL.revokeObjectURL(previewBlobUrl);
      previewBlobUrl = '';
    }
    preview.src = src || preview.getAttribute('src') || '';
  }

  function setPreviewFromFile(file) {
    if (!preview || !file) return;
    if (previewBlobUrl) {
      URL.revokeObjectURL(previewBlobUrl);
    }
    previewBlobUrl = URL.createObjectURL(file);
    preview.src = previewBlobUrl;
  }

  function setEditorMode(mode, product = null, storefront = 'extra') {
    if (!actionTypeField || !submitButton || !editorMode || !idField) return;

    actionTypeField.value = mode;
    submitButton.textContent = mode === 'create' ? 'Create Product' : 'Save Changes';
    editorMode.textContent = mode === 'create'
      ? 'Creating a new product'
      : `Editing ${product?.id || 'product'}`;

    idField.readOnly = mode === 'update';
    idField.placeholder = mode === 'create' ? 'Leave blank for auto ID' : '';

    if (mode === 'create' && storefrontField) {
      storefrontField.value = storefront;
    }
  }

  function fillEditor(data) {
    if (!data || !form) return;

    if (idField) {
      idField.value = data.id || '';
    }
    if (nameField) {
      nameField.value = data.name || '';
    }
    if (priceField) {
      priceField.value = data.price || '';
    }
    if (colorField) {
      colorField.value = data.color || '';
    }
    if (categoryField) {
      categoryField.value = data.category || '';
    }
    if (storefrontField) {
      storefrontField.value = data.storefront || 'extra';
    }
    if (imageField) {
      imageField.value = data.image || '';
    }
    if (galleryField) {
      galleryField.value = data.gallery || '';
    }
    if (descriptionField) {
      descriptionField.value = data.description || '';
    }
    if (imageUploadField) {
      imageUploadField.value = '';
    }
    if (galleryUploadField) {
      galleryUploadField.value = '';
    }

    setPreviewSource(data.image || preview?.getAttribute('src') || '');
    setEditorMode('update', data, data.storefront || 'extra');
    document.getElementById('editor')?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
  }

  function clearEditor(storefront = 'extra') {
    if (!form) return;

    form.reset();

    if (idField) idField.value = '';
    if (nameField) nameField.value = '';
    if (priceField) priceField.value = '';
    if (colorField) colorField.value = '';
    if (categoryField) categoryField.value = '';
    if (storefrontField) storefrontField.value = storefront;
    if (imageField) imageField.value = '';
    if (galleryField) galleryField.value = '';
    if (descriptionField) descriptionField.value = '';
    if (imageUploadField) imageUploadField.value = '';
    if (galleryUploadField) galleryUploadField.value = '';

    setPreviewSource('assets/red-product-clean.png');
    setEditorMode('create', null, storefront);
    nameField?.focus();
    document.getElementById('editor')?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
  }

  function priceMatchesBand(price, band) {
    if (band === 'under-20000') return price < 20000;
    if (band === '20000-24999') return price >= 20000 && price <= 24999;
    if (band === '25000-plus') return price >= 25000;
    return true;
  }

  function applyFilters() {
    if (!tableBody) return;

    const query = searchInputs.map((input) => normalize(input.value)).find(Boolean) || '';
    const category = normalize(filterInputs.category?.value || 'all');
    const store = normalize(filterInputs.store?.value || 'all');
    const color = normalize(filterInputs.color?.value || 'all');
    const priceBand = normalize(filterInputs.price?.value || 'all');
    const sortMode = normalize(filterInputs.sort?.value || 'featured');

    const rows = productRows();
    const visible = rows.filter((row) => {
      const text = [
        row.dataset.productId,
        row.dataset.productName,
        row.dataset.productCategory,
        row.dataset.productColor,
        row.dataset.productStore,
        row.dataset.productGallery,
        row.dataset.productDescription
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      const matchesQuery = !query || text.includes(query);
      const matchesCategory = category === 'all' || normalize(row.dataset.productCategory) === category;
      const matchesStore = store === 'all' || normalize(row.dataset.productStore) === store;
      const matchesColor = color === 'all' || normalize(row.dataset.productColor) === color;
      const matchesPrice = priceMatchesBand(Number(row.dataset.productPrice || 0), priceBand);
      return matchesQuery && matchesCategory && matchesStore && matchesColor && matchesPrice;
    });

    const sorted = [...visible].sort((left, right) => {
      if (sortMode === 'price-low') {
        return Number(left.dataset.productPrice || 0) - Number(right.dataset.productPrice || 0);
      }
      if (sortMode === 'price-high') {
        return Number(right.dataset.productPrice || 0) - Number(left.dataset.productPrice || 0);
      }
      if (sortMode === 'name') {
        return (left.dataset.productName || '').localeCompare(right.dataset.productName || '');
      }
      if (sortMode === 'recent') {
        return (right.dataset.productUpdated || '').localeCompare(left.dataset.productUpdated || '');
      }
      return Number(left.dataset.productSort || 0) - Number(right.dataset.productSort || 0);
    });

    const visibleSet = new Set(sorted);
    rows.forEach((row) => {
      row.classList.toggle('is-hidden', !visibleSet.has(row));
    });

    sorted.forEach((row) => tableBody.appendChild(row));

    visibleCounts.forEach((node) => {
      node.textContent = String(sorted.length);
    });

    if (emptyState) {
      emptyState.hidden = sorted.length > 0;
    }
  }

  sidebarToggle?.addEventListener('click', () => {
    const open = !document.body.classList.contains('sidebar-open');
    if (open) {
      openSidebar();
    } else {
      closeSidebar();
    }
  });

  sidebarClose?.addEventListener('click', closeSidebar);
  sidebarBackdrop?.addEventListener('click', closeSidebar);

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  window.addEventListener('resize', () => {
    syncSidebarState();
  });

  sidebarLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      const href = link.getAttribute('href') || '';
      if (!href.startsWith('#')) {
        return;
      }

      const target = document.querySelector(href);
      if (target) {
        event.preventDefault();
        target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
      }

      if (window.innerWidth <= 1280) {
        closeSidebar();
      }
    });
  });

  newProductButtons.forEach((button) => {
    button.addEventListener('click', () => {
      clearEditor(button.dataset.storefront || filterInputs.store?.value || 'extra');
    });
  });

  editButtons.forEach((button) => {
    button.addEventListener('click', () => {
      fillEditor({
        id: button.dataset.productId,
        name: button.dataset.productName,
        price: button.dataset.productPrice,
        color: button.dataset.productColor,
        category: button.dataset.productCategory,
        image: button.dataset.productImage,
        gallery: button.dataset.productGallery,
        description: button.dataset.productDescription,
        storefront: button.dataset.productStore
      });
    });
  });

  document.addEventListener('click', (event) => {
    const row = event.target.closest?.('[data-product-row]');
    if (!row) {
      return;
    }

    if (event.target.closest('button, a, input, select, textarea, label, form')) {
      return;
    }

    fillEditor({
      id: row.dataset.productId,
      name: row.dataset.productName,
      price: row.dataset.productPrice,
      color: row.dataset.productColor,
      category: row.dataset.productCategory,
      image: row.dataset.productImage,
      gallery: row.dataset.productGallery,
      description: row.dataset.productDescription,
      storefront: row.dataset.productStore
    });
  });

  deleteForms.forEach((formEl) => {
    formEl.addEventListener('submit', (event) => {
      const name = formEl.dataset.productName || 'this product';
      if (!window.confirm(`Delete ${name}?`)) {
        event.preventDefault();
      }
    });
  });

  searchInputs.forEach((input) => {
    input.addEventListener('input', () => {
      const value = input.value;
      searchInputs.forEach((other) => {
        if (other !== input && other.value !== value) {
          other.value = value;
        }
      });
      applyFilters();
    });
  });

  Object.values(filterInputs).forEach((input) => {
    input?.addEventListener('change', applyFilters);
  });

  clearFiltersButton?.addEventListener('click', () => {
    searchInputs.forEach((input) => {
      input.value = '';
    });

    if (filterInputs.category) filterInputs.category.value = 'all';
    if (filterInputs.store) filterInputs.store.value = 'all';
    if (filterInputs.color) filterInputs.color.value = 'all';
    if (filterInputs.price) filterInputs.price.value = 'all';
    if (filterInputs.sort) filterInputs.sort.value = 'featured';

    applyFilters();
  });

  imageField?.addEventListener('input', () => {
    const value = imageField.value.trim();
    if (value) {
      setPreviewSource(value);
    }
  });

  imageUploadField?.addEventListener('change', () => {
    const file = imageUploadField.files && imageUploadField.files[0] ? imageUploadField.files[0] : null;
    if (file) {
      setPreviewFromFile(file);
    }
  });

  form?.addEventListener('reset', () => {
    window.requestAnimationFrame(() => {
      applyFilters();
    });
  });

  syncSidebarState();
  applyFilters();

  if (window.location.hash) {
    const target = document.querySelector(window.location.hash);
    target?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
  }

  document.dispatchEvent(new CustomEvent('admin-dashboard:ready'));
})();
