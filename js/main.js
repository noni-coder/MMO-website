/* ============================================
   ENSEMBLE MMO — JAVASCRIPT COMPLET
   Navigation, Carrousel, Formulaire AJAX,
   Validation, CSRF, Sakura, Scroll Reveal
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // NAVIGATION
    // ==========================================
    const nav = document.getElementById('mainNav');
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');

    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 60);
    });

    navToggle.addEventListener('click', () => {
        navToggle.classList.toggle('active');
        navLinks.classList.toggle('open');
        document.body.style.overflow = navLinks.classList.contains('open') ? 'hidden' : '';
    });

    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navToggle.classList.remove('active');
            navLinks.classList.remove('open');
            document.body.style.overflow = '';
        });
    });

    // ==========================================
    // CARROUSEL DES ACCROCHES
    // ==========================================
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.carousel-dot');
    let currentSlide = 0;
    let carouselInterval;

    function goToSlide(index) {
        slides[currentSlide].classList.remove('active');
        dots[currentSlide].classList.remove('active');
        currentSlide = index;
        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
    }

    function nextSlide() {
        goToSlide((currentSlide + 1) % slides.length);
    }

    function startCarousel() {
        carouselInterval = setInterval(nextSlide, 4500);
    }

    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            clearInterval(carouselInterval);
            goToSlide(parseInt(dot.dataset.slide));
            startCarousel();
        });
    });

    startCarousel();

    // ==========================================
    // SCROLL REVEAL
    // ==========================================
    const revealElements = document.querySelectorAll('[data-reveal]');

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.dataset.delay) || 0;
                setTimeout(() => entry.target.classList.add('revealed'), delay);
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealElements.forEach(el => revealObserver.observe(el));

    // ==========================================
    // SMOOTH SCROLL
    // ==========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offset = 72;
                window.scrollTo({
                    top: target.getBoundingClientRect().top + window.scrollY - offset,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ==========================================
    // BOUTON "REJOINDRE" → PRÉ-REMPLIR LE SELECT
    // ==========================================
    document.querySelectorAll('[data-prefill-subject]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Le smooth scroll se fait via le href="#contact"
            // On pré-remplit le select après un délai pour le scroll
            setTimeout(() => {
                const select = document.getElementById('formSubject');
                if (select) {
                    select.value = btn.dataset.prefillSubject;
                    // Trigger un changement visuel
                    select.dispatchEvent(new Event('change'));
                }
            }, 600);
        });
    });

    // ==========================================
    // CSRF TOKEN — chargé au démarrage
    // ==========================================
    let csrfToken = '';

    async function fetchCsrfToken() {
        try {
            const resp = await fetch('php/csrf-token.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            const data = await resp.json();
            csrfToken = data.token || '';
            const tokenField = document.getElementById('csrfToken');
            if (tokenField) tokenField.value = csrfToken;
        } catch (err) {
            console.warn('CSRF token non disponible (normal en local sans PHP):', err.message);
        }
    }

    fetchCsrfToken();

    // ==========================================
    // VALIDATION E-MAIL (côté client)
    // ==========================================
    const EMAIL_REGEX = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/;

    // Domaines jetables connus (blacklist côté client)
    const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email',
        'yopmail.com', 'sharklasers.com', 'trashmail.com', 'fakeinbox.com',
        'maildrop.cc', 'dispostable.com', 'getnada.com', 'temp-mail.org',
        'guerrillamailblock.com', 'grr.la', 'mailnesia.com', '10minutemail.com',
        'mohmal.com', 'emailondeck.com', 'tempail.com', 'burnermail.io',
        'crazymailing.com', 'discard.email', 'discardmail.com', 'jetable.org',
    ];

    function validateEmailClient(email) {
        if (!email) return 'L\'adresse e-mail est requise.';
        if (!EMAIL_REGEX.test(email)) return 'L\'adresse e-mail n\'est pas valide.';
        if (email.length > 254) return 'L\'adresse e-mail est trop longue.';

        const domain = email.split('@')[1]?.toLowerCase();
        if (DISPOSABLE_DOMAINS.includes(domain)) {
            return 'Les adresses e-mail temporaires ne sont pas acceptées.';
        }

        return '';
    }

    // ==========================================
    // FORMULAIRE DE CONTACT — VALIDATION & ENVOI
    // ==========================================
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const formStatus = document.getElementById('formStatus');

    if (!form) return;

    // Références aux champs
    const fields = {
        name:    { el: document.getElementById('formName'),    errorEl: document.getElementById('errorName') },
        email:   { el: document.getElementById('formEmail'),   errorEl: document.getElementById('errorEmail') },
        subject: { el: document.getElementById('formSubject'), errorEl: document.getElementById('errorSubject') },
        message: { el: document.getElementById('formMessage'), errorEl: document.getElementById('errorMessage') },
    };

    // Validation d'un champ individuel
    function validateField(name) {
        const { el, errorEl } = fields[name];
        const val = el.value.trim();
        let error = '';

        switch (name) {
            case 'name':
                if (!val) error = 'Le nom est requis.';
                else if (val.length < 2) error = 'Le nom doit contenir au moins 2 caractères.';
                else if (val.length > 100) error = 'Le nom est trop long.';
                break;
            case 'email':
                error = validateEmailClient(val);
                break;
            case 'subject':
                if (!val) error = 'Veuillez sélectionner un objet.';
                break;
            case 'message':
                if (!val) error = 'Le message est requis.';
                else if (val.length < 10) error = `Encore ${10 - val.length} caractère(s) minimum.`;
                else if (val.length > 5000) error = 'Le message est trop long (max. 5000 caractères).';
                break;
        }

        const group = el.closest('.form-group');
        if (error) {
            group.classList.add('has-error');
            group.classList.remove('is-valid');
            errorEl.textContent = error;
        } else {
            group.classList.remove('has-error');
            if (val) group.classList.add('is-valid');
            errorEl.textContent = '';
        }

        return !error;
    }

    // Validation en temps réel (au blur et à la saisie)
    Object.keys(fields).forEach(name => {
        const { el } = fields[name];
        el.addEventListener('blur', () => validateField(name));
        el.addEventListener('input', () => {
            // Valider après que l'utilisateur ait commencé à corriger
            if (el.closest('.form-group').classList.contains('has-error')) {
                validateField(name);
            }
        });
    });

    // Validation complète
    function validateAll() {
        let valid = true;
        Object.keys(fields).forEach(name => {
            if (!validateField(name)) valid = false;
        });
        return valid;
    }

    // Afficher le statut
    function showStatus(type, message) {
        formStatus.className = 'form-status visible ' + type;
        formStatus.textContent = message;
    }

    function hideStatus() {
        formStatus.className = 'form-status';
        formStatus.textContent = '';
    }

    // Loader du bouton
    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitBtn.querySelector('.btn-text').style.display = loading ? 'none' : '';
        submitBtn.querySelector('.btn-loader').style.display = loading ? 'inline-flex' : 'none';
        submitBtn.querySelector('.btn-icon').style.display = loading ? 'none' : '';
    }

    // ─── SOUMISSION AJAX ───
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideStatus();

        // 1. Validation côté client
        if (!validateAll()) {
            showStatus('error', 'Veuillez corriger les erreurs ci-dessus.');
            // Scroll vers la première erreur
            const firstError = form.querySelector('.has-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        // 2. Préparer les données
        const payload = {
            name: fields.name.el.value.trim(),
            email: fields.email.el.value.trim(),
            subject: fields.subject.el.value,
            message: fields.message.el.value.trim(),
            csrf_token: csrfToken,
            website_url: document.getElementById('website_url')?.value || ''
        };

        // 3. Envoyer
        setLoading(true);

        try {
            const response = await fetch('php/send-mail.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                showStatus('success', '✓ ' + data.message);
                form.reset();
                // Reset les états visuels
                Object.keys(fields).forEach(name => {
                    const group = fields[name].el.closest('.form-group');
                    group.classList.remove('has-error', 'is-valid');
                    fields[name].errorEl.textContent = '';
                });
                // Mettre à jour le token CSRF si fourni
                if (data.new_csrf) {
                    csrfToken = data.new_csrf;
                    document.getElementById('csrfToken').value = csrfToken;
                }
            } else {
                showStatus('error', data.message || 'Une erreur est survenue.');
            }
        } catch (err) {
            console.error('Erreur envoi:', err);
            showStatus('error',
                'Impossible de joindre le serveur. Vérifiez votre connexion ou contactez-nous par téléphone au 06 21 00 77 35.'
            );
        } finally {
            setLoading(false);
        }
    });

    // ==========================================
    // PÉTALES DE SAKURA (Canvas)
    // ==========================================
    const canvas = document.getElementById('sakura-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let petals = [];
        const PETAL_COUNT = 30;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        class Petal {
            constructor() {
                this.reset();
                this.y = Math.random() * canvas.height;
            }

            reset() {
                this.x = Math.random() * canvas.width;
                this.y = -20;
                this.size = Math.random() * 12 + 5;
                this.speedY = Math.random() * 0.8 + 0.3;
                this.speedX = Math.random() * 0.6 - 0.3;
                this.rotation = Math.random() * Math.PI * 2;
                this.rotationSpeed = (Math.random() - 0.5) * 0.02;
                this.opacity = Math.random() * 0.5 + 0.35;
                this.wobble = Math.random() * Math.PI * 2;
                this.wobbleSpeed = Math.random() * 0.02 + 0.01;
            }

            update() {
                this.y += this.speedY;
                this.wobble += this.wobbleSpeed;
                this.x += this.speedX + Math.sin(this.wobble) * 0.5;
                this.rotation += this.rotationSpeed;
                if (this.y > canvas.height + 20) this.reset();
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);
                ctx.globalAlpha = this.opacity;

                // Lueur douce autour du pétale
                ctx.shadowColor = 'rgba(255,126,179,0.4)';
                ctx.shadowBlur = this.size * 1.2;

                ctx.beginPath();
                ctx.moveTo(0, -this.size);
                ctx.bezierCurveTo(
                    this.size * 0.6, -this.size * 0.6,
                    this.size * 0.6, this.size * 0.6,
                    0, this.size
                );
                ctx.bezierCurveTo(
                    -this.size * 0.6, this.size * 0.6,
                    -this.size * 0.6, -this.size * 0.6,
                    0, -this.size
                );
                ctx.fillStyle = this.opacity > 0.6 ? '#FFB3D4' : '#FF9EC0';
                ctx.fill();
                ctx.restore();
            }
        }

        function initPetals() {
            petals = [];
            for (let i = 0; i < PETAL_COUNT; i++) petals.push(new Petal());
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            petals.forEach(p => { p.update(); p.draw(); });
            requestAnimationFrame(animate);
        }

        resizeCanvas();
        initPetals();
        animate();

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => { resizeCanvas(); initPetals(); }, 200);
        });
    }

});
