document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('usersTableBody');
    const form = document.getElementById('createAccountForm');
    const modalElement = document.getElementById('createAccountModal');
    if (!tableBody || !form || !modalElement) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const idDocumentModalElement = document.getElementById('idDocumentModal');
    const idDocumentModal = idDocumentModalElement
        ? bootstrap.Modal.getOrCreateInstance(idDocumentModalElement)
        : null;
    const accountStatusModalElement = document.getElementById('accountStatusModal');
    const accountStatusModal = accountStatusModalElement
        ? bootstrap.Modal.getOrCreateInstance(accountStatusModalElement)
        : null;
    const promotionModalElement = document.getElementById('customerPromotionModal');
    const promotionModal = promotionModalElement
        ? bootstrap.Modal.getOrCreateInstance(promotionModalElement)
        : null;
    const search = document.getElementById('usersSearch');
    const errorMessage = document.getElementById('createAccountError');
    const submitButton = document.getElementById('createAccountSubmit');
    const passwordInput = form.elements.password;
    const confirmInput = form.elements.confirm_password;
    const roleInput = form.elements.role;
    const addressInputs = ['street', 'barangay', 'city', 'province', 'postal']
        .map(name => form.elements[name]);
    const roleNames = { 1: 'Customer', 2: 'Administrator' };
    const usersById = new Map();
    const filters = Array.from(document.querySelectorAll('[data-role-filter]'));
    let csrfToken = '';
    let selectedRole = 'all';
    let editingUserId = null;
    let pendingAccountStatus = null;
    let pendingPromotionUserId = null;
    let confirmedPromotionUserId = null;
    const accountStatusError = document.getElementById('accountStatusError');
    const accountStatusConfirm = document.getElementById('accountStatusConfirm');
    const accountStatusCancel = document.getElementById('accountStatusCancel');

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);

    function renderUser(user) {
        const row = document.createElement('tr');
        row.dataset.userId = String(user.user_id);
        row.dataset.role = String(user.position);
        row.dataset.active = user.is_active ? '1' : '0';
        const name = [user.first_name, user.middle_name, user.last_name].filter(Boolean).join(' ');
        const address = [user.street, user.barangay, user.city, user.province, user.postal].filter(Boolean).join(', ');
        const ordersLink = `admin-order.php?search=${encodeURIComponent(user.username)}`;
        const verificationStatus = user.verification_status || 'Pending';
        const verificationBadgeClass = verificationStatus === 'Approved'
            ? 'bg-success'
            : verificationStatus === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark';
        const verificationControls = Number(user.position) !== 1
            ? '<span class="text-secondary">—</span>'
            : `<div class="d-flex flex-wrap align-items-center gap-1">
                ${user.has_id_document
                    ? '<button type="button" class="btn btn-sm btn-outline-primary view-id-btn"><i class="bi bi-card-image me-1" aria-hidden="true"></i>View ID</button>'
                    : '<span class="small text-secondary">No ID submitted</span>'}
                ${verificationStatus === 'Pending' && user.has_id_document
                    ? `<button type="button" class="btn btn-sm btn-outline-success verification-action-btn" data-verification-status="Approved"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Approve</button>
                        <button type="button" class="btn btn-sm btn-outline-danger verification-action-btn" data-verification-status="Rejected"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Reject</button>`
                    : ''}
            </div>`;
        row.innerHTML = `
            <td class="ps-4 fw-semibold">${escapeHtml(name)}</td>
            <td>@${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.contact_no)}</td>
            <td><span class="small">${escapeHtml(address || 'Not provided')}</span></td>
            <td>${Number(user.position) === 1
                ? `<a href="${ordersLink}" class="text-decoration-none">${Number(user.order_count).toLocaleString('en-PH')} orders</a>`
                : '<span class="text-secondary" aria-label="Not applicable">—</span>'}</td>
            <td>${escapeHtml(roleNames[Number(user.position)] || 'Unknown')}</td>
            <td><span class="account-status-badge ${user.is_active ? 'is-active' : 'is-inactive'}">
                ${user.is_active ? 'Active' : 'Deactivated'}</span></td>
            <td>${Number(user.position) === 1
                ? `<span class="badge ${verificationBadgeClass} mb-2">${escapeHtml(verificationStatus)}</span>${verificationControls}`
                : verificationControls}</td>
            <td class="text-end pe-4"><div class="d-flex justify-content-end gap-1">
                <button type="button" class="btn btn-sm btn-outline-dark edit-user-btn" aria-label="Edit ${escapeHtml(name)}">
                    <i class="bi bi-pencil" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm ${user.is_active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-user-btn"
                    ${user.is_active && Number(user.user_id) === Number(window.dciVaultAdminUserId) ? 'disabled title="You cannot deactivate your own account."' : ''}
                    aria-label="${user.is_active ? 'Deactivate' : 'Reactivate'} ${escapeHtml(name)}">
                    <i class="bi ${user.is_active ? 'bi-person-dash' : 'bi-person-check'}" aria-hidden="true"></i>
                </button>
            </div></td>`;
        return row;
    }

    function updateFilters() {
        const query = search.value.trim().toLocaleLowerCase();
        const rows = Array.from(tableBody.querySelectorAll('tr[data-user-id]'));
        const counts = { all: rows.length, '1': 0, '2': 0, inactive: 0, pending: 0 };
        rows.forEach(row => {
            const user = usersById.get(row.dataset.userId);
            if (user && Number(user.position) in roleNames) counts[String(user.position)] += 1;
            if (user && !user.is_active) counts.inactive += 1;
            if (user && Number(user.position) === 1 && user.verification_status === 'Pending') counts.pending += 1;
            const matchesRole = selectedRole === 'all'
                || (selectedRole === 'inactive'
                    ? !user.is_active
                    : selectedRole === 'verification-pending'
                        ? Number(user.position) === 1 && user.verification_status === 'Pending'
                        : String(user.position) === selectedRole);
            const matchesSearch = row.textContent.toLocaleLowerCase().includes(query);
            row.classList.toggle('d-none', !matchesRole || !matchesSearch);
        });

        document.getElementById('allAccountCount').textContent = counts.all;
        document.getElementById('adminAccountCount').textContent = counts['2'];
        document.getElementById('customerAccountCount').textContent = counts['1'];
        document.getElementById('inactiveAccountCount').textContent = counts.inactive;
        document.getElementById('pendingVerificationCount').textContent = counts.pending;

        let empty = tableBody.querySelector('[data-empty-state]');
        if (!empty) {
            empty = document.createElement('tr');
            empty.dataset.emptyState = 'true';
            empty.innerHTML = '<td colspan="9" class="text-center text-secondary py-4">No accounts match this filter.</td>';
            tableBody.append(empty);
        }
        empty.classList.toggle('d-none', rows.some(row => !row.classList.contains('d-none')));
    }

    async function postUpdate(payload) {
        const response = await fetch('admin-users-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save account changes.');
        return result;
    }

    async function loadUsers() {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Loading accounts…</td></tr>';
        const response = await fetch('admin-users-api.php', { cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load user accounts.');
        csrfToken = result.csrf_token;
        window.dciVaultAdminUserId = Number(result.current_user_id);
        usersById.clear();
        (result.users || []).forEach(user => usersById.set(String(user.user_id), user));
        const rows = Array.from(usersById.values(), renderUser);
        tableBody.replaceChildren(...rows);
        updateFilters();
    }

    function openForm(user) {
        form.reset();
        errorMessage.textContent = '';
        editingUserId = user ? Number(user.user_id) : null;
        document.getElementById('editUserId').value = user ? String(user.user_id) : '';
        document.getElementById('createAccountModalLabel').textContent = user ? 'Edit Account' : 'Create Account';
        submitButton.textContent = user ? 'Save Changes' : 'Create Account';
        passwordInput.required = !user;
        confirmInput.required = !user;
        passwordInput.value = '';
        confirmInput.value = '';
        form.elements.username.value = user ? user.username : '';
        form.elements.role.value = user ? String(user.position) : '1';
        form.elements.first_name.value = user ? user.first_name : '';
        form.elements.middle_name.value = user ? user.middle_name || '' : '';
        form.elements.last_name.value = user ? user.last_name : '';
        form.elements.phone.value = user ? user.contact_no : '';
        form.elements.street.value = user ? user.street : '';
        form.elements.barangay.value = user ? user.barangay : '';
        form.elements.city.value = user ? user.city : '';
        form.elements.province.value = user ? user.province : '';
        form.elements.postal.value = user ? user.postal : '';
        updateAddressRequirements();
        modal.show();
    }

    function updateAddressRequirements() {
        const addressRequired = roleInput.value === '1';
        addressInputs.forEach(input => {
            input.required = addressRequired;
        });
    }

    roleInput.addEventListener('change', updateAddressRequirements);

    filters.forEach(button => button.addEventListener('click', () => {
        selectedRole = button.dataset.roleFilter;
        filters.forEach(filter => {
            const active = filter === button;
            filter.classList.toggle('active', active);
            filter.setAttribute('aria-pressed', String(active));
        });
        updateFilters();
    }));
    search.addEventListener('input', updateFilters);
    document.getElementById('createAccountOpenBtn').addEventListener('click', () => openForm(null));
    tableBody.addEventListener('click', async event => {
        const row = event.target.closest('tr[data-user-id]');
        if (!row) return;
        const user = usersById.get(row.dataset.userId);
        if (event.target.closest('.view-id-btn')) {
            if (!idDocumentModal) return;
            document.getElementById('idDocumentModalTitle').textContent = `ID submission: ${user.username}`;
            document.getElementById('idDocumentPreview').src =
                `admin-id-image.php?user_id=${encodeURIComponent(user.user_id)}&v=${Date.now()}`;
            idDocumentModal.show();
            return;
        }
        const verificationButton = event.target.closest('.verification-action-btn');
        if (verificationButton) {
            const verificationStatus = verificationButton.dataset.verificationStatus;
            if (
                verificationStatus === 'Rejected' &&
                !window.confirm(`Reject the ID verification submitted by ${user.username}?`)
            ) return;
            verificationButton.disabled = true;
            try {
                await postUpdate({
                    action: 'verification',
                    user_id: user.user_id,
                    verification_status: verificationStatus
                });
                await loadUsers();
            } catch (error) {
                window.alert(error.message);
                verificationButton.disabled = false;
            }
            return;
        }
        if (event.target.closest('.edit-user-btn')) {
            openForm(user);
            return;
        }
        if (!event.target.closest('.toggle-user-btn')) return;
        const nextActive = !user.is_active;
        if (!accountStatusModal) return;
        pendingAccountStatus = { user, nextActive };
        accountStatusError.textContent = '';
        document.getElementById('accountStatusModalTitle').textContent =
            nextActive ? 'Reactivate account?' : 'Deactivate account?';
        document.getElementById('accountStatusModalMessage').textContent =
            `${nextActive ? 'Reactivate' : 'Deactivate'} ${user.username}? Their order history will be preserved.`;
        accountStatusConfirm.textContent = nextActive ? 'Reactivate account' : 'Deactivate account';
        accountStatusConfirm.className = `btn ${nextActive ? 'btn-success' : 'btn-danger'}`;
        accountStatusCancel.textContent = nextActive ? 'Cancel' : 'Keep account';
        accountStatusModal.show();
    });

    accountStatusConfirm.addEventListener('click', async () => {
        if (!pendingAccountStatus || accountStatusConfirm.disabled) return;
        const { user, nextActive } = pendingAccountStatus;
        accountStatusConfirm.disabled = true;
        accountStatusError.textContent = '';
        try {
            await postUpdate({ action: 'status', user_id: user.user_id, is_active: nextActive ? 1 : 0 });
            await loadUsers();
            accountStatusModal.hide();
            pendingAccountStatus = null;
        } catch (error) {
            accountStatusError.textContent = error.message || 'Unable to update account status.';
        } finally {
            accountStatusConfirm.disabled = false;
        }
    });

    accountStatusModalElement?.addEventListener('hidden.bs.modal', () => {
        pendingAccountStatus = null;
        accountStatusError.textContent = '';
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        errorMessage.textContent = '';
        confirmInput.setCustomValidity('');
        if (!form.reportValidity()) return;
        if (passwordInput.value !== confirmInput.value) {
            confirmInput.setCustomValidity('Passwords do not match.');
            form.reportValidity();
            return;
        }

        const values = Object.fromEntries(new FormData(form).entries());
        const editedUser = editingUserId ? usersById.get(String(editingUserId)) : null;
        if (
            editingUserId &&
            editedUser &&
            Number(editedUser.position) === 1 &&
            Number(values.role) === 2 &&
            confirmedPromotionUserId !== editingUserId
        ) {
            if (!promotionModal) return;
            pendingPromotionUserId = editingUserId;
            promotionModal.show();
            return;
        }
        confirmedPromotionUserId = null;

        submitButton.disabled = true;
        try {
            if (editingUserId) {
                await postUpdate({
                    action: 'update',
                    user_id: editingUserId,
                    role: Number(values.role),
                    first_name: values.first_name,
                    middle_name: values.middle_name,
                    last_name: values.last_name,
                    username: values.username,
                    contact_no: values.phone,
                    street: values.street,
                    barangay: values.barangay,
                    city: values.city,
                    province: values.province,
                    postal: values.postal,
                    password: values.password,
                    confirm_password: values.confirm_password
                });
            } else {
                const response = await fetch('admin-create-account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(values)
                });
                const result = await response.json();
                if (!response.ok || !result.account) throw new Error(result.message || 'Unable to create the account.');
            }
            await loadUsers();
            modal.hide();
        } catch (error) {
            errorMessage.textContent = error.message || 'Unable to save account changes.';
        } finally {
            submitButton.disabled = false;
        }
    });

    document.getElementById('customerPromotionConfirm').addEventListener('click', () => {
        if (!pendingPromotionUserId) return;
        confirmedPromotionUserId = pendingPromotionUserId;
        pendingPromotionUserId = null;
        promotionModal.hide();
        form.requestSubmit();
    });

    promotionModalElement?.addEventListener('hidden.bs.modal', () => {
        pendingPromotionUserId = null;
    });

    loadUsers().catch(error => {
        console.error('Unable to load user accounts.', error);
        tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
    });

    idDocumentModalElement?.addEventListener('hidden.bs.modal', () => {
        document.getElementById('idDocumentPreview').removeAttribute('src');
    });
});
