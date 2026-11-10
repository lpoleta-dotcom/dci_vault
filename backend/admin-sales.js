document.addEventListener('DOMContentLoaded', () => {
    const periodSelect = document.getElementById('salesPeriod');
    const currency = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    });
    const dateFormat = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' });
    const dateTimeFormat = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    const categoryColors = ['category-blue', 'category-violet', 'category-green', 'category-amber'];
    const paymentNames = {
        gcash: 'GCash',
        card: 'Credit / Debit Card',
        cod: 'Cash on Delivery / Pickup'
    };

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);

    function showError(message) {
        document.getElementById('salesGross').textContent = '—';
        document.getElementById('salesOrders').textContent = '—';
        document.getElementById('salesAverage').textContent = '—';
        document.getElementById('salesChart').innerHTML = `<p class="text-danger">${escapeHtml(message)}</p>`;
        document.getElementById('salesCategories').innerHTML = '';
        document.getElementById('topProductsBody').innerHTML =
            `<tr><td class="ps-4 py-4 text-danger" colspan="3">${escapeHtml(message)}</td></tr>`;
        document.getElementById('recentSalesBody').innerHTML =
            `<tr><td class="ps-4 py-4 text-danger" colspan="4">${escapeHtml(message)}</td></tr>`;
    }

    function renderChart(dailySales, periodDays) {
        const chart = document.getElementById('salesChart');
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const data = [];
        for (let offset = periodDays - 1; offset >= 0; offset -= 1) {
            const date = new Date(now);
            date.setDate(now.getDate() - offset);
            const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            const daily = dailySales[key] || { revenue: 0, orders: 0 };
            data.push({ date, revenue: Number(daily.revenue) || 0, orders: Number(daily.orders) || 0 });
        }

        const maxRevenue = Math.max(...data.map(day => day.revenue), 1);
        chart.setAttribute('aria-label', `Sales chart with ${data.length} daily revenue bars`);
        chart.innerHTML = data.map((day, index) => {
            const height = Math.max(2, Math.round(day.revenue / maxRevenue * 100));
            const label = `${dateTimeFormat.format(day.date)}: ${currency.format(day.revenue)}`;
            const showTick = data.length <= 10 || index % Math.ceil(data.length / 7) === 0;
            return `<div class="admin-sales-bar-column" title="${escapeHtml(label)}">
                <div class="admin-sales-bar-track"><span class="admin-sales-bar" style="height:${height}%" aria-label="${escapeHtml(label)}"></span></div>
                <span class="admin-sales-bar-label">${showTick ? escapeHtml(dateFormat.format(day.date)) : ''}</span>
            </div>`;
        }).join('');
        document.getElementById('salesChartStart').textContent = dateFormat.format(data[0].date);
        document.getElementById('salesChartEnd').textContent = dateFormat.format(data[data.length - 1].date);
    }

    function renderCategories(categories) {
        const total = categories.reduce((sum, item) => sum + item.revenue, 0);
        document.getElementById('salesCategories').innerHTML = categories.length
            ? categories.map((item, index) => {
                const share = total ? item.revenue / total * 100 : 0;
                const color = categoryColors[index % categoryColors.length];
                return `<div class="admin-sales-category">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-semibold">${escapeHtml(item.name)}</span>
                        <span class="small text-secondary">${currency.format(item.revenue)}</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="${escapeHtml(item.name)} sales"
                        aria-valuenow="${Math.round(share)}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar ${color}" style="width:${share}%"></div>
                    </div>
                </div>`;
            }).join('')
            : '<p class="text-secondary mb-0">No category sales in this period.</p>';
    }

    function renderProducts(products) {
        const body = document.getElementById('topProductsBody');
        body.innerHTML = products.length
            ? products.map((product, index) => `<tr>
                <td class="ps-4"><span class="admin-sales-product-dot ${categoryColors[index % categoryColors.length]}"></span>${escapeHtml(product.name)}</td>
                <td>${Number(product.units).toLocaleString('en-PH')}</td>
                <td class="text-end pe-4 fw-semibold">${currency.format(product.revenue)}</td>
            </tr>`).join('')
            : '<tr><td class="ps-4 py-4 text-secondary" colspan="3">No product sales in this period.</td></tr>';
    }

    function renderTransactions(transactions) {
        const body = document.getElementById('recentSalesBody');
        body.innerHTML = transactions.length
            ? transactions.slice(0, 8).map(sale => {
                const [year, month, day] = sale.date.slice(0, 10).split('-').map(Number);
                const saleDate = new Date(year, month - 1, day);
                return `<tr>
                <td class="ps-4"><span class="fw-semibold">${escapeHtml(sale.order_number)}</span>
                    <small class="d-block text-secondary">${escapeHtml(sale.customer_name)} · @${escapeHtml(sale.username)}</small></td>
                <td class="text-secondary">${escapeHtml(dateFormat.format(saleDate))}</td>
                <td>${escapeHtml(paymentNames[sale.payment] || sale.payment)}</td>
                <td class="text-end pe-4 fw-semibold">${currency.format(sale.amount)}</td>
            </tr>`;
            }).join('')
            : '<tr><td class="ps-4 py-4 text-secondary" colspan="4">No completed sales recorded in this period.</td></tr>';
    }

    async function loadReport() {
        const days = Number(periodSelect.value);
        try {
            const response = await fetch(`admin-sales-api.php?days=${encodeURIComponent(days)}`, { cache: 'no-store' });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load sales report.');

            document.getElementById('salesGross').textContent = currency.format(result.summary.gross);
            document.getElementById('salesOrders').textContent = Number(result.summary.orders).toLocaleString('en-PH');
            document.getElementById('salesAverage').textContent = currency.format(result.summary.average);
            document.getElementById('salesChartCaption').textContent =
                `Daily completed sales · ${periodSelect.options[periodSelect.selectedIndex].text}`;
            renderChart(result.daily_sales || {}, days);
            renderCategories(result.categories || []);
            renderProducts(result.products || []);
            renderTransactions(result.transactions || []);
        } catch (error) {
            console.error('Unable to load sales report.', error);
            showError(error.message || 'Unable to load sales report.');
        }
    }

    periodSelect.addEventListener('change', loadReport);
    loadReport();
});
