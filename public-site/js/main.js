// CarFix - Main JavaScript
// Autor: CarFix Development Team
// Versión: 1.0.0

'use strict';

// ===================================
// INITIALIZE AOS (Animate On Scroll)
// ===================================
AOS.init({
    duration: 800,
    easing: 'ease-in-out',
    once: true,
    offset: 100
});

// ===================================
// LOADING SCREEN
// ===================================
window.addEventListener('load', function() {
    setTimeout(function() {
        const loaderWrapper = document.querySelector('.loader-wrapper');
        if (loaderWrapper) {
            loaderWrapper.classList.add('hidden');
        }
    }, 1500);
});

// ===================================
// SWIPER SLIDER INITIALIZATION
// ===================================
const swiper = new Swiper('.swiper', {
    loop: true,
    speed: 800,
    autoplay: {
        delay: 4000,
        disableOnInteraction: false,
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },
    effect: 'fade',
    fadeEffect: {
        crossFade: true
    }
});

// ===================================
// SMOOTH SCROLL FOR ANCHOR LINKS
// ===================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ===================================
// BACK TO TOP BUTTON
// ===================================
const backToTop = document.getElementById('backToTop');

window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        backToTop.classList.add('visible');
    } else {
        backToTop.classList.remove('visible');
    }
});

backToTop.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// ===================================
// CONTACT FORM HANDLING
// ===================================
const contactForm = document.getElementById('contactForm');
const formMessage = document.getElementById('formMessage');

contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form values
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const message = document.getElementById('message').value;
    
    // Validate form fields
    if (!name || !email || !phone || !message) {
        showFormMessage('Por favor completa todos los campos requeridos.', 'error');
        return;
    }
    
    // Create mailto link
    const subject = encodeURIComponent('Solicitud de Información - CarFix');
    const body = encodeURIComponent(
        `Nombre: ${name}\n` +
        `Email: ${email}\n` +
        `Teléfono: ${phone}\n\n` +
        `Mensaje:\n${message}`
    );
    
    const mailtoLink = `mailto:markii.candiani@live.com.mx?subject=${subject}&body=${body}`;
    
    // Open email client
    window.location.href = mailtoLink;
    
    // Show success message
    showFormMessage('Se abrirá tu cliente de correo para enviar el mensaje.', 'success');
    
    // Reset form after delay
    setTimeout(function() {
        contactForm.reset();
        hideFormMessage();
    }, 3000);
});

// Helper function to show form messages
function showFormMessage(message, type) {
    formMessage.textContent = message;
    formMessage.className = `form-message ${type}`;
    formMessage.style.display = 'block';
}

// Helper function to hide form messages
function hideFormMessage() {
    formMessage.style.display = 'none';
}

// ===================================
// PARALLAX EFFECT FOR HERO SECTION
// ===================================
window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.hero-content');
    
    if (hero && scrolled < window.innerHeight) {
        hero.style.transform = `translateY(${scrolled * 0.5}px)`;
        hero.style.opacity = 1 - (scrolled / window.innerHeight);
    }
});

// ===================================
// INFO CARDS CLICK HANDLING
// ===================================
const manualCard = document.getElementById('manualCard');
const bankingCard = document.getElementById('bankingCard');
const bankingModal = document.getElementById('bankingModal');
const modalOverlay = document.getElementById('modalOverlay');
const modalClose = document.getElementById('modalClose');

// Manual Corporativo - SOLO DESCARGA PDF
if (manualCard) {
    manualCard.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Add visual feedback
        const icon = this.querySelector('i');
        const originalClass = icon.className;
        
        // Show downloading state
        icon.className = 'fas fa-spinner fa-spin';
        this.style.transform = 'translateY(-8px) scale(1.02)';
        
        // Create download link
        const link = document.createElement('a');
        link.href = 'ManualCorporativo.pdf';
        link.download = 'Manual_CarFix.pdf';
        link.target = '_blank';
        
        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Reset visual state after delay
        setTimeout(() => {
            icon.className = originalClass;
            this.style.transform = '';
        }, 1500);
        
        console.log('Manual Corporativo descargado');
    });
}

// Banking Card - Abrir Modal
if (bankingCard && bankingModal) {
    bankingCard.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openBankingModal();
    });
}

// Modal Functions
function openBankingModal() {
    if (bankingModal) {
        // Guardar posición actual del scroll
        const scrollY = window.pageYOffset;
        
        // Bloquear scroll del fondo completamente
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.overflow = 'hidden';
        
        bankingModal.classList.add('active');
        console.log('Modal datos bancarios abierto');
    }
}

function closeBankingModal() {
    if (bankingModal) {
        // Restaurar scroll del fondo
        const scrollY = document.body.style.top;
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.overflow = '';
        
        // Restaurar posición de scroll
        if (scrollY) {
            window.scrollTo(0, parseInt(scrollY || '0') * -1);
        }
        
        bankingModal.classList.remove('active');
        console.log('Modal datos bancarios cerrado');
    }
}

// Modal Close Events
if (modalClose) {
    modalClose.addEventListener('click', closeBankingModal);
}

if (modalOverlay) {
    modalOverlay.addEventListener('click', closeBankingModal);
}

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && bankingModal && bankingModal.classList.contains('active')) {
        closeBankingModal();
    }
});

// Prevent modal close when clicking inside modal container
const modalContainer = document.querySelector('.modal-container');
if (modalContainer) {
    modalContainer.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// Copy to Clipboard Function
function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(function() {
        // Visual feedback
        const originalText = button.textContent;
        button.textContent = '¡Copiado!';
        button.classList.add('copied');
        
        // Reset after 2 seconds
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
        
        console.log('Texto copiado:', text);
    }).catch(function(err) {
        console.error('Error al copiar:', err);
        // Fallback para navegadores que no soportan clipboard API
        fallbackCopyTextToClipboard(text, button);
    });
}

