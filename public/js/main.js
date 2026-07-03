/* ======================================================
   CloudVM Pro — Main JavaScript
   ====================================================== */

// ===== Theme System =====
const ThemeManager = {
  init() {
    const saved = localStorage.getItem('theme') || 'light';
    this.apply(saved);
    document.getElementById('themeToggle')?.addEventListener('click', () => this.toggle());
  },
  apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
  },
  toggle() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    this.apply(current === 'dark' ? 'light' : 'dark');
  }
};

// ===== Three.js Particle Background =====
const ParticleBackground = {
  scene: null, camera: null, renderer: null,
  particles: null, frame: 0,

  init() {
    const canvas = document.getElementById('bg-canvas');
    if (!canvas || typeof THREE === 'undefined') return;

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    this.camera.position.z = 50;

    this.renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    this.renderer.setSize(window.innerWidth, window.innerHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    this.createParticles();
    this.animate();
    window.addEventListener('resize', () => this.resize());
  },

  createParticles() {
    const count = window.innerWidth < 768 ? 80 : 200;
    const geo = new THREE.BufferGeometry();
    const positions = new Float32Array(count * 3);
    const sizes = new Float32Array(count);

    for (let i = 0; i < count; i++) {
      positions[i * 3] = (Math.random() - 0.5) * 160;
      positions[i * 3 + 1] = (Math.random() - 0.5) * 90;
      positions[i * 3 + 2] = (Math.random() - 0.5) * 40;
      sizes[i] = Math.random() * 2 + 0.5;
    }

    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geo.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

    const mat = new THREE.PointsMaterial({
      color: 0x2563eb, size: 0.5, transparent: true,
      opacity: 0.4, sizeAttenuation: true
    });

    this.particles = new THREE.Points(geo, mat);
    this.scene.add(this.particles);

    // Connection lines
    const lineMat = new THREE.LineBasicMaterial({ color: 0x2563eb, opacity: 0.06, transparent: true });
    for (let i = 0; i < 30; i++) {
      const pts = [
        new THREE.Vector3((Math.random() - 0.5) * 160, (Math.random() - 0.5) * 90, 0),
        new THREE.Vector3((Math.random() - 0.5) * 160, (Math.random() - 0.5) * 90, 0)
      ];
      const lineGeo = new THREE.BufferGeometry().setFromPoints(pts);
      this.scene.add(new THREE.Line(lineGeo, lineMat));
    }
  },

  animate() {
    requestAnimationFrame(() => this.animate());
    this.frame++;
    if (this.particles) {
      this.particles.rotation.y = this.frame * 0.0005;
      this.particles.rotation.x = this.frame * 0.0002;
    }
    this.renderer.render(this.scene, this.camera);
  },

  resize() {
    this.camera.aspect = window.innerWidth / window.innerHeight;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(window.innerWidth, window.innerHeight);
  }
};

// ===== Scroll Reveal =====
const ScrollReveal = {
  init() {
    const obs = new IntersectionObserver((entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) {
          setTimeout(() => e.target.classList.add('visible'), i * 60);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
  }
};

// ===== Counter Animation =====
const CounterAnimation = {
  init() {
    const obs = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          this.animate(e.target);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.5 });
    document.querySelectorAll('.counter').forEach(el => obs.observe(el));
  },
  animate(el) {
    const target = parseFloat(el.dataset.target);
    const isFloat = target % 1 !== 0;
    const duration = 1800;
    const start = performance.now();
    const update = (now) => {
      const p = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - p, 4);
      const val = target * ease;
      el.textContent = isFloat ? val.toFixed(1) : Math.floor(val).toLocaleString();
      if (p < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  }
};

// ===== Magnetic Effect =====
const MagneticEffect = {
  init() {
    document.querySelectorAll('.magnetic').forEach(el => {
      el.addEventListener('mousemove', (e) => {
        const rect = el.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        el.style.transform = `translate(${x * 0.2}px, ${y * 0.2}px)`;
      });
      el.addEventListener('mouseleave', () => {
        el.style.transform = '';
      });
    });
  }
};

// ===== Navbar Scroll =====
const NavbarScroll = {
  init() {
    const nav = document.getElementById('navbar');
    if (!nav) return;
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });
  }
};

// ===== Mobile Menu =====
const MobileMenu = {
  init() {
    const btn = document.getElementById('mobileToggle');
    const menu = document.getElementById('mobileMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', () => {
      menu.classList.toggle('open');
    });
  }
};

// ===== Pricing Toggle =====
const PricingToggle = {
  init() {
    const toggle = document.getElementById('billingToggle');
    const monthlyLabel = document.getElementById('monthlyLabel');
    const yearlyLabel = document.getElementById('yearlyLabel');
    if (!toggle) return;

    toggle.addEventListener('change', () => {
      const isYearly = toggle.checked;
      monthlyLabel?.classList.toggle('active', !isYearly);
      yearlyLabel?.classList.toggle('active', isYearly);

      document.querySelectorAll('.price-amount').forEach(el => {
        const price = isYearly ? el.dataset.yearly : el.dataset.monthly;
        if (price) el.textContent = price;
      });
    });
  }
};

// ===== Simulated Ping =====
const PingSimulator = {
  pings: { 'ping-gz': [3,5,7], 'ping-sh': [2,4,6], 'ping-hk': [7,10,14], 'ping-jp': [38,45,52], 'ping-la': [115,125,135], 'ping-sg': [48,56,65] },
  init() {
    Object.entries(this.pings).forEach(([id, range]) => {
      const el = document.getElementById(id);
      if (el) {
        const ping = range[Math.floor(Math.random() * range.length)];
        el.textContent = ping;
      }
    });
  }
};

// ===== Notification System =====
window.showNotification = function(title, msg, type = 'info') {
  const notif = document.createElement('div');
  notif.className = 'notification';
  notif.innerHTML = `
    <div class="notif-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</div>
    <div><div class="notif-title">${title}</div><div class="notif-msg">${msg}</div></div>
    <button onclick="this.parentElement.classList.remove('show')" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:18px;margin-left:auto">×</button>
  `;
  document.body.appendChild(notif);
  requestAnimationFrame(() => notif.classList.add('show'));
  setTimeout(() => { notif.classList.remove('show'); setTimeout(() => notif.remove(), 400); }, 4000);
};

// ===== Sidebar Active =====
const SidebarActive = {
  init() {
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar-item').forEach(item => {
      const href = item.getAttribute('href') || '';
      if (href && path.includes(href.split('/').pop().split('.')[0])) {
        item.classList.add('active');
      }
    });
  }
};

// ===== Ticket helper =====
window.openTicket = function() {
  window.location.href = 'views/user/tickets.html';
};

// ===== Init =====
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  NavbarScroll.init();
  MobileMenu.init();
  ScrollReveal.init();
  CounterAnimation.init();
  MagneticEffect.init();
  PricingToggle.init();
  PingSimulator.init();
  SidebarActive.init();

  // Only load Three.js particles on hero page
  if (document.getElementById('bg-canvas')) {
    ParticleBackground.init();
  }
});
