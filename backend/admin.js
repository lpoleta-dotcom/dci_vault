document.addEventListener('DOMContentLoaded', function () {
    const productImageUrls = new Map();
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
                const response = await fetch('admin-create-account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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
                errorMessage.textContent = 'Account creation failed. Check your connection and try again.';
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
        const orderRows = Array.from(recentOrdersBody.querySelectorAll('tr:not(#recentOrdersEmptyState)'));
        const emptyState = document.getElementById('recentOrdersEmptyState');
        const resultCount = document.getElementById('recentOrdersCount');

        function filterRecentOrders() {
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
        const modalTitle = document.getElementById('productModalLabel');
        const saveProductBtn = document.getElementById('saveProductBtn');
        const productForm = document.getElementById('productForm');
        const productsTableBody = document.getElementById('productsTableBody');
        let editingProductId = null;
        let nextProductId = Math.max(0, ...Array.from(productsTableBody.querySelectorAll('tr[data-product-id]'))
            .map(row => Number(row.dataset.productId) || 0)) + 1;
        const escapeValue = value => String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);

        const openAddBtn = document.getElementById('openAddProductModalBtn');
        if (openAddBtn) {
            openAddBtn.addEventListener('click', function () {
                editingProductId = null;
                modalTitle.textContent = 'Add New Product';
                productForm.reset();
                saveProductBtn.textContent = 'Save Product';
                document.querySelectorAll('.product-size-checkbox').forEach(cb => {
                    cb.checked = (cb.value === 'M' || cb.value === 'L');
                });
            });
        }

        productsTableBody.addEventListener('click', event => {
            const button = event.target.closest('.edit-product-btn');
            if (!button) return;
            const row = button.closest('tr[data-product-id]');
            if (!row) return;
            editingProductId = row.dataset.productId;
            const cells = row.querySelectorAll('td');
            const sizeText = cells[0].querySelector('small')?.textContent || '';
            const sizesMatch = sizeText.match(/Size:\s*([^|]+)/i);
            const sizes = (sizesMatch?.[1] || sizeText.replace(/^Sizes:\s*/i, '')).split(',').map(size => size.trim());
            modalTitle.textContent = 'Edit Product';
            saveProductBtn.textContent = 'Update Product';
            document.getElementById('productNameInput').value = cells[0].querySelector('.fw-semibold')?.textContent.trim() || '';
            document.getElementById('productCategoryInput').value = cells[2].textContent.trim();
            document.getElementById('productPriceInput').value = cells[3].textContent.replace(/[^\d.]/g, '');
            document.querySelectorAll('.product-size-checkbox').forEach(checkbox => {
                checkbox.checked = sizes.includes(checkbox.value);
            });
            productModal.show();
        });

        const renderProductRow = (product, row) => {
            const target = row || document.createElement('tr');
            target.dataset.productId = String(product.id);
            target.innerHTML = `<td class="ps-4"><div class="d-flex align-items-center gap-3"><img src="${escapeValue(product.images.front)}" alt="${escapeValue(product.name)} front view" class="rounded object-fit-cover shadow-sm" width="45" height="45"><div><div class="fw-semibold text-dark">${escapeValue(product.name)}</div><small class="text-muted">Sizes: ${escapeValue(product.sizes.join(', '))}</small></div></div></td>
                <td class="text-secondary">${escapeValue(product.id)}</td>
                <td><span class="badge bg-secondary bg-opacity-10 text-dark px-2 py-1">${escapeValue(product.category)}</span></td>
                <td class="fw-semibold" style="color:var(--contrast-primary,#2563EB)">₱${product.price.toFixed(2)}</td>
                <td class="text-end pe-4"><div class="d-flex justify-content-end gap-1"><button type="button" class="btn btn-sm btn-outline-dark edit-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Edit ${escapeValue(product.name)}"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-sm btn-outline-danger delete-product-btn px-2 py-1" data-product-id="${escapeValue(product.id)}" aria-label="Delete ${escapeValue(product.name)}"><i class="bi bi-trash"></i></button></div></td>`;
            if (!row) productsTableBody.append(target);
        };

        saveProductBtn.addEventListener('click', function () {
            const nameInput = document.getElementById('productNameInput');
            const priceInput = document.getElementById('productPriceInput');
            const price = Number(priceInput.value);
            const frontImage = document.getElementById('productFrontImageInput').files[0];
            const threeQuarterImage = document.getElementById('productThreeQuarterImageInput').files[0];
            const backImage = document.getElementById('productBackImageInput').files[0];
            const selectedSizes = Array.from(document.querySelectorAll('.product-size-checkbox:checked'))
                .map(checkbox => checkbox.value);
            if (!productForm.reportValidity()) return;
            if (!Number.isFinite(price) || price <= 0 || !selectedSizes.length) {
                alert('Enter a positive price and select at least one size.');
                return;
            }
            const productId = editingProductId || String(nextProductId++);
            productImageUrls.get(productId)?.forEach(URL.revokeObjectURL);
            const images = {
                front: URL.createObjectURL(frontImage),
                threeQuarter: URL.createObjectURL(threeQuarterImage),
                back: URL.createObjectURL(backImage)
            };
            const imageUrls = Object.values(images);
            productImageUrls.set(productId, imageUrls);
            const product = {
                id: productId,
                name: nameInput.value.trim(),
                category: document.getElementById('productCategoryInput').value,
                price,
                images,
                sizes: selectedSizes
            };
            const existingRow = editingProductId
                ? productsTableBody.querySelector(`tr[data-product-id="${CSS.escape(editingProductId)}"]`)
                : null;
            renderProductRow(product, existingRow);
            productModal.hide();
            productForm.reset();
            editingProductId = null;
        });
    }

    // ==========================================
    // 2. MANAGE DELETE CONFIRMATION MODAL
    // ==========================================
    const deleteModalElement = document.getElementById('deleteProductModal');
    if (deleteModalElement) {
        const deleteModal = new bootstrap.Modal(deleteModalElement);
        const productsTableBody = document.getElementById('productsTableBody');
        productsTableBody.addEventListener('click', function (event) {
            const button = event.target.closest('.delete-product-btn');
            if (!button) return;
            const productId = button.getAttribute('data-product-id');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            confirmDeleteBtn?.setAttribute('data-target-id', productId);
            const productName = button.closest('tr')?.querySelector('.fw-semibold')?.textContent.trim();
            const nameElement = document.getElementById('deleteProductName');
            if (nameElement) nameElement.textContent = productName || 'this product';
            deleteModal.show();
        });

        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function () {
                const productId = this.getAttribute('data-target-id');
                if (!productId) return;
                const row = productsTableBody.querySelector(`tr[data-product-id="${CSS.escape(productId)}"]`);
                if (row) row.remove();
                productImageUrls.get(productId)?.forEach(URL.revokeObjectURL);
                productImageUrls.delete(productId);
                deleteModal.hide();
                confirmDeleteBtn.removeAttribute('data-target-id');
            });
        }
    }

    // ==========================================
    // 3. ADMIN ORDER DETAILS REVIEW MODAL
    // ==========================================
    const adminModalElement = document.getElementById('adminOrderDetailsModal');
    if (adminModalElement) {
        const adminOrderModal = new bootstrap.Modal(adminModalElement);
        const currency = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2
        });
        const money = amount => currency.format(Number(amount) || 0);
        const ordersTableBody = document.getElementById('adminOrdersTableBody');
        const orderFilters = Array.from(document.querySelectorAll('#adminOrderFilterTab button'));
        let selectedOrderStatus = 'all';
        const getOrderStatus = row => row.querySelector('td:nth-child(6) .badge')?.textContent.trim() || '';
        const filterOrders = () => {
            ordersTableBody?.querySelectorAll('tr[data-order-id]').forEach(row => {
                row.classList.toggle('d-none', selectedOrderStatus !== 'all'
                    && getOrderStatus(row).toLocaleLowerCase() !== selectedOrderStatus);
            });
        };
        orderFilters.forEach(button => button.addEventListener('click', () => {
            selectedOrderStatus = button.id.replace('admin-', '').replace('-tab', '');
            orderFilters.forEach(filter => filter.classList.toggle('active', filter === button));
            filterOrders();
        }));

        ordersTableBody?.addEventListener('click', event => {
            const updateButton = event.target.closest('.update-status-btn');
            if (updateButton) {
                event.preventDefault();
                const row = updateButton.closest('tr');
                const badge = row?.querySelector('td:nth-child(6) .badge');
                const status = updateButton.dataset.status;
                if (!badge || !status) return;
                badge.textContent = status;
                badge.className = `badge ${status === 'Completed'
                    ? 'bg-success bg-opacity-10 text-success border-success'
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
            const vat = Math.round((subtotal + Number.EPSILON) * 12) / 100;
            const shipping = 30;
            document.getElementById('adminModalOrderId').textContent = cells[0].textContent.trim();
            document.getElementById('adminModalCustomerName').textContent = cells[1].textContent.trim();
            document.getElementById('adminModalCustomerContact').textContent = button.dataset.customerContact || 'Not provided';
            document.getElementById('adminModalPayment').textContent = cells[4].textContent.trim();
            document.getElementById('adminModalDatePlaced').textContent = datePlaced;
            document.getElementById('adminModalSubtotal').textContent = money(subtotal);
            document.getElementById('adminModalVat').textContent = money(vat);
            document.getElementById('adminModalShipping').textContent = money(shipping);
            document.getElementById('adminModalTotal').textContent = money(subtotal + vat + shipping);
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