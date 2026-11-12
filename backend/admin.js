document.addEventListener('DOMContentLoaded', function () {
    const usersTableBody = document.getElementById('usersTableBody');
    const createAccountForm = document.getElementById('createAccountForm');

    if (usersTableBody && createAccountForm) {
        const roleNames = { '1': 'Customer', '2': 'Administrator' };
        const roleFilters = Array.from(document.querySelectorAll('[data-role-filter]'));
        const emptyUsersRow = document.createElement('tr');
        const emptyUsersCell = document.createElement('td');
        let selectedRole = 'all';
        let nextUserId = Math.max(0, ...Array.from(usersTableBody.querySelectorAll('tr[data-user-id]'))
            .map(row => Number(row.dataset.userId) || 0)) + 1;

        emptyUsersCell.colSpan = 10;
        emptyUsersCell.className = 'text-center text-secondary py-4';
        emptyUsersCell.textContent = 'No accounts found for this role.';
        emptyUsersRow.classList.add('d-none');
        emptyUsersRow.append(emptyUsersCell);
        usersTableBody.append(emptyUsersRow);

        function updateUserList() {
            const rows = Array.from(usersTableBody.querySelectorAll('tr[data-user-id]'));
            const counts = { all: rows.length, '1': 0, '2': 0 };

            rows.forEach(row => {
                const role = row.dataset.role;
                if (Object.hasOwn(counts, role)) counts[role] += 1;
                row.classList.toggle('d-none', selectedRole !== 'all' && role !== selectedRole);
            });

            document.getElementById('allAccountCount').textContent = counts.all;
            document.getElementById('adminAccountCount').textContent = counts['2'];
            document.getElementById('customerAccountCount').textContent = counts['1'];
            emptyUsersRow.classList.toggle('d-none', rows.some(row => !row.classList.contains('d-none')));
        }

        roleFilters.forEach(button => {
            button.addEventListener('click', function () {
                selectedRole = this.dataset.roleFilter;
                roleFilters.forEach(filter => {
                    const isActive = filter === this;
                    filter.classList.toggle('active', isActive);
                    filter.setAttribute('aria-pressed', String(isActive));
                });
                updateUserList();
            });
        });

        usersTableBody.addEventListener('change', function (event) {
            const roleSelect = event.target.closest('.role-select');
            if (!roleSelect) return;

            const row = roleSelect.closest('tr[data-user-id]');
            if (!row || !roleNames[roleSelect.value]) return;

            row.dataset.role = roleSelect.value;
            updateUserList();
        });

        usersTableBody.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.delete-user-btn');
            if (!deleteButton || deleteButton.disabled) return;

            deleteButton.closest('tr[data-user-id]')?.remove();
            updateUserList();
        });

        createAccountForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const formData = new FormData(createAccountForm);
            const usernameInput = createAccountForm.elements.username;
            const username = String(formData.get('username')).trim();
            const errorMessage = document.getElementById('createAccountError');
            const submitButton = document.getElementById('createAccountSubmit');
            const usernameExists = Array.from(usersTableBody.querySelectorAll('tr[data-user-id]'))
                .some(row => row.children[1].textContent.trim().toLocaleLowerCase() === username.toLocaleLowerCase());

            usernameInput.setCustomValidity(usernameExists ? 'This username is already in use.' : '');
            errorMessage.textContent = '';
            if (!createAccountForm.reportValidity()) return;

            if (formData.get('password') !== formData.get('confirm_password')) {
                createAccountForm.elements.confirm_password.setCustomValidity('Passwords do not match.');
                createAccountForm.reportValidity();
                return;
            }

            submitButton.disabled = true;
            try {
                const csrfResponse = await fetch('admin-create-account.php', { cache: 'no-store' });
                const csrfResult = await csrfResponse.json();
                if (!csrfResponse.ok || !csrfResult.csrf_token) {
                    throw new Error(csrfResult.message || 'Unable to verify your administrator session.');
                }

                const response = await fetch('admin-create-account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfResult.csrf_token
                    },
                    body: JSON.stringify(Object.fromEntries(formData.entries()))
                });
                const result = await response.json();
                if (!response.ok) {
                    if (result.field === 'username') {
                        usernameInput.setCustomValidity(result.message);
                        usernameInput.reportValidity();
                    } else {
                        errorMessage.textContent = result.message || 'Unable to create the account.';
                    }
                    return;
                }

                const role = String(result.account.role);
                const row = document.createElement('tr');
                row.dataset.userId = String(result.account.id || nextUserId++);
                row.dataset.role = role;

                const values = [
                    [result.account.first_name, result.account.middle_name, result.account.last_name].filter(Boolean).join(' '),
                    result.account.username,
                    result.account.phone,
                    result.account.street,
                    result.account.barangay,
                    result.account.city,
                    result.account.province,
                    result.account.postal
                ];

                values.forEach((value, index) => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    if (index === 0) cell.classList.add('ps-4', 'fw-semibold');
                    row.append(cell);
                });

                const roleCell = document.createElement('td');
                const roleSelect = document.createElement('select');
                roleSelect.className = 'form-select form-select-sm shadow-none role-select';
                roleSelect.dataset.userId = row.dataset.userId;
                Object.entries(roleNames).forEach(([value, name]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = name;
                    option.selected = value === role;
                    roleSelect.append(option);
                });
                roleCell.append(roleSelect);
                row.append(roleCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'text-end pe-4';
                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'btn btn-sm btn-outline-danger delete-user-btn';
                deleteButton.dataset.userId = row.dataset.userId;
                deleteButton.setAttribute('aria-label', `Delete ${values[0]}`);
                const deleteIcon = document.createElement('i');
                deleteIcon.className = 'bi bi-trash';
                deleteIcon.setAttribute('aria-hidden', 'true');
                deleteButton.append(deleteIcon);
                actionsCell.append(deleteButton);
                row.append(actionsCell);

                usersTableBody.insertBefore(row, emptyUsersRow);
                createAccountForm.reset();
                usernameInput.setCustomValidity('');
                createAccountForm.elements.confirm_password.setCustomValidity('');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('createAccountModal')).hide();
                updateUserList();
            } catch (error) {
                errorMessage.textContent = error.message || 'Account creation failed. Check your connection and try again.';
            } finally {
                submitButton.disabled = false;
            }
        });

        createAccountForm.elements.username.addEventListener('input', function () {
            this.setCustomValidity('');
        });
        createAccountForm.elements.confirm_password.addEventListener('input', function () {
            this.setCustomValidity('');
        });
        createAccountForm.elements.password.addEventListener('input', function () {
            createAccountForm.elements.confirm_password.setCustomValidity('');
        });

        updateUserList();
    }

    const recentOrdersBody = document.getElementById('recentOrdersTableBody');
    const recentOrdersSearch = document.getElementById('recentOrdersSearch');
    const recentOrdersStatus = document.getElementById('recentOrdersStatus');

    if (recentOrdersBody && recentOrdersSearch && recentOrdersStatus) {
        const emptyState = document.getElementById('recentOrdersEmptyState');
        const resultCount = document.getElementById('recentOrdersCount');

        function filterRecentOrders() {
            const orderRows = Array.from(recentOrdersBody.querySelectorAll('tr[data-order-id]'));
            const searchTerm = recentOrdersSearch.value.trim().toLocaleLowerCase();
            const selectedStatus = recentOrdersStatus.value;
            let visibleCount = 0;

            orderRows.forEach(row => {
                const matchesSearch = row.textContent.toLocaleLowerCase().includes(searchTerm);
                const status = row.querySelector('td:nth-child(4)')?.textContent.trim().toLocaleLowerCase();
                const matchesStatus = !selectedStatus || status === selectedStatus;
                const isVisible = matchesSearch && matchesStatus;

                row.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            if (emptyState) emptyState.classList.toggle('d-none', visibleCount > 0);
            if (resultCount) resultCount.textContent = `Showing ${visibleCount} of ${orderRows.length} orders`;
        }

        recentOrdersSearch.addEventListener('input', filterRecentOrders);
        recentOrdersStatus.addEventListener('change', filterRecentOrders);
        filterRecentOrders();
    }

    // ==========================================
    // 1. MANAGE PRODUCTS MODAL (Add & Edit)
    // ==========================================
    const productModalElement = document.getElementById('productModal');
    if (productModalElement) {
        const productModal = new bootstrap.Modal(productModalElement);
        const archiveModalElement = document.getElementById('archiveProductModal');
        const archiveModal = archiveModalElement ? bootstrap.Modal.getOrCreateInstance(archiveModalElement) : null;
        const archiveProductName = document.getElementById('archiveProductName');
        const confirmArchiveProductBtn = document.getElementById('confirmArchiveProductBtn');
        const modalTitle = document.getElementById('productModalLabel');
        const saveProductBtn = document.getElementById('saveProductBtn');
        const productForm = document.getElementById('productForm');
        const productsTableBody = document.getElementById('productsTableBody');
        const productCategoryFilters = Array.from(document.querySelectorAll('#adminProductCategoryFilter [data-product-category]'));
        const stockInputs = document.getElementById('variantStockInputs');
        const productsById = new Map();
        let editingProductId = null;
        let adminCsrfToken = '';
        let selectedProductCategory = 'all';
        let selectedProductStatus = 'active';
        let pendingArchiveProduct = null;
        let productEmptyState;
        const escapeValue = value => String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
        const selectedVariants = new Map();

        const filterProducts = () => {
            const rows = Array.from(productsTableBody.querySelectorAll('tr[data-product-id]'));
            let visibleCount = 0;
            rows.forEach(row => {
                const visible = row.dataset.productActive === String(selectedProductStatus === 'active') &&
                    (selectedProductCategory === 'all' || row.dataset.productCategory === selectedProductCategory);
                row.classList.toggle('d-none', !visible);
                if (visible) visibleCount += 1;
            });
            if (productEmptyState) {
                productEmptyState.classList.toggle('d-none', visibleCount > 0);
                productEmptyState.querySelector('td').textContent = rows.length === 0
                    ? 'No products in the catalog.'
                    : selectedProductStatus === 'archived'
                        ? 'No archived products.'
                        : selectedProductCategory === 'all'
                            ? 'No active products.'
                            : 'No active products in this category.';
            }
        };
        productCategoryFilters.forEach(button => button.addEventListener('click', () => {
            selectedProductCategory = button.dataset.productCategory || 'all';
            selectedProductStatus = button.dataset.productStatus || 'active';
            productCategoryFilters.forEach(filter => {
                const selected = filter === button;
                filter.classList.toggle('active', selected);
                filter.setAttribute('aria-pressed', String(selected));
            });
            filterProducts();
        }));

        const syncStockInputs = () => {
            const selectedSizes = Array.from(document.querySelectorAll('.product-size-checkbox:checked'))
                .map(checkbox => checkbox.value);
            Array.from(selectedVariants.keys()).forEach(size => {
                if (!selectedSizes.includes(size)) selectedVariants.delete(size);
            });
            selectedSizes.forEach(size => {
                if (!selectedVariants.has(size)) selectedVariants.set(size, 0);
            });
            stockInputs.replaceChildren(...selectedSizes.map(size => {
                const wrapper = document.createElement('div');
                wrapper.className = 'row align-items-center g-2';
                const label = document.createElement('label');
                label.className = 'col-4 small fw-semibold text-secondary';
                label.textContent = `${size} stock`;
                const input = document.createElement('input');
                input.type = 'number';
                input.className = 'form-control form-control-sm col variant-stock-input';
                input.min = '0';
                input.max = '999999';
                input.step = '1';
                input.required = true;
                input.value = String(selectedVariants.get(size));
                input.dataset.size = size;
                input.setAttribute('aria-label', `${size} stock quantity`);
                input.addEventListener('input', () => selectedVariants.set(size, input.value));
                wrapper.append(label, input);
                return wrapper;
            }));
        };
        document.querySelectorAll('.product-size-checkbox').forEach(checkbox =>
            checkbox.addEventListener('change', syncStockInputs));

        const inventoryLabel = product => product.variants.length
            ? product.variants.map(variant => `<span class="product-stock-badge ${variant.stock > 0 ? 'is-available' : 'is-empty'}" data-size-stock="${escapeValue(variant.size)}">
                <strong>${escapeValue(variant.size)}</strong>
                <span>${variant.stock > 0 ? `${variant.stock} in stock` : 'Out of stock'}</span>
            </span>`).join('')
            : '<span class="product-stock-empty">No sizes configured</span>';
        const renderProductRow = product => {
            const row = document.createElement('tr');
            row.dataset.productId = String(product.id);
            row.dataset.productCategory = product.category_slug;
            row.dataset.productActive = String(product.active);
            if (!product.active) row.classList.add('table-secondary');
            const totalStock = product.variants.reduce((total, variant) => total + variant.stock, 0);
            row.innerHTML = `<td class="ps-4"><div class="d-flex align-items-center gap-3"><img src="${escapeValue(product.images.front || 'assets/placeholder.jpg')}" alt="${escapeValue(product.name)} front view" class="rounded object-fit-cover shadow-sm" width="45" height="45"><div><div class="fw-semibold text-dark">${escapeValue(product.name)}</div><small class="text-muted">${escapeValue(product.description || '')}</small></div></div></td>
                <td class="text-secondary">${escapeValue(product.id)}</td>
                <td><span class="badge bg-secondary bg-opacity-10 text-dark px-2 py-1">${escapeValue(product.category)}</span><div><small class="${product.active ? 'text-success' : 'text-danger'}">${product.active ? 'Active' : 'Archived'}</small></div></td>
                <td class="fw-semibold" style="color:var(--contrast-primary,#2563EB)">₱${Number(product.price).toFixed(2)}</td>
                <td><div class="product-size-stock-list">${inventoryLabel(product)}</div><div class="product-stock-total">${totalStock} total</div></td>
                <td class="text-end pe-4"><div class="d-flex justify-content-end gap-1">${product.active
                    ? `<button type="button" class="btn btn-sm btn-outline-dark edit-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Edit ${escapeValue(product.name)}"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-sm btn-outline-danger delete-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Archive ${escapeValue(product.name)}"><i class="bi bi-archive"></i></button>`
                    : `<button type="button" class="btn btn-sm btn-outline-dark edit-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Editing disabled for archived ${escapeValue(product.name)}" disabled><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-sm btn-outline-success restore-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Unarchive ${escapeValue(product.name)}"><i class="bi bi-arrow-counterclockwise"></i><span class="ms-1">Unarchive</span></button>`}</div></td>`;
            return row;
        };
        const loadProducts = async () => {
            productsTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">Loading products…</td></tr>';
            const response = await fetch('admin-products-api.php', { cache: 'no-store' });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load products.');
            adminCsrfToken = result.csrf_token;
            productsById.clear();
            result.products.forEach(product => productsById.set(String(product.id), product));
            const rows = result.products.map(renderProductRow);
            productEmptyState = document.createElement('tr');
            productEmptyState.classList.add('d-none');
            productEmptyState.innerHTML = '<td colspan="6" class="text-center text-secondary py-4"></td>';
            productsTableBody.replaceChildren(...rows, productEmptyState);
            filterProducts();
        };
        const resetProductForm = () => {
            productForm.reset();
            selectedVariants.clear();
            document.querySelectorAll('.product-size-checkbox').forEach(checkbox => {
                checkbox.checked = checkbox.value === 'M' || checkbox.value === 'L';
                if (checkbox.checked) selectedVariants.set(checkbox.value, 0);
            });
            syncStockInputs();
            document.getElementById('productFrontImageInput').required = !editingProductId;
        };

        const openAddBtn = document.getElementById('openAddProductModalBtn');
        if (openAddBtn) {
            openAddBtn.addEventListener('click', function () {
                editingProductId = null;
                modalTitle.textContent = 'Add New Product';
                resetProductForm();
                saveProductBtn.textContent = 'Save Product';
            });
        }

        productsTableBody.addEventListener('click', event => {
            const button = event.target.closest('.edit-product-btn');
            if (!button) return;
            const product = productsById.get(button.dataset.productId);
            if (!product) return;
            editingProductId = String(product.id);
            modalTitle.textContent = 'Edit Product';
            saveProductBtn.textContent = 'Update Product';
            resetProductForm();
            document.getElementById('productNameInput').value = product.name;
            document.getElementById('productDescriptionInput').value = product.description || '';
            document.getElementById('productCategoryInput').value = product.category_slug;
            document.getElementById('productPriceInput').value = Number(product.price).toFixed(2);
            document.getElementById('productFrontImageInput').required = false;
            document.querySelectorAll('.product-size-checkbox').forEach(checkbox => {
                const variant = product.variants.find(item => item.size === checkbox.value);
                checkbox.checked = Boolean(variant);
                if (variant) selectedVariants.set(variant.size, variant.stock);
            });
            syncStockInputs();
            productModal.show();
        });

        saveProductBtn.addEventListener('click', async () => {
            if (!productForm.reportValidity()) return;
            const variants = Array.from(document.querySelectorAll('.variant-stock-input')).map(input => ({
                size: input.dataset.size,
                stock: input.value
            }));
            if (!variants.length) {
                window.alert('Select at least one size and set its stock quantity.');
                return;
            }
            const imageFields = [
                ['image_front', 'productFrontImageInput'],
                ['image_side', 'productThreeQuarterImageInput'],
                ['image_back', 'productBackImageInput']
            ];
            const selectedImages = imageFields
                .map(([field, id]) => ({ field, file: document.getElementById(id).files[0] }))
                .filter(image => image.file);
            const maxImageSize = 5 * 1024 * 1024;
            if (selectedImages.some(image => image.file.size > maxImageSize)) {
                window.alert('Each product image must be no larger than 5 MB.');
                return;
            }
            if (selectedImages.reduce((total, image) => total + image.file.size, 0) > 15 * 1024 * 1024) {
                window.alert('Product images must be no larger than 15 MB combined.');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('product', JSON.stringify({
                id: editingProductId,
                name: document.getElementById('productNameInput').value.trim(),
                description: document.getElementById('productDescriptionInput').value.trim(),
                category: document.getElementById('productCategoryInput').value,
                price: document.getElementById('productPriceInput').value,
                variants
            }));
            selectedImages.forEach(({ field, file }) => formData.append(field, file));
            saveProductBtn.disabled = true;
            try {
                const response = await fetch('admin-products-api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': adminCsrfToken },
                    body: formData
                });
                const responseBody = await response.text();
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch {
                    const message = response.status === 413
                        ? 'Product images must be no larger than 5 MB each (15 MB combined).'
                        : 'The server could not process the product request. Reduce the image sizes and try again.';
                    throw new Error(message);
                }
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save product.');
                await loadProducts();
                productModal.hide();
                editingProductId = null;
                productForm.reset();
            } catch (error) {
                window.alert(error.message || 'Unable to save product.');
            } finally {
                saveProductBtn.disabled = false;
            };
        });

        productsTableBody.addEventListener('click', event => {
            const button = event.target.closest('.delete-product-btn');
            if (!button || button.disabled) return;
            const product = productsById.get(button.dataset.productId);
            if (!product || !archiveModal || !archiveProductName) return;
            pendingArchiveProduct = { product, button };
            archiveProductName.textContent = product.name;
            archiveModal.show();
        });

        productsTableBody.addEventListener('click', async event => {
            const button = event.target.closest('.restore-product-btn');
            if (!button || button.disabled) return;
            const product = productsById.get(button.dataset.productId);
            if (!product || product.active) return;
            button.disabled = true;
            const formData = new FormData();
            formData.append('action', 'restore');
            formData.append('product_id', String(product.id));
            try {
                const response = await fetch('admin-products-api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': adminCsrfToken },
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to unarchive product.');
                await loadProducts();
            } catch (error) {
                window.alert(error.message || 'Unable to unarchive product.');
                button.disabled = false;
            }
        });

        archiveModalElement?.addEventListener('hidden.bs.modal', () => {
            pendingArchiveProduct = null;
            archiveProductName.textContent = '';
            confirmArchiveProductBtn.disabled = false;
        });

        confirmArchiveProductBtn?.addEventListener('click', async () => {
            if (!pendingArchiveProduct || confirmArchiveProductBtn.disabled) return;
            const { product, button } = pendingArchiveProduct;
            confirmArchiveProductBtn.disabled = true;
            button.disabled = true;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('product_id', String(product.id));
            try {
                const response = await fetch('admin-products-api.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': adminCsrfToken },
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to archive product.');
                await loadProducts();
                archiveModal.hide();
            } catch (error) {
                window.alert(error.message || 'Unable to archive product.');
                button.disabled = false;
                confirmArchiveProductBtn.disabled = false;
            }
        });

        loadProducts().catch(error => {
            productsTableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeValue(error.message || 'Unable to load products.')}</td></tr>`;
        });
    }

    // ==========================================
    // 3. ADMIN ORDER DETAILS REVIEW MODAL
    // ==========================================
    const adminModalElement = document.getElementById('adminOrderDetailsModal');
    if (adminModalElement) {
        const adminOrderModal = new bootstrap.Modal(adminModalElement);
        const statusErrorModalElement = document.getElementById('adminOrderStatusErrorModal');
        const statusErrorModal = statusErrorModalElement
            ? bootstrap.Modal.getOrCreateInstance(statusErrorModalElement)
            : null;
        const currency = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2
        });
        const money = amount => currency.format(Number(amount) || 0);
        const ordersTableBody = document.getElementById('adminOrdersTableBody');
        const orderFilters = Array.from(document.querySelectorAll('#adminOrderFilterTab button'));
        let selectedOrderStatus = 'all';
        const orderSearch = document.getElementById('adminOrderSearch');
        const getOrderStatus = row => row.querySelector('td:nth-child(6) .badge')?.textContent.trim() || '';
        const filterOrders = () => {
            const searchTerm = orderSearch ? orderSearch.value.trim().toLocaleLowerCase() : '';
            ordersTableBody?.querySelectorAll('tr[data-order-id]').forEach(row => {
                const matchesStatus = selectedOrderStatus === 'all'
                    || getOrderStatus(row).toLocaleLowerCase() === selectedOrderStatus;
                const matchesSearch = row.textContent.toLocaleLowerCase().includes(searchTerm);
                row.classList.toggle('d-none', !matchesStatus || !matchesSearch);
            });
        };
        if (orderSearch) {
            orderSearch.value = new URLSearchParams(window.location.search).get('search') || '';
            orderSearch.addEventListener('input', filterOrders);
        }
        orderFilters.forEach(button => button.addEventListener('click', () => {
            selectedOrderStatus = button.id.replace('admin-', '').replace('-tab', '');
            orderFilters.forEach(filter => filter.classList.toggle('active', filter === button));
            filterOrders();
        }));

        ordersTableBody?.addEventListener('click', async event => {
            const updateButton = event.target.closest('.update-status-btn');
            if (updateButton) {
                event.preventDefault();
                const row = updateButton.closest('tr');
                const badge = row?.querySelector('td:nth-child(6) .badge');
                const status = updateButton.dataset.status;
                if (!badge || !status) return;
                if (row.dataset.databaseOrder === 'true') {
                    updateButton.classList.add('disabled');
                    try {
                        const response = await fetch('admin-orders-api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': window.dciVaultAdminCsrf || ''
                            },
                            body: JSON.stringify({ order_id: row.dataset.orderId, status })
                        });
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.message || 'Status update failed.');
                    } catch (error) {
                        const message = error.message || 'Unable to update the order status.';
                        const errorMessage = document.getElementById('adminOrderStatusErrorMessage');
                        if (statusErrorModal && errorMessage) {
                            errorMessage.textContent = message;
                            statusErrorModal.show();
                        } else {
                            console.error(message);
                        }
                        updateButton.classList.remove('disabled');
                        return;
                    }
                    updateButton.classList.remove('disabled');
                }
                badge.textContent = status;
                badge.className = `badge ${status === 'Completed'
                    ? 'bg-success bg-opacity-10 text-success border-success'
                : status === 'Cancelled'
                    ? 'bg-danger bg-opacity-10 text-danger border-danger'
                    : 'bg-primary bg-opacity-10 text-primary border-primary'} border border-opacity-25 px-3 py-1 rounded-pill`;
                row.dataset.status = status.toLowerCase();
                filterOrders();
                return;
            }
            const button = event.target.closest('.view-order-details-btn');
            if (!button) return;
            const row = button.closest('tr');
            if (!row) return;
            const cells = row.querySelectorAll('td');
            const status = getOrderStatus(row);
            const datePlaced = cells[2]?.textContent.trim() || 'Not provided';
            const items = button.dataset.items ? JSON.parse(button.dataset.items) : [];
            const itemSubtotal = items.reduce((sum, item) =>
                sum + Number(item.price || 0) * Number(item.quantity || 0), 0);
            const subtotal = Number(button.dataset.subtotal) || itemSubtotal;
            const vatFromItems = items.reduce((sum, item) => {
                const unitVat = Math.round((Number(item.price || 0) * 12 / 112 + Number.EPSILON) * 100) / 100;
                return sum + unitVat * Number(item.quantity || 0);
            }, 0);
            const vat = Number(button.dataset.vat) || vatFromItems;
            const shipping = button.dataset.shipping === undefined ? 30 : Number(button.dataset.shipping);
            const total = button.dataset.total === undefined ? subtotal + shipping : Number(button.dataset.total);
            document.getElementById('adminModalOrderId').textContent = cells[0].textContent.trim();
            document.getElementById('adminModalCustomerName').textContent = button.dataset.customerName || cells[1].textContent.trim();
            document.getElementById('adminModalCustomerContact').textContent = button.dataset.customerContact || 'Not provided';
            document.getElementById('adminModalPayment').textContent = cells[4].textContent.trim();
            document.getElementById('adminModalDatePlaced').textContent = datePlaced;
            document.getElementById('adminModalSubtotal').textContent = money(subtotal);
            document.getElementById('adminModalVat').textContent = money(vat);
            document.getElementById('adminModalShipping').textContent = money(shipping);
            document.getElementById('adminModalTotal').textContent = money(total);
            const itemsTable = document.getElementById('adminModalItemsTable');
            itemsTable.replaceChildren();
            items.forEach(item => {
                const itemRow = document.createElement('tr');
                const nameCell = document.createElement('td');
                nameCell.className = 'fw-semibold text-dark';
                nameCell.textContent = item.name || 'Product';
                const sizeCell = document.createElement('td');
                sizeCell.textContent = item.size || 'One Size';
                const priceCell = document.createElement('td');
                priceCell.textContent = money(item.price);
                const quantityCell = document.createElement('td');
                quantityCell.textContent = String(Number(item.quantity) || 0);
                const subtotalCell = document.createElement('td');
                subtotalCell.className = 'text-end fw-semibold text-dark';
                subtotalCell.textContent = money(Number(item.price || 0) * Number(item.quantity || 0));
                itemRow.append(nameCell, sizeCell, priceCell, quantityCell, subtotalCell);
                itemsTable.append(itemRow);
            });
            const statusBadge = document.getElementById('adminModalStatusBadge');
            statusBadge.textContent = status;
            statusBadge.className = `badge ${status.toLowerCase() === 'completed'
                ? 'bg-success text-white' : status.toLowerCase() === 'cancelled'
                    ? 'bg-danger text-white' : 'bg-warning text-dark'}`;
            adminOrderModal.show();
        });
    }

});

