// Servicio Gudiño — main.js
// Identidad: Racing / Rojo agresivo
// Versión: 2026-05-20

'use strict';

// ============================================================
// 1. LOADER
//    - Aplica delays a las letras del nombre
//    - Desaparece a los 2.5s
// ============================================================
(function initLoader() {
    // Delays escalonados para las letras
    const letters = document.querySelectorAll('.loader-name span');
    letters.forEach(function (letter, i) {
        // Saltar el span del espacio (clase loader-space)
        if (letter.classList.contains('loader-space')) return;
        letter.style.animationDelay = (i * 0.06) + 's';
    });

    // Ocultar loader a los 2.5s
    window.addEventListener('load', function () {
        setTimeout(function () {
            var wrapper = document.getElementById('loaderWrapper');
            if (wrapper) {
                wrapper.classList.add('hidden');
            }
        }, 2500);
    });
})();

// ============================================================
// 2. AOS — Animate On Scroll
// ============================================================
AOS.init({
    duration: 750,
    easing: 'ease-in-out',
    once: true,
    offset: 80
});

// ============================================================
// 3. NAVBAR — scroll effect + hamburger menu
// ============================================================
(function initNavbar() {
    var navbar    = document.getElementById('navbar');
    var hamburger = document.getElementById('navHamburger');
    var navLinks  = document.getElementById('navLinks');

    // Scroll: añadir clase 'scrolled' a partir de 50px
    window.addEventListener('scroll', function () {
        if (navbar) {
            if (window.pageYOffset > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    }, { passive: true });

    // Hamburger toggle
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function () {
            hamburger.classList.toggle('open');
            navLinks.classList.toggle('open');
        });

        // Cerrar menú al hacer click en un link
        navLinks.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                hamburger.classList.remove('open');
                navLinks.classList.remove('open');
            });
        });

        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function (e) {
            if (!navbar.contains(e.target)) {
                hamburger.classList.remove('open');
                navLinks.classList.remove('open');
            }
        });
    }
})();

// ============================================================
// 4. SMOOTH SCROLL para links de ancla
// ============================================================
document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
        var targetId = this.getAttribute('href');
        if (targetId === '#') return;
        var target = document.querySelector(targetId);
        if (target) {
            e.preventDefault();
            // Compensar la altura del navbar (64px)
            var navbarH = 64;
            var targetY = target.getBoundingClientRect().top + window.pageYOffset - navbarH;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }
    });
});

// ============================================================
// 5. SWIPER — slider de trabajos con autoplay
// ============================================================
var sgSwiper = new Swiper('.sg-swiper', {
    loop: true,
    speed: 700,
    autoplay: {
        delay: 4500,
        disableOnInteraction: false
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true
    },
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev'
    },
    effect: 'fade',
    fadeEffect: {
        crossFade: true
    }
});

// ============================================================
// 6. COUNTER ANIMATION para stats
//    IntersectionObserver + easing cuadrático
// ============================================================
(function initCounters() {
    var statNumbers = document.querySelectorAll('.stat-number');
    if (!statNumbers.length) return;

    // Duración total de la animación en ms
    var DURATION = 2000;

    function easeOutQuad(t) {
        return t * (2 - t);
    }

    function animateCounter(el, target) {
        var start = null;

        function step(timestamp) {
            if (!start) start = timestamp;
            var elapsed = timestamp - start;
            var progress = Math.min(elapsed / DURATION, 1);
            var easedProgress = easeOutQuad(progress);
            var current = Math.floor(easedProgress * target);
            el.textContent = current.toLocaleString('es-MX');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('es-MX');
            }
        }

        requestAnimationFrame(step);
    }

    var observer = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                var el     = entry.target;
                var target = parseInt(el.getAttribute('data-target'), 10);
                if (!isNaN(target)) {
                    animateCounter(el, target);
                }
                obs.unobserve(el);
            }
        });
    }, { threshold: 0.4 });

    statNumbers.forEach(function (el) {
        observer.observe(el);
    });
})();

// ============================================================
// 7. BACK TO TOP
// ============================================================
(function initBackToTop() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;

    window.addEventListener('scroll', function () {
        if (window.pageYOffset > 400) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    }, { passive: true });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();

// ============================================================
// 8. FORMULARIO DE CONTACTO
//    preventDefault + validación + apertura de mailto
// ============================================================
(function initContactForm() {
    var form        = document.getElementById('contactForm');
    var msgEl       = document.getElementById('formMessage');
    if (!form || !msgEl) return;

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className   = 'form-message ' + type;
        msgEl.style.display = 'block';
    }

    function hideMsg() {
        msgEl.style.display = 'none';
        msgEl.className = 'form-message';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMsg();

        var name    = (document.getElementById('name').value    || '').trim();
        var email   = (document.getElementById('email').value   || '').trim();
        var phone   = (document.getElementById('phone').value   || '').trim();
        var message = (document.getElementById('message').value || '').trim();

        // Validación básica
        if (!name || !email || !phone || !message) {
            showMsg('Por favor completa todos los campos requeridos.', 'error');
            return;
        }

        // Validación de email
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showMsg('Por favor ingresa un correo electrónico válido.', 'error');
            return;
        }

        // Abrir cliente de correo
        var subject = encodeURIComponent('Solicitud de información — Servicio Gudiño');
        var body    = encodeURIComponent(
            'Nombre: ' + name + '\n' +
            'Email: ' + email + '\n' +
            'Teléfono: ' + phone + '\n\n' +
            'Mensaje:\n' + message
        );
        var mailto = 'mailto:contacto@serviciogudino.com?subject=' + subject + '&body=' + body;
        window.location.href = mailto;

        showMsg('Se abrirá tu cliente de correo para enviar el mensaje. ¡Gracias!', 'success');

        setTimeout(function () {
            form.reset();
            hideMsg();
        }, 4000);
    });
})();

// ============================================================
// 9. CONSOLE BRANDING
// ============================================================
console.log(
    '%c SERVICIO GUDIÑO ',
    'background: #CC0000; color: #ffffff; font-size: 18px; font-weight: bold; padding: 10px 20px; font-family: Arial Black;'
);
console.log(
    '%c Mecánica de Alto Rendimiento ',
    'background: #111111; color: #CC0000; font-size: 13px; padding: 6px 20px;'
);
