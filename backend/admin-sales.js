document.addEventListener('DOMContentLoaded', () => {
    const periodSelect = document.getElementById('salesPeriod');
    const currency = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    });
    const dateFormat = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' });
    const dateTimeFormat = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    const categories = [
        { name: 'Apparel', share: 0.46, color: 'category-blue' },
        { name: 'Outerwear', share: 0.27, color: 'category-violet' },
        { name: 'Bottoms', share: 0.17, color: 'category-green' },
        { name: 'Accessories', share: 0.10, color: 'category-amber' }
    ];
    const products = [
        { name: 'Classic T-Shirt', category: 'Apparel', price: 250, share: 0.66 },
        { name: 'Premium Hoodie', category: 'Outerwear', price: 850, share: 0.66 },
        { name: 'Everyday Joggers', category: 'Bottoms', price: 620, share: 1 },
        { name: 'Canvas Tote Bag', category: 'Accessories', price: 320, share: 1 },
        { name: 'Zip-Up Jacket', category: 'Outerwear', price: 1100, share: 0.34 }
    ];
    const paymentMethods = ['GCash', 'Cash on Delivery', 'Card'];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const dailySales = Array.from({ length: 90 }, (_, index) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (89 - index));
        const weekdayFactor = [0.82, 0.9, 0.96, 1.02, 1.08, 1.34, 1.2][date.getDay()];
        const revenue = Math.round((1800 + ((index * 673) % 4200) + (index % 4) * 275) * weekdayFactor);
        const orders = Math.max(2, Math.round(revenue / (680 + (index % 3) * 45)));
        return { date, revenue, orders };
    });

    const transactions = Array.from({ length: 18 }, (_, index) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (index % 16));
        const product = products[index % products.length];
        const quantity = index % 3 + 1;
        return {
            id: `ORD-2026-${String(48 - index).padStart(3, '0')}`,
            date,
            amount: product.price * quantity,
            payment: paymentMethods[index % paymentMethods.length]
        };
    });

    function getReportDays() {
        const periodDays = Number(periodSelect.value);
        const startDate = new Date(today);
        startDate.setDate(today.getDate() - (periodDays - 1));
        return dailySales.filter(day => day.date >= startDate && day.date <= today);
    }

    function renderReport() {
        const reportDays = getReportDays();
        const periodDays = Number(periodSelect.value);
        const startDate = new Date(today);
        startDate.setDate(today.getDate() - (periodDays - 1));
        const reportTransactions = transactions.filter(sale => sale.date >= startDate && sale.date <= today);
        const grossSales = reportDays.reduce((sum, day) => sum + day.revenue, 0);
        const orderCount = reportDays.reduce((sum, day) => sum + day.orders, 0);

        document.getElementById('salesGross').textContent = currency.format(grossSales);
        document.getElementById('salesOrders').textContent = orderCount.toLocaleString('en-PH');
        document.getElementById('salesAverage').textContent = currency.format(orderCount ? grossSales / orderCount : 0);
        document.getElementById('salesChartCaption').textContent = `Daily revenue · ${periodSelect.options[periodSelect.selectedIndex].text}`;
        document.getElementById('salesChartStart').textContent = reportDays.length ? dateFormat.format(reportDays[0].date) : '—';
        document.getElementById('salesChartEnd').textContent = reportDays.length ? dateFormat.format(reportDays[reportDays.length - 1].date) : '—';
        renderChart(reportDays);
        renderCategories(grossSales);
        renderProducts(grossSales);
        renderTransactions(reportTransactions);
    }

    function renderChart(days) {
        const chart = document.getElementById('salesChart');
        const maxRevenue = Math.max(...days.map(day => day.revenue), 1);
        chart.setAttribute('aria-label', `Sales chart with ${days.length} daily revenue bars`);
        chart.innerHTML = days.map((day, index) => {
            const height = Math.max(5, Math.round(day.revenue / maxRevenue * 100));
            const label = `${dateTimeFormat.format(day.date)}: ${currency.format(day.revenue)}`;
            const showTick = days.length <= 10 || index % Math.ceil(days.length / 7) === 0;
            return `<div class="admin-sales-bar-column" title="${label}">
                <div class="admin-sales-bar-track"><span class="admin-sales-bar" style="height:${height}%" aria-label="${label}"></span></div>
                <span class="admin-sales-bar-label">${showTick ? dateFormat.format(day.date) : ''}</span>
            </div>`;
        }).join('');
    }

    function renderCategories(grossSales) {
        document.getElementById('salesCategories').innerHTML = categories.map(category => {
            const amount = grossSales * category.share;
            return `<div class="admin-sales-category">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold">${category.name}</span>
                    <span class="small text-secondary">${currency.format(amount)}</span>
                </div>
                <div class="progress" role="progressbar" aria-label="${category.name} sales" aria-valuenow="${Math.round(category.share * 100)}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar ${category.color}" style="width:${category.share * 100}%"></div>
                </div>
            </div>`;
        }).join('');
    }

    function renderProducts(grossSales) {
        const rankedProducts = products.map(product => {
            const category = categories.find(item => item.name === product.category);
            const revenue = grossSales * category.share * product.share;
            const units = Math.max(1, Math.round(revenue / product.price));
            return { product, category, revenue, units };
        }).sort((a, b) => b.units - a.units);

        document.getElementById('topProductsBody').innerHTML = rankedProducts.map(({ product, category, revenue, units }) => `<tr>
                <td class="ps-4"><span class="admin-sales-product-dot ${category.color}"></span>${product.name}</td>
                <td>${units}</td>
                <td class="text-end pe-4 fw-semibold">${currency.format(revenue)}</td>
            </tr>`).join('');
    }

    function renderTransactions(sales) {
        const body = document.getElementById('recentSalesBody');
        body.innerHTML = sales.slice(0, 8).map(sale => `<tr>
            <td class="ps-4 fw-semibold">${sale.id}</td>
            <td class="text-secondary">${dateFormat.format(sale.date)}</td>
            <td>${sale.payment}</td>
            <td class="text-end pe-4 fw-semibold">${currency.format(sale.amount)}</td>
        </tr>`).join('');

        if (!sales.length) {
            body.innerHTML = '<tr><td class="ps-4 py-4 text-secondary" colspan="4">No sales recorded in this period.</td></tr>';
        }
    }

    periodSelect.addEventListener('change', renderReport);
    renderReport();
});