document.addEventListener('DOMContentLoaded', async () => {
    const orderTable = document.getElementById('adminOrdersTableBody');
    const recentTable = document.getElementById('recentOrdersTableBody');
    if (!orderTable && !recentTable) return;
    try {
        const response = await fetch('admin-orders-api.php', { cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load orders.');
        window.dciVaultAdminCsrf = result.csrf_token;
        const orders = result.orders || [];
        const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
        const statusClass = status => status === 'Completed'
            ? 'bg-success bg-opacity-10 text-success border-success'
            : status === 'Cancelled' ? 'bg-danger bg-opacity-10 text-danger border-danger'
                : 'bg-primary bg-opacity-10 text-primary border-primary';
        const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
        const makeOrdersRow = order => {
            const row = document.createElement('tr');
            row.dataset.orderId = String(order.order_id);
            row.dataset.databaseOrder = 'true';
            const paymentNames = { gcash: 'GCash', card: 'Credit / Debit Card', cod: 'Cash on Delivery / Pickup' };
            const items = (order.items || []).map(item => ({ name: item.name, size: item.size, price: item.price, quantity: item.quantity }));
            row.innerHTML = `<td class="ps-4 fw-semibold text-dark">#${escapeHtml(order.order_number)}</td>
                <td class="text-dark">${escapeHtml(order.customer_name)}<small class="d-block text-secondary">@${escapeHtml(order.customer_username)}</small></td>
                <td class="text-secondary">${escapeHtml(order.placed_at)}</td>
                <td class="fw-semibold">${escapeHtml(money(order.total_amount))}</td>
                <td class="text-secondary">${escapeHtml(paymentNames[order.payment_method] || order.payment_method)}</td>
                <td><span class="badge ${statusClass(order.status)} border border-opacity-25 px-3 py-1 rounded-pill">${escapeHtml(order.status)}</span></td>
                <td class="text-end pe-4"><div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-outline-dark view-order-details-btn px-3 fw-semibold" data-customer-name="${escapeHtml(order.customer_name)}" data-customer-contact="${escapeHtml(order.shipping_contact)}" data-subtotal="${escapeHtml(order.subtotal)}" data-vat="${escapeHtml(order.vat_amount)}" data-shipping="${escapeHtml(order.shipping_fee)}" data-total="${escapeHtml(order.total_amount)}" data-items="${escapeHtml(JSON.stringify(items))}"><i class="bi bi-eye"></i> View</button>
                    <div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle fw-semibold px-3" type="button" data-bs-toggle="dropdown">Update</button>
                        <ul class="dropdown-menu dropdown-menu-end">${order.status === 'Cancelled' ? '' : '<li><a class="dropdown-item update-status-btn" href="#" data-status="Processing">Mark Processing</a></li>'}<li><a class="dropdown-item update-status-btn" href="#" data-status="Completed">Mark Completed</a></li><li><a class="dropdown-item update-status-btn" href="#" data-status="Cancelled">Cancel order</a></li></ul></div>
                </div></td>`;
            return row;
        };
        if (orderTable) {
            orderTable.replaceChildren(...orders.map(makeOrdersRow));
            if (!orders.length) orderTable.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">No orders recorded yet.</td></tr>';
        }
        if (recentTable) {
            const recent = orders.slice(0, 5);
            recentTable.replaceChildren(...recent.map(order => {
                const row = document.createElement('tr');
                row.dataset.orderId = String(order.order_id);
                row.innerHTML = `<td class="ps-4 fw-semibold text-dark">#${escapeHtml(order.order_number)}</td><td class="text-dark">${escapeHtml(order.customer_name)}<small class="d-block text-secondary">@${escapeHtml(order.customer_username)}</small></td><td class="fw-semibold">${escapeHtml(money(order.total_amount))}</td><td><span class="badge ${statusClass(order.status)} border border-opacity-25 px-3 py-1 rounded-pill">${escapeHtml(order.status)}</span></td><td class="text-end pe-4"><a href="admin-order.php?search=${encodeURIComponent(order.customer_username)}" class="btn btn-sm btn-outline-dark fw-semibold px-3">Review</a></td>`;
                return row;
            }));
            if (!recent.length) recentTable.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-4">No recent orders recorded yet.</td></tr>';
        }
        document.getElementById('recentOrdersSearch')?.dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('adminOrderSearch')?.dispatchEvent(new Event('input', { bubbles: true }));
    } catch (error) {
        console.error('Unable to load orders from the database.', error);
        const message = 'Orders could not be loaded from the database.';
        if (orderTable) orderTable.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${message}</td></tr>`;
        if (recentTable) recentTable.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${message}</td></tr>`;
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    const totalSales = document.getElementById('metricTotalSales');
    if (!totalSales) return;
    try {
        const response = await fetch('admin-dashboard-api.php', { cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load dashboard metrics.');
        const metrics = result.metrics;
        totalSales.textContent = new Intl.NumberFormat('en-PH', {
            style: 'currency', currency: 'PHP', minimumFractionDigits: 2
        }).format(metrics.sales);
        document.getElementById('metricTotalOrders').textContent = Number(metrics.orders).toLocaleString('en-PH');
        document.getElementById('metricOrdersCaption').textContent =
            `${Number(metrics.processing_orders).toLocaleString('en-PH')} processing`;
        document.getElementById('metricTotalProducts').textContent = Number(metrics.products).toLocaleString('en-PH');
        document.getElementById('metricTotalUsers').textContent = Number(metrics.users).toLocaleString('en-PH');
        document.getElementById('metricUsersCaption').textContent =
            `${Number(metrics.admins).toLocaleString('en-PH')} administrators · ${Number(metrics.customers).toLocaleString('en-PH')} customers`;
    } catch (error) {
        console.error('Unable to load dashboard metrics.', error);
        totalSales.textContent = '—';
        document.getElementById('metricTotalOrders').textContent = '—';
        document.getElementById('metricTotalProducts').textContent = '—';
        document.getElementById('metricTotalUsers').textContent = '—';
        document.getElementById('metricSalesCaption').textContent = 'Metrics could not be loaded.';
    }
});
