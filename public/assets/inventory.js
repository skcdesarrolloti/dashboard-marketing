(() => {
  const productModal = document.querySelector('[data-product-modal]');
  const productForm = productModal?.querySelector('[data-product-form]');
  const categorySelect = productModal?.querySelector('[data-category-select]');
  const categoryCreate = productModal?.querySelector('[data-category-create]');
  const newCategoryInput = productModal?.querySelector('[data-new-category]');
  const saveCategoryButton = productModal?.querySelector('[data-save-category]');
  const categoryMessage = productModal?.querySelector('[data-category-message]');
  const productImageInput = productModal?.querySelector('[data-product-image-input]');
  const productImagePreview = productModal?.querySelector('[data-product-image-preview]');
  const productImagePreviewImg = productModal?.querySelector('[data-product-image-preview-img]');
  const productImagePreviewTitle = productModal?.querySelector('[data-product-image-preview-title]');
  const productImagePreviewName = productModal?.querySelector('[data-product-image-preview-name]');
  const productImageMessage = productModal?.querySelector('[data-product-image-message]');
  const clearProductImageButton = productModal?.querySelector('[data-clear-product-image]');
  const deliveryModal = document.querySelector('[data-delivery-modal]');
  const apiModal = document.querySelector('[data-api-modal]');
  let activeInventoryModal = null;
  let inventoryModalReturnFocus = null;
  let inventoryModalCloseTimer = null;
  let lastCategoryValue = 'Souvenir';
  let currentProductImageUrl = '';
  let productImageObjectUrl = '';

  const revokeProductImagePreview = () => {
    if (productImageObjectUrl) URL.revokeObjectURL(productImageObjectUrl);
    productImageObjectUrl = '';
  };

  const showProductImagePreview = (url, title, name, canClear = false) => {
    if (!productImagePreview || !productImagePreviewImg) return;
    productImagePreview.hidden = !url;
    productImagePreviewImg.src = url || '';
    if (productImagePreviewTitle) productImagePreviewTitle.textContent = title;
    if (productImagePreviewName) productImagePreviewName.textContent = name;
    if (clearProductImageButton) clearProductImageButton.hidden = !canClear;
  };

  const resetProductImage = (url = '') => {
    revokeProductImagePreview();
    currentProductImageUrl = url;
    if (productImageInput) productImageInput.value = '';
    if (productImageMessage) {
      productImageMessage.textContent = '';
      productImageMessage.classList.remove('is-error');
    }
    if (url) {
      showProductImagePreview(url, 'Imagen actual', 'Se conservará si no seleccionas otra imagen.');
    } else {
      showProductImagePreview('', '', '');
    }
  };

  const openInventoryModal = (modal, trigger, focusTarget = null) => {
    if (!modal) return;
    if (inventoryModalCloseTimer) window.clearTimeout(inventoryModalCloseTimer);
    if (activeInventoryModal && activeInventoryModal !== modal) {
      activeInventoryModal.classList.remove('is-open');
      activeInventoryModal.hidden = true;
    }
    activeInventoryModal = modal;
    inventoryModalReturnFocus = trigger || document.activeElement;
    modal.hidden = false;
    document.body.classList.add('inventory-modal-open');
    window.requestAnimationFrame(() => {
      modal.classList.add('is-open');
      window.requestAnimationFrame(() => {
        const target = focusTarget
          || modal.querySelector('[data-modal-initial-focus]')
          || modal.querySelector('button:not([tabindex="-1"]), input, select, textarea');
        target?.focus();
      });
    });
  };

  const closeInventoryModal = (modal = activeInventoryModal) => {
    if (!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('inventory-modal-open');
    const finishClose = () => {
      modal.hidden = true;
      if (activeInventoryModal === modal) activeInventoryModal = null;
      inventoryModalReturnFocus?.focus?.();
    };
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      finishClose();
      return;
    }
    inventoryModalCloseTimer = window.setTimeout(finishClose, 200);
  };

  const setCategoryMessage = (message = '', isError = false) => {
    if (!categoryMessage) return;
    categoryMessage.textContent = message;
    categoryMessage.classList.toggle('is-error', isError);
    categoryMessage.classList.toggle('is-success', message !== '' && !isError);
  };

  const resetCategoryCreator = () => {
    if (categoryCreate) categoryCreate.hidden = true;
    if (newCategoryInput) newCategoryInput.value = '';
    if (saveCategoryButton) {
      saveCategoryButton.disabled = false;
      saveCategoryButton.textContent = 'Guardar';
    }
    setCategoryMessage();
    if (categorySelect && categorySelect.value !== '__add_category__') {
      lastCategoryValue = categorySelect.value || 'Souvenir';
    }
  };

  const setProductField = (name, value) => {
    const field = productForm?.elements.namedItem(name);
    if (!field) return;
    field.value = value === null || value === undefined ? '' : String(value);
  };

  const setProductMode = (product = null) => {
    if (!productModal || !productForm) return;
    const isEditing = Boolean(product?.id);
    productForm.reset();

    setProductField('id', isEditing ? product.id : 0);
    setProductField('name', product?.name ?? '');
    setProductField('type', isEditing ? product?.type : 'Souvenir');
    setProductField('sku', product?.sku ?? '');
    setProductField('unit', isEditing ? product?.unit : 'unidad');
    setProductField('initial_quantity', 0);
    setProductField('minimum_stock', product?.minimum_stock ?? 0);
    setProductField('price', product?.price ?? '');
    setProductField('points', product?.points ?? '');
    setProductField('description', product?.description ?? '');
    resetProductImage(product?.image_url ?? '');

    const syncField = productForm.elements.namedItem('sync_pph');
    if (syncField) syncField.checked = Boolean(product?.sync_pph);

    productModal.querySelectorAll('[data-create-only]').forEach((section) => {
      section.hidden = isEditing;
      section.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = isEditing;
      });
    });

    productModal.querySelector('[data-evidence-preview]')?.replaceChildren();
    productModal.querySelector('[data-product-modal-kicker]').textContent = isEditing ? 'Edición de producto' : 'Nuevo registro';
    productModal.querySelector('[data-product-modal-title]').textContent = isEditing ? 'Editar producto' : 'Agregar producto';
    productModal.querySelector('[data-product-modal-description]').textContent = isEditing
      ? 'Actualiza la información del producto. La cantidad se modifica desde “Ajustar cantidad”.'
      : 'Completa los datos y las existencias iniciales.';
    productModal.querySelector('[data-product-submit]').textContent = isEditing ? 'Guardar cambios' : 'Crear producto';
    resetCategoryCreator();
  };

  const openProductModal = (product, trigger) => {
    if (!productModal || !productForm) return;
    setProductMode(product);
    openInventoryModal(productModal, trigger, productForm.elements.namedItem('name'));
  };

  const closeProductModal = () => {
    closeInventoryModal(productModal);
  };

  document.querySelector('[data-open-product-modal]')?.addEventListener('click', (event) => {
    openProductModal(null, event.currentTarget);
  });

  document.querySelectorAll('[data-edit-product]').forEach((button) => {
    button.addEventListener('click', () => {
      try {
        openProductModal(JSON.parse(button.dataset.product || '{}'), button);
      } catch (_) {
        openProductModal(null, button);
      }
    });
  });

  productModal?.querySelectorAll('[data-close-product-modal]').forEach((button) => {
    button.addEventListener('click', closeProductModal);
  });

  productImageInput?.addEventListener('change', () => {
    const file = productImageInput.files?.[0];
    if (!file) {
      resetProductImage(currentProductImageUrl);
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      productImageInput.value = '';
      if (productImageMessage) {
        productImageMessage.textContent = file.size > 5 * 1024 * 1024
          ? 'La imagen supera el límite de 5 MB.'
          : 'Selecciona una imagen JPG, PNG o WebP.';
        productImageMessage.classList.add('is-error');
      }
      if (currentProductImageUrl) {
        showProductImagePreview(currentProductImageUrl, 'Imagen actual', 'Selecciona otro archivo para reemplazarla.');
      } else {
        showProductImagePreview('', '', '');
      }
      productImageInput.focus();
      return;
    }

    revokeProductImagePreview();
    productImageObjectUrl = URL.createObjectURL(file);
    showProductImagePreview(productImageObjectUrl, 'Nueva imagen', file.name, true);
    if (productImageMessage) {
      productImageMessage.textContent = 'La imagen se guardará al confirmar el producto.';
      productImageMessage.classList.remove('is-error');
    }
  });

  clearProductImageButton?.addEventListener('click', () => {
    resetProductImage(currentProductImageUrl);
    productImageInput?.focus();
  });

  document.querySelector('[data-open-delivery-modal]')?.addEventListener('click', (event) => {
    openInventoryModal(deliveryModal, event.currentTarget);
  });

  document.querySelector('[data-open-api-modal]')?.addEventListener('click', (event) => {
    openInventoryModal(apiModal, event.currentTarget);
  });

  document.querySelectorAll('[data-close-inventory-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      closeInventoryModal(button.closest('.inventory-modal'));
    });
  });

  categorySelect?.addEventListener('change', () => {
    setCategoryMessage();
    if (categorySelect.value === '__add_category__') {
      if (categoryCreate) categoryCreate.hidden = false;
      window.requestAnimationFrame(() => newCategoryInput?.focus());
      return;
    }
    lastCategoryValue = categorySelect.value || 'Souvenir';
    if (categoryCreate) categoryCreate.hidden = true;
    if (newCategoryInput) newCategoryInput.value = '';
  });

  productModal?.querySelector('[data-cancel-category]')?.addEventListener('click', () => {
    if (categorySelect) categorySelect.value = lastCategoryValue;
    if (categoryCreate) categoryCreate.hidden = true;
    if (newCategoryInput) newCategoryInput.value = '';
    setCategoryMessage();
    categorySelect?.focus();
  });

  const saveCategory = async () => {
    if (!productForm || !categorySelect || !newCategoryInput || !saveCategoryButton) return;
    const category = newCategoryInput.value.trim();
    if (category.length < 2) {
      setCategoryMessage('Escribe una categoría de al menos 2 caracteres.', true);
      newCategoryInput.focus();
      return;
    }

    const body = new FormData();
    body.set('action', 'inventory_add_category');
    body.set('_token', productForm.elements.namedItem('_token')?.value || '');
    body.set('category', category);
    saveCategoryButton.disabled = true;
    saveCategoryButton.textContent = 'Guardando…';
    setCategoryMessage('Guardando categoría…');

    try {
      const response = await fetch(window.location.href, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok || !payload.category) {
        throw new Error(payload.message || 'No fue posible guardar la categoría.');
      }

      let option = [...categorySelect.options].find(
        (candidate) => candidate.value.toLocaleLowerCase('es') === payload.category.toLocaleLowerCase('es')
      );
      if (!option) {
        option = document.createElement('option');
        option.value = payload.category;
        option.textContent = payload.category;
        categorySelect.insertBefore(option, categorySelect.querySelector('[data-add-category-option]'));
      }
      categorySelect.value = option.value;
      lastCategoryValue = option.value;
      if (categoryCreate) categoryCreate.hidden = true;
      newCategoryInput.value = '';
      setCategoryMessage(`Categoría “${option.value}” guardada.`, false);
      categorySelect.focus();
    } catch (error) {
      setCategoryMessage(error.message || 'No fue posible guardar la categoría.', true);
      newCategoryInput.focus();
    } finally {
      saveCategoryButton.disabled = false;
      saveCategoryButton.textContent = 'Guardar';
    }
  };

  saveCategoryButton?.addEventListener('click', saveCategory);
  newCategoryInput?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    saveCategory();
  });
  productForm?.addEventListener('submit', (event) => {
    if (categorySelect?.value !== '__add_category__') return;
    event.preventDefault();
    event.stopPropagation();
    setCategoryMessage('Guarda o cancela la nueva categoría antes de continuar.', true);
    newCategoryInput?.focus();
  });

  document.addEventListener('keydown', (event) => {
    if (!activeInventoryModal || activeInventoryModal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeInventoryModal(activeInventoryModal);
      return;
    }
    if (event.key !== 'Tab') return;

    const focusable = [...activeInventoryModal.querySelectorAll(
      'button:not([disabled]):not([tabindex="-1"]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )].filter((element) => element.getClientRects().length > 0);
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  const deliveryForm = document.querySelector('[data-inventory-delivery-form]');
  if (deliveryForm) {
    const items = deliveryForm.querySelector('[data-delivery-items]');
    const addButton = deliveryForm.querySelector('[data-add-delivery-item]');
    let itemIndex = items?.querySelectorAll('[data-delivery-item]').length || 1;

    const bindStock = (row) => {
      const select = row.querySelector('select');
      const quantity = row.querySelector('input[type="number"]');
      const hint = row.querySelector('[data-stock-hint]');
      const update = () => {
        const option = select?.selectedOptions?.[0];
        const stock = Number(option?.dataset?.stock || 0);
        if (quantity && option?.value) {
          quantity.max = String(stock);
          if (Number(quantity.value) > stock) quantity.value = String(Math.max(1, stock));
        }
        if (hint) {
          hint.textContent = option?.value
            ? `${stock.toLocaleString('es-CO')} unidades disponibles.`
            : 'Selecciona un producto para ver existencias.';
        }
      };
      select?.addEventListener('change', update);
      update();
    };

    items?.querySelectorAll('[data-delivery-item]').forEach(bindStock);

    addButton?.addEventListener('click', () => {
      const source = items?.querySelector('[data-delivery-item]');
      if (!source || !items) return;
      const row = source.cloneNode(true);
      row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/\[\d+]/, `[${itemIndex}]`);
        field.id = field.id.replace(/-\d+$/, `-${itemIndex}`);
        if (field.tagName === 'SELECT') field.value = '';
        if (field.matches('input[type="number"]')) field.value = '1';
      });
      row.querySelectorAll('label[for]').forEach((label) => {
        label.htmlFor = label.htmlFor.replace(/-\d+$/, `-${itemIndex}`);
      });
      itemIndex += 1;
      items.appendChild(row);
      bindStock(row);
      row.querySelector('select')?.focus();
    });

    items?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-remove-delivery-item]');
      if (!button) return;
      const rows = items.querySelectorAll('[data-delivery-item]');
      if (rows.length === 1) {
        const row = rows[0];
        const select = row.querySelector('select');
        const quantity = row.querySelector('input[type="number"]');
        if (select) select.value = '';
        if (quantity) quantity.value = '1';
        select?.focus();
        return;
      }
      button.closest('[data-delivery-item]')?.remove();
    });

  }

  document.querySelectorAll('[data-evidence-input]').forEach((evidenceInput) => {
    evidenceInput.addEventListener('change', () => {
      const selected = [...(evidenceInput.files || [])];
      const evidencePreview = evidenceInput.closest('form')?.querySelector('[data-evidence-preview]');
      evidencePreview?.replaceChildren();
      if (selected.length > 5) {
        evidenceInput.value = '';
        if (evidencePreview) evidencePreview.textContent = 'Selecciona máximo cinco evidencias.';
        evidenceInput.focus();
        return;
      }
      const oversized = selected.find((file) => file.size > 8 * 1024 * 1024);
      if (oversized) {
        evidenceInput.value = '';
        if (evidencePreview) evidencePreview.textContent = `${oversized.name} supera el límite de 8 MB.`;
        evidenceInput.focus();
        return;
      }
      selected.forEach((file) => {
        const chip = document.createElement('span');
        chip.textContent = file.name;
        evidencePreview?.appendChild(chip);
      });
    });
  });

  document.querySelector('[data-copy-api]')?.addEventListener('click', async (event) => {
    const example = document.querySelector('[data-api-example]')?.textContent || '';
    try {
      await navigator.clipboard.writeText(example);
      event.currentTarget.textContent = 'Copiado';
      window.setTimeout(() => { event.currentTarget.textContent = 'Copiar'; }, 2500);
    } catch (_) {
      document.querySelector('[data-api-example]')?.focus();
    }
  });
})();
