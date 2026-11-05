document.addEventListener('DOMContentLoaded', () => {
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
        const cards = Array.from(productGrid.querySelectorAll('.product-card'));
        categories.forEach(button => button.addEventListener('click', () => {
            const category = button.dataset.category || 'all';
            categories.forEach(filter => {
                const active = filter === button;
                filter.classList.toggle('active', active);
                filter.setAttribute('aria-pressed', String(active));
            });
            cards.forEach(card => {
                const wrapper = card.closest('.col, [class*="col-"]') || card;
                wrapper.classList.toggle('d-none', category !== 'all' && card.dataset.category !== category);
            });
        }));
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
