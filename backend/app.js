document.addEventListener('DOMContentLoaded', () => {
    const departmentLogo = document.getElementById('departmentLogo');
    const landingScrollPanel = document.querySelector('.landing-page .main-control');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const landingHero = document.querySelector('.landing-hero');
    const landingHeroParticles = landingHero?.querySelector('.landing-hero-particles');

    if (landingHero && landingHeroParticles && !prefersReducedMotion.matches) {
        const particles = Array.from({ length: 10 }, () => document.createElement('span'));
        landingHeroParticles.append(...particles);

        const trackPointer = event => {
            if (prefersReducedMotion.matches || event.pointerType === 'touch') return;

            const bounds = landingHero.getBoundingClientRect();
            landingHeroParticles.style.setProperty('--cursor-x', `${event.clientX - bounds.left}px`);
            landingHeroParticles.style.setProperty('--cursor-y', `${event.clientY - bounds.top}px`);
            landingHero.classList.add('is-tracking-pointer');
        };
        const stopTracking = () => landingHero.classList.remove('is-tracking-pointer');

        landingHero.addEventListener('pointermove', trackPointer, { passive: true });
        landingHero.addEventListener('pointerleave', stopTracking);
        prefersReducedMotion.addEventListener('change', event => {
            if (event.matches) stopTracking();
        });
    }

    if (departmentLogo && landingScrollPanel && !prefersReducedMotion.matches) {
        let logoFrame = 0;
        const syncLogoWithScroll = () => {
            cancelAnimationFrame(logoFrame);
            logoFrame = requestAnimationFrame(() => {
                const rotation = landingScrollPanel.scrollTop * 0.72;
                departmentLogo.style.setProperty('--logo-rotation', `${rotation}deg`);
            });
        };
        landingScrollPanel.addEventListener('scroll', syncLogoWithScroll, { passive: true });
        syncLogoWithScroll();
    }

    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        const password = document.getElementById(button.dataset.passwordToggle);
        const icon = button.querySelector('i');
        if (!password || !icon) return;

        button.addEventListener('click', () => {
            const showPassword = password.type === 'password';
            password.type = showPassword ? 'text' : 'password';
            button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(showPassword));
            icon.classList.toggle('bi-eye', !showPassword);
            icon.classList.toggle('bi-eye-slash', showPassword);
        });
    });

    const categories = document.querySelectorAll('#categoryFilter [data-category]');
    const productGrid = document.getElementById('productGrid');
    if (productGrid && categories.length) {
        let selectedCategory = 'all';
        const applyCategory = () => productGrid.querySelectorAll('.product-card').forEach(card => {
            const wrapper = card.closest('.col, [class*="col-"]') || card;
            wrapper.classList.toggle('d-none', selectedCategory !== 'all' && card.dataset.category !== selectedCategory);
        });
        categories.forEach(button => button.addEventListener('click', () => {
            selectedCategory = button.dataset.category || 'all';
            categories.forEach(filter => {
                const active = filter === button;
                filter.classList.toggle('active', active);
                filter.setAttribute('aria-pressed', String(active));
            });
            applyCategory();
        }));

        fetch('catalog-feed.php')
            .then(response => {
                if (!response.ok) throw new Error('Could not load the catalog.');
                return response.json();
            })
            .then(products => {
                productGrid.replaceChildren();
                products.forEach(product => {
                    const column = document.createElement('div');
                    column.className = 'col-6 col-md-4 col-lg-3 col-xl-3';
                    const card = document.createElement('article');
                    card.className = 'productBox product-card landing-product-card h-100';
                    card.dataset.category = product.category_slug;
                    const image = document.createElement('img');
                    image.className = 'landing-product-image';
                    image.src = product.image_url || 'assets/product.jpg';
                    image.alt = product.name;
                    const info = document.createElement('div');
                    info.className = 'landing-product-info';
                    const category = document.createElement('span');
                    category.className = 'landing-product-category';
                    category.textContent = product.category_name.toUpperCase();
                    const name = document.createElement('h3');
                    name.className = 'productName';
                    name.textContent = product.name;
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center justify-content-between gap-2';
                    const price = document.createElement('p');
                    price.className = 'productPrice mb-0';
                    price.textContent = `₱${Number(product.price).toFixed(2)}`;
                    const link = document.createElement('a');
                    link.href = 'login.php';
                    link.className = 'landing-product-link';
                    link.setAttribute('aria-label', `Log in to shop ${product.name}`);
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-arrow-up-right';
                    link.append(icon);
                    row.append(price, link);
                    info.append(category, name, row);
                    card.append(image, info);
                    column.append(card);
                    productGrid.append(column);
                });
                applyCategory();
            })
            .catch(() => {
                productGrid.textContent = 'The product catalog could not be loaded.';
            });
    }

    const signUpForm = document.getElementById('signUpForm');
    if (signUpForm) {
        const firstName = document.getElementById('new-fnInput');
        const lastName = document.getElementById('new-lnInput');
        const username = document.getElementById('new-userInput');
        const password = document.getElementById('new-pwInput');
        const confirmPassword = document.getElementById('new-confirmPwInput');
        const passwordMessage = document.getElementById('new-pwVerify');
        const confirmPasswordMessage = document.getElementById('new-confirmPwVerify');
        const response = document.getElementById('new-resInput');
        const requiredFields = [
            firstName, lastName, username, document.getElementById('new-contactInput'),
            document.getElementById('new-streetInput'), document.getElementById('new-barangayInput'),
            document.getElementById('new-cityInput'), document.getElementById('new-provinceInput'),
            document.getElementById('new-zipInput'), password, confirmPassword
        ];

        const submitSignUp = event => {
            passwordMessage.textContent = '';
            confirmPasswordMessage.textContent = '';
            response.textContent = '';
            requiredFields.forEach(input => {
                input.setCustomValidity(input.value.trim() ? '' : 'This field is required.');
            });
            const contact = document.getElementById('new-contactInput');
            const digitsInContact = contact.value.replace(/\D/g, '').length;
            if (!/^[\d+\-\s()]{7,20}$/.test(contact.value.trim()) || digitsInContact < 7) {
                contact.setCustomValidity('Enter a valid contact number.');
            }
            if (password.value.length < 8) {
                password.setCustomValidity('Use at least 8 characters.');
                passwordMessage.textContent = 'Password must be at least 8 characters.';
            }
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match.');
                confirmPasswordMessage.textContent = 'Passwords do not match.';
            }
            if (!signUpForm.reportValidity()) event.preventDefault();
        };
        signUpForm.addEventListener('submit', submitSignUp);
        requiredFields.forEach(input => input.addEventListener('input', () => input.setCustomValidity('')));
        confirmPassword.addEventListener('input', () => {
            confirmPasswordMessage.textContent = '';
            confirmPassword.setCustomValidity('');
        });
    }
});