// Fallback copy function
function fallbackCopyTextToClipboard(text, button) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        
        // Visual feedback
        const originalText = button.textContent;
        button.textContent = '¡Copiado!';
        button.classList.add('copied');
        
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('copied');
        }, 2000);
        
        console.log('Texto copiado (fallback):', text);
    } catch (err) {
        console.error('Error en fallback copy:', err);
    }
    
    document.body.removeChild(textArea);
}

// Copy Button Event Listeners
document.addEventListener('click', function(e) {
    if (e.target.closest('.copy-button')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.copy-button');
        const textToCopy = button.getAttribute('data-copy');
        
        if (textToCopy) {
            copyToClipboard(textToCopy, button);
        }
    }
});

// ===================================
// MODAL — SOLICITAR CITA  v2
// Calendario real · API NHTSA · Autocomplete
// ===================================
(function initCitaModal() {

    var TOTAL_STEPS = 3;
    var currentStep  = 1;
    var calViewYear  = null;
    var calViewMonth = null;
    var citaData = { nombre:'', tel:'', marca:'', modelo:'', anio:'', servicio:'', precio:'', fecha:'', hora:'', dow:-1 };

    // Marcas desde el dataset local (orden alfabético ya viene del objeto)
    var MEXICO_MAKES = Object.keys(VEHICULOS_MX).sort();

    var MON_FULL = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    var DAY_FULL = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    var MON_SHT  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    // Servicios — actualizar con el Excel de Paco
    var SERVICIOS = [
        { icon: 'fas fa-tools',      name: 'Full Service',        desc: 'Mantenimiento preventivo integral' },
        { icon: 'fas fa-cog',        name: 'Clutch y Frenos',     desc: 'Discos, balatas, líquido' },
        { icon: 'fas fa-wrench',     name: 'Mecánica General',    desc: 'Motor, transmisión, enfriamiento' },
        { icon: 'fas fa-car-side',   name: 'Hojalatería',         desc: 'Carrocería, pintura, soldadura' },
        { icon: 'fas fa-road',       name: 'Suspensión',          desc: 'Amortiguadores, terminales, alineación' },
        { icon: 'fas fa-microchip',  name: 'Scanner Electrónico', desc: 'Diagnóstico computarizado' }
    ];

    var STEP_META = {
        1: { label: 'Paso 1 de 3', title: 'Tu Vehículo' },
        2: { label: 'Paso 2 de 3', title: 'Servicio' },
        3: { label: 'Paso 3 de 3', title: 'Fecha y Hora' },
        4: { label: '¡Listo!',     title: '¡Cita Programada!' }
    };

    var overlay      = document.getElementById('citaOverlay');
    var closeBtn     = document.getElementById('citaClose');
    var nextBtn      = document.getElementById('citaNext');
    var backBtn      = document.getElementById('citaBack');
    var footer       = document.getElementById('citaFooter');
    var progressFill = document.getElementById('citaProgressFill');
    var stepLabel    = document.getElementById('citaStepLabel');
    var titleEl      = document.getElementById('citaModalTitle');
    var errorBanner  = document.getElementById('citaErrorBanner');

    if (!overlay) return;

    // --- Años en select (desc: más reciente primero) ---
    (function fillYears() {
        var sel = document.getElementById('citaAnio');
        if (!sel) return;
        var cur = new Date().getFullYear();
        for (var y = cur; y >= 1985; y--) {
            var opt = document.createElement('option');
            opt.value = y; opt.textContent = y;
            sel.appendChild(opt);
        }
    })();

    // --- Combo box de marca: dropdown completo + texto libre ---
    function initMarcaAutocomplete() {
        var input    = document.getElementById('citaMarca');
        var dropdown = document.getElementById('marcaDropdown');
        var wrap     = document.getElementById('marcaAutoWrap');
        if (!input || !dropdown) return;

        function openDd(items) {
            dropdown.innerHTML = '';
            if (!items.length) { closeDd(); return; }

            items.forEach(function(make) {
                var li = document.createElement('li');
                li.className = 'cita-dropdown-item';
                li.textContent = make;
                li.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    pickMake(make);
                });
                dropdown.appendChild(li);
            });

            // Separador + "Otra marca"
            var sep = document.createElement('li');
            sep.className = 'cita-dropdown-sep';
            dropdown.appendChild(sep);

            var other = document.createElement('li');
            other.className = 'cita-dropdown-item cita-dropdown-other';
            other.innerHTML = '<i class="fas fa-pencil-alt"></i>Otra marca — escribe aquí';
            other.addEventListener('mousedown', function(e) {
                e.preventDefault();
                input.value = '';
                citaData.marca = '';
                closeDd();
                input.focus();
                // Limpiar modelo
                var sel = document.getElementById('citaModelo');
                if (sel) { sel.innerHTML = '<option value="">Escribe tu marca arriba</option>'; sel.disabled = true; }
            });
            dropdown.appendChild(other);

            dropdown.classList.add('open');
            if (wrap) wrap.classList.add('open');
        }

        function closeDd() {
            dropdown.classList.remove('open');
            if (wrap) wrap.classList.remove('open');
        }

        function pickMake(make) {
            input.value = make;
            citaData.marca = make;
            closeDd();
            var sel = document.getElementById('citaModelo');
            if (sel) { sel.innerHTML = '<option value="">Selecciona el modelo</option>'; sel.disabled = true; citaData.modelo = ''; }
            fetchModels(make);
            hideError();
        }

        // Al enfocar: muestra todas las marcas (o filtra si ya hay texto)
        input.addEventListener('focus', function() {
            var q = this.value.trim().toLowerCase();
            var items = q
                ? MEXICO_MAKES.filter(function(m) { return m.toLowerCase().indexOf(q) !== -1; })
                : MEXICO_MAKES.slice();
            openDd(items);
        });

        // Al escribir: filtra la lista en tiempo real
        input.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            var items = q
                ? MEXICO_MAKES.filter(function(m) { return m.toLowerCase().indexOf(q) !== -1; })
                : MEXICO_MAKES.slice();
            openDd(items);
        });

        // Al perder foco: cierra y guarda texto libre
        input.addEventListener('blur', function() {
            setTimeout(function() {
                closeDd();
                if (!citaData.marca) citaData.marca = input.value.trim();
            }, 200);
        });

        // Click en el input cuando ya tiene valor: reabre
        input.addEventListener('click', function() {
            if (!dropdown.classList.contains('open')) {
                var q = this.value.trim().toLowerCase();
                var items = q
                    ? MEXICO_MAKES.filter(function(m) { return m.toLowerCase().indexOf(q) !== -1; })
                    : MEXICO_MAKES.slice();
                openDd(items);
            }
        });
    }

    // --- Modelos por marca: CarQuery JSONP + fallback dataset local ---
    function fetchModels(make) {
        var sel     = document.getElementById('citaModelo');
        var spinner = document.getElementById('modeloLoading');
        if (!sel) return;

        sel.disabled = true;
        sel.innerHTML = '<option value="">Cargando modelos...</option>';
        citaData.modelo = '';
        if (spinner) spinner.classList.add('visible');

        var cbName = 'vxcb' + Date.now();
        var timer;
        var scriptEl;

        // Modelos del dataset local para esta marca
        function localModels() {
            var key = Object.keys(VEHICULOS_MX).find(function(k) {
                return k.toLowerCase() === make.toLowerCase();
            });
            return key ? VEHICULOS_MX[key].slice() : [];
        }

        // Poblar el select con lista final deduplicada + ordenada
        function populate(list) {
            clearTimeout(timer);
            if (scriptEl && scriptEl.parentNode) scriptEl.parentNode.removeChild(scriptEl);
            delete window[cbName];

            var seen = {}; var unique = [];
            list.forEach(function(m) {
                var k = m.trim();
                if (k && !seen[k.toLowerCase()]) { seen[k.toLowerCase()] = true; unique.push(k); }
            });
            unique.sort();

            sel.innerHTML = unique.length
                ? '<option value="">Selecciona el modelo</option>'
                : '<option value="">Sin modelos — escribe el modelo</option>';
            unique.forEach(function(m) {
                var o = document.createElement('option'); o.value = m; o.textContent = m;
                sel.appendChild(o);
            });
            sel.disabled = unique.length === 0;
            if (spinner) spinner.classList.remove('visible');
        }

        // Callback JSONP
        window[cbName] = function(data) {
            var api = (data.Models || []).map(function(m) { return m.model_name; });
            // Merge: API + dataset local (el local cubre modelos MX que la API no tiene)
            populate(api.concat(localModels()));
        };

        // Timeout 5s → caer al dataset local
        timer = setTimeout(function() {
            delete window[cbName];
            populate(localModels());
        }, 5000);

        // Inyectar script JSONP (sin fetch, sin CORS)
        scriptEl = document.createElement('script');
        scriptEl.src = 'https://www.carqueryapi.com/api/0.3/?cmd=getModels&make=' +
                       encodeURIComponent(make) + '&callback=' + cbName;
        scriptEl.onerror = function() {
            clearTimeout(timer);
            delete window[cbName];
            populate(localModels());
        };
        document.body.appendChild(scriptEl);
    }

    // --- Calendario mes real ---
    function buildCalendar() {
        var cal = document.getElementById('citaCalendario');
        if (!cal) return;

        var today   = new Date(); today.setHours(0,0,0,0);
        var minDate = new Date(today); minDate.setDate(today.getDate() + 1);   // mañana
        var maxDate = new Date(today); maxDate.setDate(today.getDate() + 30);  // 30 días

        if (calViewYear === null) {
            calViewYear  = minDate.getFullYear();
            calViewMonth = minDate.getMonth();
        }
        renderMonth(cal, calViewYear, calViewMonth, minDate, maxDate);
    }

    function renderMonth(container, year, month, minDate, maxDate) {
        var DOW_HDR = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

        var firstDay    = new Date(year, month, 1);
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var startOffset = (firstDay.getDay() + 6) % 7;  // 0=Lun

        var today = new Date(); today.setHours(0,0,0,0);

        // ¿Puede navegar prev/next?
        var prevMonth = new Date(year, month - 1, 1);
        var canPrev   = prevMonth >= new Date(today.getFullYear(), today.getMonth(), 1) &&
                        !(prevMonth.getFullYear() === today.getFullYear() && prevMonth.getMonth() === today.getMonth());
        var nextFirst = new Date(year, month + 1, 1);
        var canNext   = nextFirst <= maxDate;

        container.innerHTML = '';

        // Header mes
        var hdr = document.createElement('div');
        hdr.className = 'cal-header';
        hdr.innerHTML =
            '<button class="cal-nav-btn" id="calPrev" type="button"' + (canPrev?'':' disabled') + '>' +
                '<i class="fas fa-chevron-left"></i></button>' +
            '<span class="cal-month-title">' + MON_FULL[month] + ' ' + year + '</span>' +
            '<button class="cal-nav-btn" id="calNext" type="button"' + (canNext?'':' disabled') + '>' +
                '<i class="fas fa-chevron-right"></i></button>';
        container.appendChild(hdr);

        // Grid
        var grid = document.createElement('div');
        grid.className = 'cal-grid';

        // Encabezados día semana
        DOW_HDR.forEach(function(d) {
            var cell = document.createElement('div');
            cell.className = 'cal-cell cal-dow' + (d === 'Dom' ? ' cal-dow-sun' : '');
            cell.textContent = d;
            grid.appendChild(cell);
        });

        // Celdas vacías de offset
        for (var e = 0; e < startOffset; e++) {
            var emp = document.createElement('div');
            emp.className = 'cal-cell cal-empty';
            grid.appendChild(emp);
        }

        // Días del mes
        for (var d = 1; d <= daysInMonth; d++) {
            var date    = new Date(year, month, d);
            var isSun   = date.getDay() === 0;
            var isPast  = date < minDate;
            var isFar   = date > maxDate;
            var isToday = date.getTime() === today.getTime();

            var ds = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            var isSelected = citaData.fecha === ds;

            var clickable = !isSun && !isPast && !isFar;
            var cell = document.createElement(clickable ? 'button' : 'div');

            var cls = ['cal-cell', 'cal-day'];
            if (isSun)      cls.push('cal-closed');
            if (isPast || isFar) cls.push('cal-out');
            if (isToday)    cls.push('cal-today');
            if (isSelected) cls.push('selected');
            cell.className = cls.join(' ');
            cell.textContent = d;

            if (clickable) {
                cell.type = 'button';
                (function(dateStr, dow, el) {
                    el.addEventListener('click', function() {
                        container.querySelectorAll('.cal-day.selected').forEach(function(c) { c.classList.remove('selected'); });
                        el.classList.add('selected');
                        citaData.fecha = dateStr;
                        citaData.dow   = dow;
                        citaData.hora  = '';
                        buildTimeSlots(dow);
                        hideError();
                    });
                })(ds, date.getDay(), cell);
            }

            grid.appendChild(cell);
        }

        container.appendChild(grid);

        // Navegación
        var prevBtn = container.querySelector('#calPrev');
        var nextBtn = container.querySelector('#calNext');
        if (prevBtn && canPrev) prevBtn.addEventListener('click', function() {
            calViewMonth--;
            if (calViewMonth < 0) { calViewMonth = 11; calViewYear--; }
            renderMonth(container, calViewYear, calViewMonth, minDate, maxDate);
        });
        if (nextBtn && canNext) nextBtn.addEventListener('click', function() {
            calViewMonth++;
            if (calViewMonth > 11) { calViewMonth = 0; calViewYear++; }
            renderMonth(container, calViewYear, calViewMonth, minDate, maxDate);
        });
    }

    // ─── Lista de precios Full Service (con/sin bujías) ────────
    // c = con bujías, s = sin bujías
    var PDB = {
      chevrolet:[
        {m:'aveo clasico',c:2900,s:2900},{m:'aveo ng',c:3500,s:2900},
        {m:'spark ng',c:3800,s:2900},{m:'spark',c:2900,s:2900},
        {m:'astra',c:2900,s:2900},{m:'beat',c:2900,s:2900},
        {m:'chevy',c:2900,s:2900},{m:'malibu',c:3600,s:3000},
        {m:'optra',c:3000,s:3000},{m:'sonic',c:3500,s:3000},
        {m:'trax',c:3500,s:3000},{m:'cruze',c:3500,s:3000},
        {m:'suburban 8',c:5400,s:4400},{m:'suburban 6',c:4400,s:3700},
        {m:'traverse',c:4400,s:3700},{m:'onix',c:3300,s:2900},
        {m:'captiva',c:3300,s:2900},{m:'equinox',c:3600,s:3200},
      ],
      dodge:[
        {m:'attitude',c:3500,s:2900},{m:'verna',c:2900,s:2900},
        {m:'neon',c:3600,s:2900},{m:'vision',c:3000,s:2900},
        {m:'journey',c:3800,s:3200},{m:'atos',c:2900,s:2900},
        {m:'caravan',c:2900,s:2900},{m:'voyager',c:4000,s:3200},
        {m:'ram',c:6000,s:5000},
      ],
      ford:[
        {m:'f150',c:4400,s:4400},{m:'f250',c:4800,s:4800},
        {m:'f350',c:5000,s:5000},{m:'explorer',c:4300,s:3300},
        {m:'escape',c:3800,s:3000},{m:'focus',c:3500,s:3000},
        {m:'fiesta',c:3000,s:3000},{m:'edge',c:4300,s:3300},
        {m:'ranger',c:3000,s:3000},{m:'ecosport',c:2900,s:2900},
        {m:'figo',c:3100,s:2800},{m:'transit',c:3700,s:2900},
        {m:'fusion',c:3800,s:3000},
      ],
      nissan:[
        {m:'altima',c:3600,s:3000},{m:'march',c:3600,s:2900},
        {m:'np300',c:3500,s:2900},{m:'platina',c:2900,s:2900},
        {m:'sentra',c:3600,s:3200},{m:'tsuru',c:2900,s:2900},
        {m:'versa',c:3800,s:3000},{m:'x-trail',c:3800,s:3200},
        {m:'xtrail',c:3800,s:3200},{m:'note',c:3800,s:3000},
        {m:'armada',c:5500,s:4500},{m:'tiida',c:3600,s:3000},
        {m:'kicks',c:3800,s:3000},{m:'frontier',c:3500,s:2900},
      ],
      renault:[
        {m:'clio',c:2900,s:2900},{m:'duster',c:2900,s:2900},
        {m:'stepway',c:2900,s:2900},{m:'sandero',c:2900,s:2900},
        {m:'fluence',c:3000,s:3000},{m:'logan',c:3000,s:3000},
        {m:'megane',c:3700,s:3000},{m:'megan',c:3700,s:3000},
        {m:'kwid',c:3700,s:3000},
      ],
      seat:[
        {m:'ibiza',c:4500,s:4400},{m:'leon',c:4100,s:3900},
        {m:'toledo',c:3900,s:3000},{m:'altea',c:3900,s:3000},
        {m:'cordoba',c:3000,s:3000},{m:'cupra',c:5000,s:4000},
      ],
      volkswagen:[
        {m:'gol',c:2900,s:2900},{m:'pointer',c:2900,s:2900},
        {m:'saveiro',c:2900,s:2900},{m:'lupo',c:2800,s:2800},
        {m:'jetta clasico',c:2900,s:2900},{m:'jetta 2.5',c:3700,s:3000},
        {m:'jetta 1.4',c:3950,s:3000},{m:'jetta',c:3950,s:3000},
        {m:'beetle',c:4500,s:3500},{m:'bora',c:3700,s:3000},
        {m:'golf',c:4500,s:3500},{m:'tcross',c:4100,s:3100},
        {m:'tiguan',c:4500,s:3500},{m:'taos',c:3800,s:3000},
        {m:'passat',c:4500,s:3500},{m:'polo',c:3800,s:3200},
        {m:'vento',c:3600,s:3100},{m:'up',c:3400,s:2800},
      ],
      mazda:[
        {m:'mazda 2',c:3800,s:2900},{m:'mazda 3',c:3800,s:2900},
        {m:'cx3',c:3800,s:2900},{m:'cx-3',c:3800,s:2900},
        {m:'cx5',c:3800,s:2900},{m:'cx-5',c:3800,s:2900},
        {m:'cx9',c:3800,s:2900},{m:'cx-9',c:3800,s:2900},
      ],
      honda:[
        {m:'civic',c:3900,s:3000},{m:'accord 4',c:3800,s:3000},
        {m:'accord 6',c:4300,s:3300},{m:'accord',c:3800,s:3000},
        {m:'cr-v',c:3800,s:3000},{m:'crv',c:3800,s:3000},
        {m:'odyssey',c:3800,s:3200},{m:'odissey',c:3800,s:3200},
        {m:'fit',c:3700,s:2900},{m:'city',c:3700,s:2900},
      ],
      mitsubishi:[
        {m:'lancer',c:3400,s:2900},{m:'mirage',c:2900,s:2900},
        {m:'outlander',c:3500,s:2900},{m:'eclipse',c:3600,s:3000},
        {m:'montero',c:3600,s:3000},
      ],
      toyota:[
        {m:'yaris',c:3500,s:2900},{m:'rav4',c:3800,s:3000},
        {m:'corolla',c:3400,s:2900},{m:'camry',c:3400,s:2900},
        {m:'sienna',c:4300,s:3300},{m:'avanza',c:3500,s:3000},
        {m:'hilux',c:3500,s:3000},{m:'tacoma',c:3500,s:3000},
      ],
      mini:[
        {m:'countryman',c:4800,s:3800},{m:'cooper s',c:4800,s:3800},
        {m:'cooper',c:4500,s:3500},
      ],
      bmw:[
        {m:'118',c:4900,s:3900},{m:'120',c:5200,s:4200},
        {m:'220',c:5200,s:4200},{m:'230',c:5400,s:4400},
        {m:'318',c:5100,s:4100},{m:'320',c:5100,s:4100},
        {m:'328',c:5200,s:4200},{m:'330',c:5200,s:4200},
        {m:'340',c:7000,s:6000},{m:'420',c:5100,s:4100},
        {m:'x1',c:5100,s:4100},{m:'x3',c:7500,s:6500},
        {m:'x5',c:7600,s:6600},
      ],
      audi:[
        {m:'a1',c:4300,s:3300},{m:'a3',c:4400,s:3200},
        {m:'a4',c:4400,s:3200},{m:'a5',c:4400,s:3200},
        {m:'q2',c:4300,s:3300},{m:'q3',c:4400,s:3200},
        {m:'q5',c:4400,s:3200},{m:'q7',c:7800,s:6800},
        {m:'s3',c:5100,s:4100},{m:'s4',c:5100,s:4100},
        {m:'s5',c:5100,s:4100},{m:'tt',c:5100,s:4100},
      ],
      kia:[
        {m:'rio',c:3900,s:3000},{m:'forte',c:3900,s:3000},
        {m:'optima',c:3900,s:3000},{m:'sportage',c:3900,s:3000},
        {m:'sorento',c:4200,s:3200},{m:'sorrento',c:4200,s:3200},
        {m:'stonic',c:3900,s:3000},{m:'seltos',c:3900,s:3000},
      ],
      hyundai:[
        {m:'i10',c:3100,s:3100},{m:'grand i10',c:3100,s:3100},
        {m:'accent',c:3500,s:3500},{m:'creta',c:3600,s:3600},
        {m:'elantra',c:3600,s:3600},{m:'tucson',c:3600,s:3600},
        {m:'tuckson',c:3600,s:3600},
      ],
      suzuki:[
        {m:'swift',c:3700,s:2950},{m:'ignis',c:3420,s:2900},
        {m:'ertiga',c:3850,s:3000},{m:'vitara',c:3850,s:3000},
        {m:'s-cross',c:3850,s:3000},
      ],
      mercedes:[
        {m:'cla 250',c:4900,s:3900},{m:'cla250',c:4900,s:3900},
        {m:'cla 280',c:4300,s:3300},
      ],
      fiat:[
        {m:'palio',c:3420,s:2900},
      ],
      peugeot:[
        {m:'301',c:3900,s:3000},{m:'208',c:3600,s:2800},
        {m:'308',c:4200,s:3200},{m:'3008',c:4200,s:3200},
        {m:'partner',c:3600,s:2800},
      ],
    };

    // ─── Lookup de precio ───────────────────────────────────────
    function normTxt(s) {
        return (s||'').toLowerCase()
            .replace(/[áàäâã]/g,'a').replace(/[éèëê]/g,'e')
            .replace(/[íìïî]/g,'i').replace(/[óòöôõ]/g,'o')
            .replace(/[úùüû]/g,'u').replace(/ñ/g,'n')
            .replace(/[-_]/g,' ').trim();
    }

    function normBrand(marca) {
        var s = normTxt(marca);
        if (s === 'volkswagen' || s === 'vw') return 'volkswagen';
        if (s.indexOf('mercedes') !== -1)       return 'mercedes';
        if (s.indexOf('mini') !== -1)            return 'mini';
        return s;
    }

    function buscarPrecio(marca, modelo) {
        if (!marca || !modelo) return null;
        var bKey = normBrand(marca);
        var entries = PDB[bKey];
        if (!entries) return null;

        var mKey = normTxt(modelo);
        var mWords = mKey.split(/\s+/).filter(function(w) { return w.length > 1; });

        var matches = entries.filter(function(e) {
            var eKey = normTxt(e.m);
            if (mKey === eKey || mKey.indexOf(eKey) !== -1 || eKey.indexOf(mKey) !== -1) return true;
            var eWords = eKey.split(/\s+/).filter(function(w) { return w.length > 1; });
            return mWords.filter(function(w) { return eKey.indexOf(w) !== -1; }).length >= 1 ||
                   eWords.filter(function(w) { return mKey.indexOf(w) !== -1; }).length >= 1;
        });

        if (!matches.length) return null;

        var cVals = matches.map(function(e){ return e.c; });
        var sVals = matches.map(function(e){ return e.s; });
        var cMin = Math.min.apply(null, cVals), cMax = Math.max.apply(null, cVals);
        var sMin = Math.min.apply(null, sVals), sMax = Math.max.apply(null, sVals);

        return { cMin:cMin, cMax:cMax, sMin:sMin, sMax:sMax, rango: cMin!==cMax||sMin!==sMax };
    }

    function fmtPrecio(min, max) {
        var f = function(n) { return '$' + n.toLocaleString('es-MX'); };
        return min === max ? f(min) : 'Desde ' + f(min);
    }

    // ─── Construir cards de servicio con precio ─────────────────
    function buildPricedServices() {
        var grid = document.getElementById('citaServiciosGrid');
        if (!grid) return;
        grid.innerHTML = '';
        citaData.servicio = '';
        citaData.precio = '';

        var precio = (citaData.marca && citaData.modelo)
            ? buscarPrecio(citaData.marca, citaData.modelo)
            : null;

        grid.className = 'cita-servicios-grid precio-mode';

        var defs;
        if (precio) {
            defs = [
                {
                    key: 'Full Service',
                    icon: 'fas fa-wrench',
                    name: 'Full Service',
                    sub: 'Sin bujías incluidas',
                    desc: 'Aceite sintético · Filtros · Revisión 21 puntos',
                    precio: fmtPrecio(precio.sMin, precio.sMax),
                    precioVal: fmtPrecio(precio.sMin, precio.sMax)
                },
                {
                    key: 'Full Service + Bujías',
                    icon: 'fas fa-cogs',
                    name: 'Full Service + Bujías',
                    sub: 'Bujías originales incluidas',
                    desc: 'Todo lo anterior + bujías nuevas originales',
                    precio: fmtPrecio(precio.cMin, precio.cMax),
                    precioVal: fmtPrecio(precio.cMin, precio.cMax)
                }
            ];
        } else {
            defs = [
                {
                    key: 'Full Service',
                    icon: 'fas fa-wrench',
                    name: 'Full Service',
                    sub: 'Sin bujías',
                    desc: 'Aceite sintético · Filtros · Revisión 21 puntos',
                    precio: null, precioVal: null
                },
                {
                    key: 'Full Service + Bujías',
                    icon: 'fas fa-cogs',
                    name: 'Full Service + Bujías',
                    sub: 'Bujías originales incluidas',
                    desc: 'Todo lo anterior + bujías nuevas originales',
                    precio: null, precioVal: null
                }
            ];
        }

        defs.forEach(function(srv) {
            var card = document.createElement('button');
            card.type = 'button';
            card.className = 'cita-servicio-card' + (srv.precio ? '' : ' precio-indefinido');
            card.dataset.servicio = srv.key;

            var precioHtml = srv.precio
                ? '<div class="cita-srv-price">' + srv.precio + '</div>'
                : '<div class="cita-srv-price-undef">Precio a definir · te contactamos</div>';

            card.innerHTML =
                '<div class="cita-srv-left">' +
                    '<i class="' + srv.icon + ' cita-srv-icon"></i>' +
                    '<div class="cita-srv-info">' +
                        '<div class="cita-srv-name">' + srv.name + '</div>' +
                        '<div class="cita-srv-desc">' + srv.sub + ' · ' + srv.desc + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="cita-srv-right">' +
                    precioHtml +
                    '<div class="cita-srv-check"><i class="fas fa-check"></i></div>' +
                '</div>';

            card.addEventListener('click', function() {
                grid.querySelectorAll('.cita-servicio-card').forEach(function(c) { c.classList.remove('selected'); });
                card.classList.add('selected');
                citaData.servicio = srv.key;
                citaData.precio = srv.precioVal || 'A definir';
                hideError();
            });
            grid.appendChild(card);
        });
    }

    // --- Chips de horario
    function buildTimeSlots(dow) {
        var wrap = document.getElementById('citaHorariosWrap');
        var cont = document.getElementById('citaHorarios');
        if (!wrap || !cont) return;
        var slots = (dow === 6)
            ? ['09:00','10:00','11:00','12:00']
            : ['09:00','10:00','11:00','12:00','14:00','15:00','16:00','17:00'];
        cont.innerHTML = '';
        slots.forEach(function(slot) {
            var chip = document.createElement('button');
            chip.type = 'button'; chip.className = 'cita-hora-chip'; chip.textContent = slot;
            chip.addEventListener('click', function() {
                cont.querySelectorAll('.cita-hora-chip').forEach(function(c) { c.classList.remove('selected'); });
                chip.classList.add('selected');
                citaData.hora = slot;
                hideError();
            });
            cont.appendChild(chip);
        });
        wrap.style.animation = 'none'; wrap.offsetHeight; wrap.style.animation = '';
        wrap.style.display = 'block';
    }

    // Pantalla de éxito
    function buildSuccess() {
        var resumen = document.getElementById('citaResumen');
        var waBtn   = document.getElementById('citaWABtn');
        if (!resumen) return;
        var fechaFmt = '';
        if (citaData.fecha) {
            var p = citaData.fecha.split('-');
            var fd = new Date(parseInt(p[0],10), parseInt(p[1],10)-1, parseInt(p[2],10));
            fechaFmt = DAY_FULL[fd.getDay()] + ', ' + fd.getDate() + ' de ' + MON_FULL[fd.getMonth()];
        }
        var auto = [citaData.marca, citaData.modelo, citaData.anio].filter(Boolean).join(' ') || 'No especificado';
        resumen.innerHTML =
            resRow('Nombre',   citaData.nombre)  + resRow('Teléfono', citaData.tel) +
            resRow('Vehículo', auto)              + resRow('Servicio', citaData.servicio) +
            resRow('Precio',   citaData.precio || 'Por confirmar') +
            resRow('Fecha',    fechaFmt)          + resRow('Hora',     citaData.hora);
        if (waBtn) {
            var precioLinea = citaData.precio && citaData.precio !== 'A definir'
                ? '\n• Precio cotizado: ' + citaData.precio
                : '\n• Precio: Por definir (favor de confirmar)';
            var msg = 'Hola CarFix, quisiera confirmar mi cita:\n\n' +
                '• Nombre: ' + citaData.nombre + '\n• Teléfono: ' + citaData.tel +
                '\n• Vehículo: ' + auto + '\n• Servicio: ' + citaData.servicio +
                precioLinea +
                '\n• Fecha: ' + fechaFmt + '\n• Hora: ' + citaData.hora;
            waBtn.href = 'https://wa.me/5215513422917?text=' + encodeURIComponent(msg);
        }
        var circle = document.querySelector('.cita-check-circle');
        var path   = document.querySelector('.cita-check-path');
        if (circle) { circle.style.animation='none'; circle.getBoundingClientRect(); circle.style.animation=''; }
        if (path)   { path.style.animation='none';   path.getBoundingClientRect();   path.style.animation=''; }
    }

    function resRow(key, val) {
        return '<div class="cita-resumen-row"><span class="cita-resumen-key">' + key +
               '</span><span class="cita-resumen-val">' + (val||'—') + '</span></div>';
    }

    // Navegación
    function gotoStep(newStep, dir) {
        var activeEl = document.querySelector('.cita-step.active');
        var newEl    = document.getElementById('citaStep' + newStep);
        if (!newEl) return;
        var enterClass = dir==='forward' ? 'enter-right' : dir==='back' ? 'enter-left' : '';
        var exitClass  = dir==='forward' ? 'exit-left'   : dir==='back' ? 'exit-right' : '';
        if (activeEl && dir !== 'none') {
            activeEl.classList.remove('active'); activeEl.classList.add(exitClass);
            var toClean = activeEl;
            setTimeout(function() { toClean.classList.remove(exitClass); }, 220);
        } else if (activeEl) { activeEl.classList.remove('active'); }
        var delay = dir !== 'none' ? 160 : 0;
        setTimeout(function() {
            newEl.classList.remove('enter-right','enter-left','exit-left','exit-right','active');
            if (enterClass) newEl.classList.add(enterClass);
            newEl.classList.add('active');
            setTimeout(function() { newEl.classList.remove('enter-right','enter-left'); }, 360);
            var body = document.getElementById('citaBody');
            if (body) body.scrollTop = 0;
        }, delay);
        currentStep = newStep;
        updateProgress(newStep); updateFooter(newStep); updateHeader(newStep);
        if (newStep === 2) buildPricedServices();
        if (newStep === 3) { buildCalendar(); var hw=document.getElementById('citaHorariosWrap'); if(hw) hw.style.display='none'; }
        if (newStep === 4) buildSuccess();
    }

    function updateHeader(step) {
        var m = STEP_META[step]; if (!m) return;
        if (stepLabel) stepLabel.textContent = m.label;
        if (titleEl)   titleEl.textContent   = m.title;
    }
    function updateProgress(step) {
        var pct = step <= TOTAL_STEPS ? step/TOTAL_STEPS*100 : 100;
        if (progressFill) progressFill.style.width = pct + '%';
        document.querySelectorAll('.cita-dot').forEach(function(dot) {
            var ds = parseInt(dot.dataset.step, 10);
            dot.classList.remove('active','done');
            if (ds === step && step <= TOTAL_STEPS) dot.classList.add('active');
            else if (ds < step || step > TOTAL_STEPS) dot.classList.add('done');
        });
    }
    function updateFooter(step) {
        if (!footer) return;
        if (step === 4) { footer.style.display='none'; return; }
        footer.style.display = '';
        if (backBtn) { step===1 ? backBtn.classList.add('hidden') : backBtn.classList.remove('hidden'); }
        if (nextBtn) nextBtn.innerHTML = step===TOTAL_STEPS
            ? 'Confirmar <i class="fas fa-check"></i>'
            : 'Continuar <i class="fas fa-chevron-right"></i>';
    }

    // Validación
    function validateStep(step) {
        if (step === 1) {
            var nombre = (document.getElementById('citaNombre').value||'').trim();
            var tel    = (document.getElementById('citaTel').value||'').trim();
            if (!nombre) { shakeInput('citaNombre'); showError('Ingresa tu nombre completo'); return false; }
            if (!tel)    { shakeInput('citaTel');    showError('Ingresa tu número de teléfono'); return false; }
            citaData.nombre = nombre;
            citaData.tel    = tel;
            citaData.marca  = (document.getElementById('citaMarca').value||'').trim();
            // Modelo puede ser select o texto
            var modeloEl = document.getElementById('citaModelo');
            citaData.modelo = modeloEl ? (modeloEl.value||'').trim() : '';
            citaData.anio   = document.getElementById('citaAnio').value;
        }
        if (step === 2 && !citaData.servicio) { showError('Selecciona el servicio que necesitas'); return false; }
        if (step === 3) {
            if (!citaData.fecha) { showError('Selecciona una fecha para tu cita'); return false; }
            if (!citaData.hora)  { showError('Selecciona el horario de tu preferencia'); return false; }
        }
        hideError(); return true;
    }
    function shakeInput(id) {
        var el = document.getElementById(id); if (!el) return;
        el.classList.add('error'); el.focus();
        setTimeout(function() { el.classList.remove('error'); }, 600);
    }
    function showError(msg) { if (errorBanner) { errorBanner.textContent=msg; errorBanner.classList.add('show'); } }
    function hideError()    { if (errorBanner) errorBanner.classList.remove('show'); }

    // Abrir / cerrar
    function openModal() {
        citaData = { nombre:'', tel:'', marca:'', modelo:'', anio:'', servicio:'', precio:'', fecha:'', hora:'', dow:-1 };
        // Reset calendario
        calViewYear = null; calViewMonth = null;
        // Reset inputs texto
        ['citaNombre','citaTel','citaMarca'].forEach(function(id) {
            var el = document.getElementById(id); if (el) el.value = '';
        });
        var anioSel = document.getElementById('citaAnio'); if (anioSel) anioSel.value = '';
        // Reset modelo select
        var modeloSel = document.getElementById('citaModelo');
        if (modeloSel) {
            modeloSel.innerHTML = '<option value="">Selecciona la marca primero</option>';
            modeloSel.disabled = true;
        }
        // Reset servicios
        document.querySelectorAll('.cita-servicio-card').forEach(function(c) { c.classList.remove('selected'); });
        hideError();
        gotoStep(1, 'none');
        document.body.style.overflow = 'hidden';
        overlay.removeAttribute('aria-hidden');
        overlay.classList.add('open');
    }
    function closeModal() {
        overlay.classList.add('closing');
        setTimeout(function() {
            overlay.classList.remove('open','closing');
            overlay.setAttribute('aria-hidden','true');
            document.body.style.overflow = '';
        }, 270);
    }

    // Event listeners
    document.querySelectorAll('#btnSolicitarCita, [data-cita-open]').forEach(function(el) {
        el.addEventListener('click', function(e) { e.preventDefault(); openModal(); });
    });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) { if (e.target===overlay) closeModal(); });
    document.addEventListener('keydown', function(e) {
        if (e.key==='Escape' && overlay.classList.contains('open')) closeModal();
    });
    if (nextBtn) nextBtn.addEventListener('click', function() {
        if (!validateStep(currentStep)) return;
        gotoStep(currentStep < TOTAL_STEPS ? currentStep+1 : 4, 'forward');
    });
    if (backBtn) backBtn.addEventListener('click', function() {
        if (currentStep > 1) gotoStep(currentStep-1, 'back');
    });
    var nuevaBtn = document.getElementById('citaNuevaBtn');
    if (nuevaBtn) nuevaBtn.addEventListener('click', openModal);

    // Init autocomplete (se ejecuta una vez al cargar)
    initMarcaAutocomplete();

    updateProgress(1); updateFooter(1);

})();

// ===================================
// CONSOLE BRANDING
// ===================================
console.log(
    '%c CARFIX ',
    'background: #b7ff00; color: #0a0a0a; font-size: 20px; font-weight: bold; padding: 10px;'
);
console.log(
    '%c Servicio Automotriz Premium ',
    'background: #0a0a0a; color: #b7ff00; font-size: 14px; padding: 5px;'
);
console.log(
    '%c Developed with ❤️ for CarFix ',
    'color: #a0a0a0; font-size: 12px;'
);

// ===================================
// PERFORMANCE MONITORING (Optional)
// ===================================
window.addEventListener('load', function() {
    // Log page load time
    const loadTime = window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart;
    console.log(`Page loaded in ${loadTime}ms`);
});

// ===================================
// ERROR HANDLING
// ===================================
window.addEventListener('error', function(e) {
    console.error('An error occurred:', e.error);
});

// ===================================
// PREVENT FORM RESUBMISSION
// ===================================
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}