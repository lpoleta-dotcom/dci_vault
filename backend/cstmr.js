document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });

    const keys = {
        cart: 'tindatrack.cart',
        orders: 'tindatrack.orders',
        profile: 'tindatrack.profile'
    };
    const currency = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    });
    const calculateVat = inclusivePrice => Math.round((Number(inclusivePrice) * 12 / 112 + Number.EPSILON) * 100) / 100;
    const calculateCartVat = items => items.reduce((sum, item) =>
        sum + calculateVat(item.price) * Number(item.quantity || 0), 0);
    const normalizeCart = items => items.map(item => {
        if (item.vatIncluded) return { ...item, price: Math.round((Number(item.price) / 1.12 + Number.EPSILON) * 100) / 100, vatIncluded: false };
        return { ...item, vatIncluded: false };
    });
    const read = key => {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    };
    const write = (key, value) => localStorage.setItem(key, JSON.stringify(value));
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
    const notify = message => {
        let region = document.getElementById('customerNotice');
        if (!region) {
            region = document.createElement('div');
            region.id = 'customerNotice';
            region.className = 'alert alert-info position-fixed bottom-0 end-0 m-3 shadow';
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            region.style.zIndex = '1090';
            document.body.append(region);
        }
        region.textContent = message;
        region.classList.remove('d-none');
        window.clearTimeout(region.hideTimer);
        region.hideTimer = window.setTimeout(() => region.classList.add('d-none'), 3500);
    };
    const money = amount => currency.format(Number(amount) || 0);

    function syncAvatar(profile) {
        const sessionManagedImages = document.querySelectorAll('#empAvatar[data-session-managed="true"], #accountAvatarPreview[data-session-managed="true"]');
        const avatar = profile?.avatar || 'assets/user.jpg';
        document.querySelectorAll('#empAvatar, #accountAvatarPreview').forEach(image => {
            if (image.dataset.sessionManaged !== 'true') image.src = avatar;
        });
        if (!sessionManagedImages.length) {
            document.getElementById('removeAvatarBtn')?.classList.toggle('d-none', !profile?.avatar);
        }
    }

    function syncProfile() {
        if (document.getElementById('accountForm')?.dataset.serverManaged === 'true') return;
        const profile = read(keys.profile);
        syncAvatar(profile);
        if (!profile) return;
        const fullName = [profile.firstName, profile.middleName, profile.lastName].filter(Boolean).join(' ');
        document.querySelectorAll('#userName').forEach(element => {
            if (element.dataset.sessionManaged !== 'true') {
                element.textContent = fullName || profile.username || 'Customer';
            }
        });
        const name = document.querySelector('.customer-profile-card h5');
        if (name) name.textContent = fullName || profile.username || 'Customer';
        const fields = {
            accountFn: 'firstName', accountMn: 'middleName', accountLn: 'lastName',
            accountUsername: 'username', accountContact: 'contact', accountStreet: 'street',
            accountBarangay: 'barangay', accountCity: 'city', accountProvince: 'province',
            accountZip: 'zip'
        };
        Object.entries(fields).forEach(([id, key]) => {
            const input = document.getElementById(id);
            if (input && profile[key] !== undefined) input.value = profile[key];
        });
    }

    function setupCatalog() {
        const grid = document.getElementById('productGrid');
        if (!grid) return;
        const filters = Array.from(document.querySelectorAll('#categoryFilter [data-category]'));
        const cards = Array.from(grid.querySelectorAll('.product-card'));
        cards.forEach(card => {
            const basePrice = Number(card.dataset.price || 0);
            const priceElement = card.querySelector('.productPrice');
            if (priceElement) priceElement.textContent = money(basePrice);
        });
        filters.forEach(button => button.addEventListener('click', () => {
            const category = button.dataset.category || 'all';
            filters.forEach(filter => {
                const active = filter === button;
                filter.classList.toggle('active', active);
                filter.setAttribute('aria-pressed', String(active));
            });
            cards.forEach(card => {
                const match = category === 'all' || card.dataset.category === category;
                const wrapper = card.closest('.col, [class*="col-"]') || card;
                wrapper.classList.toggle('d-none', !match);
            });
        }));

        const modalElement = document.getElementById('productModal');
        if (!modalElement || !window.bootstrap) return;
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const sizeSelect = document.getElementById('productModalSize');
        const quantityInput = document.getElementById('productModalQty');
        let selectedProduct = null;
        const openProduct = card => {
            selectedProduct = {
                id: card.dataset.productId || card.dataset.name,
                name: card.dataset.name || card.querySelector('.productName')?.textContent.trim() || 'Product',
                price: Number(card.dataset.price || card.querySelector('.productPrice')?.textContent.replace(/[^\d.]/g, '') || 0),
                vatIncluded: false,
                description: card.dataset.description || 'A quality DCI Vault favorite, designed for everyday use.',
                image: card.dataset.imgFront || card.querySelector('img')?.getAttribute('src') || 'assets/product.jpg',
                backImage: card.dataset.imgBack || card.dataset.imgFront || card.querySelector('img')?.getAttribute('src') || 'assets/product.jpg',
                sizes: (card.dataset.sizes || 'One Size').split(',').map(size => size.trim()).filter(Boolean)
            };
            document.getElementById('productModalName').textContent = selectedProduct.name;
            document.getElementById('productModalPrice').textContent = money(selectedProduct.price);
            const description = document.getElementById('productModalDescription');
            if (description) description.textContent = selectedProduct.description;
            document.getElementById('productModalImgFront').src = selectedProduct.image;
            document.getElementById('productModalImgBack').src = selectedProduct.backImage;
            sizeSelect.replaceChildren(...selectedProduct.sizes.map(size => {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = size;
                return option;
            }));
            quantityInput.value = '1';
            quantityInput.max = '99';
            modal.show();
        };
        cards.forEach(card => {
            card.addEventListener('click', event => {
                if (event.target.closest('button.add-to-cart-btn')) {
                    event.preventDefault();
                    openProduct(card);
                    return;
                }
                if (!event.target.closest('button, a')) openProduct(card);
            });
        });

        const addSelection = (goToCart = false) => {
            if (!selectedProduct) return;
            const quantity = Math.floor(Number(quantityInput.value));
            if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) {
                quantityInput.setCustomValidity('Choose a quantity between 1 and 99.');
                quantityInput.reportValidity();
                return;
            }
            quantityInput.setCustomValidity('');
            let cart = read(keys.cart);
            if (!Array.isArray(cart)) cart = [];
            cart = normalizeCart(cart);
            const size = sizeSelect.value || selectedProduct.sizes[0] || 'One Size';
            const existing = cart.find(item => String(item.id) === String(selectedProduct.id) && item.size === size);
            if (existing) existing.quantity += quantity;
            else cart.push({ ...selectedProduct, size, quantity });
            write(keys.cart, cart);
            modal.hide();
            notify(`${selectedProduct.name} added to your cart.`);
            if (goToCart) window.location.assign('cstmr-cart.php');
        };
        document.getElementById('productAddToCart')?.addEventListener('click', () => addSelection());
        document.getElementById('modalBuyNow')?.addEventListener('click', () => addSelection(true));
    }

    function getInitialCart() {
        const saved = read(keys.cart);
        if (Array.isArray(saved)) {
            const normalized = normalizeCart(saved);
            write(keys.cart, normalized);
            return normalized;
        }
        const rows = Array.from(document.querySelectorAll('#cartTableBody tr[data-product-id]'));
        const initial = rows.map(row => {
            const cells = row.querySelectorAll('td');
            const price = Number(row.dataset.basePrice || cells[2]?.textContent.replace(/[^\d.]/g, '') || 0);
            return {
                id: row.dataset.productId,
                name: cells[0]?.querySelector('.fw-semibold')?.textContent.trim() || 'Product',
                id: row.dataset.productId,
                size: cells[1]?.textContent.trim() || 'One Size',
                price,
                vatIncluded: false,
                quantity: Math.max(1, Number(cells[3]?.querySelector('input')?.value) || 1),
                image: 'assets/product.jpg'
            };
        });
        write(keys.cart, initial);
        return initial;
    }

    function setupCart() {
        const body = document.getElementById('cartTableBody');
        if (!body) return;
        let cart = getInitialCart();
            const shippingFee = 30;
        const render = () => {
            body.replaceChildren();
            cart.forEach((item, index) => {
                const row = document.createElement('tr');
                row.dataset.index = String(index);
                row.innerHTML = `<td class="ps-4"><div class="fw-semibold">${escapeHtml(item.name)}</div><small class="text-muted">Product ID: ${escapeHtml(item.id)}</small></td>
                    <td>${escapeHtml(item.size)}</td><td>${money(item.price)}</td>
                    <td><input type="number" class="form-control form-control-sm shadow-none item-qty" value="${Math.max(1, Number(item.quantity) || 1)}" min="1" max="99" aria-label="Quantity for ${escapeHtml(item.name)}" style="width:70px"></td>
                    <td class="fw-semibold item-subtotal">${money(item.price * item.quantity)}</td>
                    <td class="text-end pe-4"><button type="button" class="btn btn-sm text-danger remove-item-btn" aria-label="Remove ${escapeHtml(item.name)}"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>`;
                body.append(row);
            });
            if (!cart.length) {
                const row = document.createElement('tr');
                row.innerHTML = '<td class="text-center text-secondary py-5" colspan="6">Your cart is empty. Browse products to add something you love.</td>';
                body.append(row);
            }
            const subtotal = cart.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
            const vat = calculateCartVat(cart);
            document.getElementById('summarySubtotal').textContent = money(subtotal);
            document.getElementById('summaryVat').textContent = money(vat);
            document.getElementById('summaryShip').textContent = money(cart.length ? shippingFee : 0);
            document.getElementById('summaryTotal').textContent = money(subtotal + (cart.length ? shippingFee : 0));
            const emptyButton = document.getElementById('clearCartBtn');
            const orderButton = document.getElementById('placeOrderBtn');
            if (emptyButton) emptyButton.disabled = cart.length === 0;
            if (orderButton) orderButton.disabled = cart.length === 0;
        };
        const save = () => {
            write(keys.cart, cart);
            render();
        };
        body.addEventListener('change', event => {
            const input = event.target.closest('.item-qty');
            if (!input) return;
            const row = input.closest('tr');
            const item = cart[Number(row.dataset.index)];
            const quantity = Math.floor(Number(input.value));
            if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) {
                input.value = String(item.quantity);
                input.setCustomValidity('Choose a quantity between 1 and 99.');
                input.reportValidity();
                return;
            }
            input.setCustomValidity('');
            item.quantity = quantity;
            save();
        });
        body.addEventListener('input', event => {
            if (event.target.matches('.item-qty')) event.target.setCustomValidity('');
        });
        body.addEventListener('click', event => {
            const button = event.target.closest('.remove-item-btn');
            if (!button) return;
            cart.splice(Number(button.closest('tr').dataset.index), 1);
            save();
        });
        document.getElementById('clearCartBtn')?.addEventListener('click', () => {
            if (!cart.length || !window.confirm('Empty your cart?')) return;
            cart = [];
            save();
        });

        const placeOrderButton = document.getElementById('placeOrderBtn');
        const confirmation = document.getElementById('confirmOrderModal');
        if (placeOrderButton && confirmation && window.bootstrap) {
            const confirmationModal = bootstrap.Modal.getOrCreateInstance(confirmation);
            const title = document.getElementById('checkoutModalTitle');
            const review = document.getElementById('checkoutReview');
            const success = document.getElementById('checkoutSuccess');
            const reviewActions = document.getElementById('checkoutReviewActions');
            const successActions = document.getElementById('checkoutSuccessActions');
            const confirmButton = document.getElementById('confirmPlaceOrderBtn');
            let pendingOrder = null;

            placeOrderButton.addEventListener('click', () => {
                if (!cart.length) {
                    notify('Your cart is empty.');
                    return;
                }
                pendingOrder = null;
                title.textContent = 'Review your order';
                review.classList.remove('d-none');
                success.classList.add('d-none');
                reviewActions.classList.remove('d-none');
                successActions.classList.add('d-none');
                confirmButton.disabled = false;
                confirmButton.innerHTML = '<i class="bi bi-bag-check me-1"></i>Confirm &amp; Place Order';

                const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
                const vat = calculateCartVat(cart);
                const shipping = shippingFee;
                const paymentSelect = document.getElementById('paymentMethod');
                const itemsContainer = document.getElementById('checkoutItems');
                itemsContainer.replaceChildren();
                cart.forEach(item => {
                    const itemRow = document.createElement('div');
                    itemRow.className = 'd-flex justify-content-between align-items-start gap-3';
                    itemRow.innerHTML = `<div><div class="fw-semibold text-dark">${escapeHtml(item.name)}</div><div class="small text-secondary">Size: ${escapeHtml(item.size)} · Qty: ${Number(item.quantity)}</div></div><span class="fw-semibold text-nowrap">${money(item.price * item.quantity)}</span>`;
                    itemsContainer.append(itemRow);
                });
                document.getElementById('checkoutPayment').textContent = paymentSelect?.selectedOptions[0]?.textContent || 'GCash';
                document.getElementById('checkoutSubtotal').textContent = money(subtotal);
                document.getElementById('checkoutVat').textContent = money(vat);
                document.getElementById('checkoutShipping').textContent = money(shipping);
                document.getElementById('checkoutTotal').textContent = money(subtotal + shipping);
                pendingOrder = {
                    id: `ORD-${Date.now()}`,
                    date: new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }),
                    status: 'Processing',
                    payment: paymentSelect?.selectedOptions[0]?.textContent || 'GCash',
                    subtotal,
                    vat,
                    shipping,
                    total: subtotal + shipping,
                    items: cart.map(item => ({ ...item }))
                };
                confirmationModal.show();
            });
            confirmButton.addEventListener('click', async () => {
                if (!pendingOrder || !cart.length) {
                    notify('Your cart changed. Review the current cart before placing the order.');
                    confirmationModal.hide();
                    return;
                }
                confirmButton.disabled = true;
                confirmButton.textContent = 'Saving order…';
                try {
                    const response = await fetch('checkout-order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            payment_method: document.getElementById('paymentMethod')?.value || 'gcash',
                            items: cart.map(item => ({ id: item.id, size: item.size, quantity: item.quantity }))
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'The order could not be saved.');
                    pendingOrder = result.order;
                } catch (error) {
                    console.error('Unable to save order to the database.', error);
                    notify(error.message || 'The order could not be saved. Your cart is unchanged.');
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = '<i class="bi bi-bag-check me-1"></i>Confirm &amp; Place Order';
                    return;
                }
                document.getElementById('checkoutOrderId').textContent = pendingOrder.id;
                cart = [];
                save();
                title.textContent = 'Order confirmed';
                review.classList.add('d-none');
                success.classList.remove('d-none');
                reviewActions.classList.add('d-none');
                successActions.classList.remove('d-none');
                pendingOrder = null;
            });
        }
        render();
    }

    function setupOrders() {
        const list = document.getElementById('orderListContainer');
        if (!list) return;
        const filters = Array.from(document.querySelectorAll('#orderFilterTab button'));
        const stored = read(keys.orders);
        const orders = Array.isArray(stored) ? stored.map(order => {
            const itemSubtotal = Array.isArray(order.items)
                ? order.items.reduce((sum, item) => sum + Number(item.price || 0) * Number(item.quantity || 0), 0)
                : 0;
            const subtotal = Number(order.subtotal) || itemSubtotal;
            const vat = Number(order.vat) || calculateCartVat(order.items || []);
            const shipping = order.shipping === undefined ? 30 : Number(order.shipping);
            return { ...order, subtotal, vat, shipping, total: subtotal + shipping };
        }) : [];
        const statusClass = status => {
            const normalized = String(status).toLowerCase();
            if (normalized === 'completed') return 'bg-success bg-opacity-10 text-success border-success';
            if (normalized === 'cancelled') return 'bg-danger bg-opacity-10 text-danger border-danger';
            return 'bg-primary bg-opacity-10 text-primary border-primary';
        };
        orders.slice().reverse().forEach(order => {
            const card = document.createElement('article');
            card.className = 'card border-0 shadow-sm p-4 bg-white';
            card.dataset.status = order.status;
            const preview = order.items?.[0];
            const orderLabel = String(order.id).startsWith('ORD-') ? order.id : `ORD-2026-${String(order.id).padStart(3, '0')}`;
            card.innerHTML = `<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">
                <div><span class="fw-bold text-dark">Order #${escapeHtml(orderLabel)}</span><span class="text-muted small ms-2">Placed on ${escapeHtml(order.date)}</span></div>
                <span class="badge ${statusClass(order.status)} border px-3 py-2 rounded-pill">${escapeHtml(order.status)}</span></div>
                ${preview ? `<div class="d-flex align-items-center gap-3 mb-3"><img src="${escapeHtml(preview.image || 'assets/product.jpg')}" alt="" class="rounded object-fit-cover" style="width:65px;height:65px"><div class="flex-grow-1"><h6 class="mb-0 fw-semibold">${escapeHtml(preview.name)}</h6><small class="text-muted">Size: ${escapeHtml(preview.size)} | Qty: ${Number(preview.quantity)}</small></div><strong>${money(preview.price * preview.quantity)}</strong></div>` : ''}
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pt-3 border-top gap-3"><div><small class="text-secondary">Payment Method: <span class="fw-semibold text-dark">${escapeHtml(order.payment)}</span></small><div class="h6 fw-bold mb-0 mt-1">Total Amount: ${money(order.total)}</div></div><button type="button" class="view-order-btn btn btn-dark btn-sm px-3" data-order='${escapeHtml(JSON.stringify(order))}'>View Details</button></div>`;
            list.prepend(card);
        });

        const modalElement = document.getElementById('orderDetailsModal');
        const modal = modalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
        list.addEventListener('click', event => {
            const button = event.target.closest('.view-order-btn');
            if (!button || !modal) return;
            let order;
            if (button.dataset.order) order = JSON.parse(button.dataset.order);
            else {
                const card = button.closest('.card');
                const item = card.querySelector('.d-flex.align-items-center.gap-3.mb-3');
                const text = item?.querySelector('.flex-grow-1');
                order = {
                    id: button.dataset.orderId || '000',
                    date: button.dataset.orderDate || '',
                    status: button.dataset.orderStatus || 'Processing',
                    payment: button.dataset.orderPayment || 'GCash',
                    subtotal: Number((button.dataset.orderSubtotal || '').replace(/[^\d.]/g, '')) || 0,
                    shipping: Number((button.dataset.orderShipping || '').replace(/[^\d.]/g, '')) || 30,
                    items: [{
                        name: text?.querySelector('h6')?.textContent.trim() || 'Classic T-Shirt',
                        size: (text?.querySelector('small')?.textContent.match(/Size:\s*([^|]+)/) || [,'M'])[1].trim(),
                        quantity: Number((text?.querySelector('small')?.textContent.match(/Qty:\s*(\d+)/) || [,'1'])[1]),
                        price: 250
                    }]
                };
                order.vat = Number((button.dataset.orderVat || '').replace(/[^\d.]/g, '')) || calculateCartVat(order.items);
                order.total = order.subtotal + order.shipping;
            }
            document.getElementById('modalOrderId').textContent = `#${String(order.id).startsWith('ORD-') ? order.id : `ORD-2026-${String(order.id).padStart(3, '0')}`}`;
            document.getElementById('modalOrderDate').textContent = order.date;
            const status = document.getElementById('modalOrderstatus');
            status.textContent = order.status;
            status.className = `badge ${statusClass(order.status)} border rounded-pill px-3`;
            document.getElementById('modalOrderPayment').textContent = order.payment;
            document.getElementById('modalSubtotal').textContent = money(order.subtotal);
            document.getElementById('modalVat').textContent = money(order.vat ?? calculateCartVat(order.items || []));
            document.getElementById('modalShipping').textContent = money(order.shipping);
            document.getElementById('modalTotal').textContent = money(order.subtotal + order.shipping);
            const table = document.getElementById('modalItemsTableBody');
            table.replaceChildren();
            (order.items || []).forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `<td class="fw-semibold">${escapeHtml(item.name)}</td><td>${escapeHtml(item.size)}</td><td>${money(item.price)}</td><td>${Number(item.quantity)}</td><td class="text-end fw-semibold">${money(item.price * item.quantity)}</td>`;
                table.append(row);
            });
            modal.show();
        });
        filters.forEach(button => button.addEventListener('click', () => {
            const filter = button.id === 'all-tab' ? 'all' : button.textContent.trim().toLowerCase();
            filters.forEach(item => item.classList.toggle('active', item === button));
            Array.from(list.children).forEach(card => {
                const status = (card.dataset.status || card.querySelector('.badge')?.textContent || '').trim().toLowerCase();
                card.classList.toggle('d-none', filter !== 'all' && status !== filter);
            });
        }));
        fetch('customer-orders-api.php', { cache: 'no-store' })
            .then(async response => {
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load orders.');
                return result.orders || [];
            })
            .then(serverOrders => {
                list.replaceChildren();
                if (!serverOrders.length) {
                    const empty = document.createElement('div');
                    empty.className = 'card border-0 shadow-sm p-4 text-center text-secondary';
                    empty.textContent = 'You have no orders yet.';
                    list.append(empty);
                    return;
                }
                serverOrders.forEach(order => {
                    const preview = order.items?.[0];
                    const card = document.createElement('article');
                    card.className = 'card border-0 shadow-sm p-4 bg-white';
                    card.dataset.status = order.status;
                    const itemSubtotal = (order.items || []).reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
                    const paymentLabels = { gcash: 'GCash / Digital Wallet', card: 'Credit / Debit Card', cod: 'Cash on Delivery / Pickup' };
                    card.innerHTML = `<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">
                        <div><span class="fw-bold text-dark">Order #${escapeHtml(order.id)}</span><span class="text-muted small ms-2">Placed on ${escapeHtml(order.date)}</span></div>
                        <span class="badge ${statusClass(order.status)} border px-3 py-2 rounded-pill">${escapeHtml(order.status)}</span></div>
                        ${preview ? `<div class="d-flex align-items-center gap-3 mb-3"><img src="${escapeHtml(preview.image || 'assets/product.jpg')}" alt="" class="rounded object-fit-cover" style="width:65px;height:65px"><div class="flex-grow-1"><h6 class="mb-0 fw-semibold">${escapeHtml(preview.name)}</h6><small class="text-muted">Size: ${escapeHtml(preview.size)} | Qty: ${Number(preview.quantity)}</small></div><strong>${money(preview.price * preview.quantity)}</strong></div>` : ''}
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pt-3 border-top gap-3"><div><small class="text-secondary">Payment Method: <span class="fw-semibold text-dark">${escapeHtml(paymentLabels[order.payment] || order.payment)}</span></small><div class="h6 fw-bold mb-0 mt-1">Total Amount: ${money(order.total || (itemSubtotal + order.vat + order.shipping))}</div></div><button type="button" class="view-order-btn btn btn-dark btn-sm px-3">View Details</button></div>`;
                    card.querySelector('.view-order-btn').dataset.order = JSON.stringify(order);
                    list.append(card);
                });
                const activeFilter = filters.find(button => button.classList.contains('active'));
                if (activeFilter && activeFilter.id !== 'all-tab') {
                    const filter = activeFilter.textContent.trim().toLowerCase();
                    Array.from(list.children).forEach(card => card.classList.toggle('d-none', card.dataset.status.toLowerCase() !== filter));
                }
            })
            .catch(error => console.error('Unable to load customer orders from the database.', error));
    }

    function setupAccount() {
        const accountForm = document.getElementById('accountForm');
        if (accountForm?.dataset.serverManaged === 'true') return;
        if (accountForm) {
            syncProfile();
            accountForm.addEventListener('submit', event => {
                event.preventDefault();
                const profile = {
                    ...(read(keys.profile) || {}),
                    firstName: document.getElementById('accountFn').value.trim(),
                    middleName: document.getElementById('accountMn').value.trim(),
                    lastName: document.getElementById('accountLn').value.trim(),
                    username: document.getElementById('accountUsername').value.trim(),
                    contact: document.getElementById('accountContact').value.trim(),
                    street: document.getElementById('accountStreet').value.trim(),
                    barangay: document.getElementById('accountBarangay').value.trim(),
                    city: document.getElementById('accountCity').value.trim(),
                    province: document.getElementById('accountProvince').value.trim(),
                    zip: document.getElementById('accountZip').value.trim()
                };
                if (!profile.firstName || !profile.lastName || !profile.username) {
                    notify('First name, last name, and username are required.');
                    return;
                }
                write(keys.profile, profile);
                syncProfile();
                notify('Profile saved in this browser. Server sync is not connected yet.');
            });
        }
        const avatarInput = document.getElementById('accountAvatarInput');
        avatarInput?.addEventListener('change', () => {
            const file = avatarInput.files?.[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                avatarInput.value = '';
                notify('Choose a JPG, PNG, or WebP image.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                avatarInput.value = '';
                notify('Choose an image smaller than 5 MB.');
                return;
            }
            const reader = new FileReader();
            reader.addEventListener('error', () => {
                avatarInput.value = '';
                notify('The selected image could not be read.');
            }, { once: true });
            reader.addEventListener('load', () => {
                const image = new Image();
                image.addEventListener('error', () => {
                    avatarInput.value = '';
                    notify('The selected file is not a valid image.');
                }, { once: true });
                image.addEventListener('load', () => {
                    const canvas = document.createElement('canvas');
                    const size = 320;
                    canvas.width = size;
                    canvas.height = size;
                    const context = canvas.getContext('2d');
                    if (!context) {
                        avatarInput.value = '';
                        notify('Your browser could not process this image.');
                        return;
                    }
                    const cropSize = Math.min(image.naturalWidth, image.naturalHeight);
                    const sourceX = (image.naturalWidth - cropSize) / 2;
                    const sourceY = (image.naturalHeight - cropSize) / 2;
                    context.drawImage(image, sourceX, sourceY, cropSize, cropSize, 0, 0, size, size);
                    const profile = read(keys.profile) || {};
                    profile.avatar = canvas.toDataURL('image/jpeg', 0.82);
                    try {
                        write(keys.profile, profile);
                    } catch (error) {
                        console.error('Unable to save the profile photo in browser storage.', error);
                        avatarInput.value = '';
                        notify('The photo could not be saved in this browser. Try a smaller image.');
                        return;
                    }
                    syncAvatar(profile);
                    notify('Profile photo updated.');
                }, { once: true });
                image.src = String(reader.result);
            }, { once: true });
            reader.readAsDataURL(file);
        });
        document.getElementById('removeAvatarBtn')?.addEventListener('click', () => {
            const profile = read(keys.profile) || {};
            delete profile.avatar;
            try {
                write(keys.profile, profile);
            } catch (error) {
                console.error('Unable to remove the profile photo from browser storage.', error);
                notify('The photo could not be removed from this browser.');
                return;
            }
            avatarInput.value = '';
            syncAvatar(profile);
            notify('Profile photo removed.');
        });
        const passwordForm = document.getElementById('passwordForm');
        passwordForm?.querySelectorAll('input').forEach(input => input.addEventListener('input', () => input.setCustomValidity('')));
        passwordForm?.addEventListener('submit', event => {
            event.preventDefault();
            const current = document.getElementById('currentPassword');
            const next = document.getElementById('newPassword');
            const confirm = document.getElementById('confirmPassword');
            if (!current.value || !next.value || !confirm.value) {
                notify('Complete all password fields before continuing.');
                return;
            }
            if (next.value.length < 8) {
                next.setCustomValidity('Use at least 8 characters for the new password.');
                next.reportValidity();
                return;
            }
            next.setCustomValidity('');
            if (next.value !== confirm.value) {
                confirm.setCustomValidity('The new passwords do not match.');
                confirm.reportValidity();
                return;
            }
            confirm.setCustomValidity('');
            notify('Password changes require the account server and are not saved in this prototype.');
            passwordForm.reset();
        });
        document.getElementById('logoutBtn')?.addEventListener('click', () => {
            sessionStorage.removeItem('tindatrack.session');
        });
    }

    syncProfile();
    setupCatalog();
    setupCart();
    setupOrders();
    setupAccount();
});
