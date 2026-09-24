'use strict';

/* ============================================================
   پارس سازه و آفیس — رفتارهای رابط کاربری
============================================================= */

const CSRF_TOKEN = (document.querySelector('meta[name="csrf"]') || {}).content || '';

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
function toFaDigits(n) {
  return String(n).replace(/\d/g, (d) => FA_DIGITS[+d]);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

/* ------------------------------------------------------------
   مودال‌ها
------------------------------------------------------------ */
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('open');
  document.body.classList.add('no-scroll');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('open');
  document.body.classList.remove('no-scroll');
}

// بستن مودال با کلیک روی پس‌زمینه
document.querySelectorAll('.modal-overlay').forEach((overlay) => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.classList.remove('open');
      document.body.classList.remove('no-scroll');
    }
  });
});

// کلید Escape برای بستن مودال و منو
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.modal-overlay.open').forEach((m) => {
    m.classList.remove('open');
    document.body.classList.remove('no-scroll');
  });
  closeDrawer();
});

/* ------------------------------------------------------------
   منوی موبایل (درور)
------------------------------------------------------------ */
function openDrawer() {
  const drawer = document.getElementById('mobileDrawer');
  const btn = document.getElementById('menuBtn');
  if (!drawer) return;
  drawer.classList.add('open');
  drawer.setAttribute('aria-hidden', 'false');
  if (btn) btn.setAttribute('aria-expanded', 'true');
  document.body.classList.add('no-scroll');
}

function closeDrawer() {
  const drawer = document.getElementById('mobileDrawer');
  const btn = document.getElementById('menuBtn');
  if (!drawer) return;
  drawer.classList.remove('open');
  drawer.setAttribute('aria-hidden', 'true');
  if (btn) btn.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('no-scroll');
}

const menuBtn = document.getElementById('menuBtn');
if (menuBtn) {
  menuBtn.addEventListener('click', () => {
    const drawer = document.getElementById('mobileDrawer');
    if (drawer.classList.contains('open')) closeDrawer();
    else openDrawer();
  });
}
document.querySelectorAll('[data-close-drawer]').forEach((el) => {
  el.addEventListener('click', closeDrawer);
});

/* ------------------------------------------------------------
   سیستم اعلان (toast)
------------------------------------------------------------ */
function ensureToastWrap() {
  let wrap = document.getElementById('toastWrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'toastWrap';
    wrap.setAttribute('aria-live', 'polite');
    document.body.appendChild(wrap);
  }
  return wrap;
}

function showToast(message, opts = {}) {
  const wrap = ensureToastWrap();
  const type = opts.type || 'info';
  const icons = { success: '✓', error: '!', info: 'i' };
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.setAttribute('role', 'status');

  const icon = document.createElement('span');
  icon.className = 'toast-icon';
  icon.textContent = icons[type] || 'i';

  const msg = document.createElement('span');
  msg.className = 'toast-msg';
  msg.textContent = message;

  t.appendChild(icon);
  t.appendChild(msg);

  if (opts.action && opts.action.href) {
    const a = document.createElement('a');
    a.className = 'toast-action';
    a.href = opts.action.href;
    a.textContent = opts.action.label || 'مشاهده';
    t.appendChild(a);
  }

  const close = document.createElement('button');
  close.className = 'toast-close';
  close.setAttribute('aria-label', 'بستن اعلان');
  close.textContent = '✕';
  close.addEventListener('click', () => dismiss(t));
  t.appendChild(close);

  wrap.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  if (!opts.sticky) {
    setTimeout(() => dismiss(t), 5000);
  }
}

function dismiss(el) {
  if (!el || el.classList.contains('leaving')) return;
  el.classList.add('leaving');
  setTimeout(() => el.remove(), 320);
}

// پیام اولیه‌ای که سرور رندر کرده
window.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('siteToast');
  if (toast) {
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.add('leaving');
      setTimeout(() => toast.remove(), 400);
    }, 5000);
  }
});

/* ------------------------------------------------------------
   افزودن به سبد خرید (AJAX بدون رفرش)
------------------------------------------------------------ */
function updateCartBadge(count) {
  const badge = document.getElementById('cartBadge');
  if (!badge) return;
  badge.textContent = toFaDigits(count);
  badge.dataset.count = count;
  badge.classList.remove('pop');
  void badge.offsetWidth; // ری‌استارت انیمیشن
  badge.classList.add('pop');
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-add-cart');
  if (!btn) return;
  e.preventDefault();
  if (btn.classList.contains('busy') || btn.classList.contains('disabled')) return;

  btn.classList.add('busy');
  const label = btn.querySelector('.add-label');
  const original = label ? label.textContent : '';

  fetch(btn.getAttribute('href'), {
    method: 'POST',
    headers: {
      'X-Requested-With': 'fetch',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'csrf_token=' + encodeURIComponent(CSRF_TOKEN),
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) throw new Error(data.error || 'خطا در افزودن به سبد');
      updateCartBadge(data.cart);
      if (label) label.textContent = '✓ افزوده شد';
      btn.classList.add('added');
      showToast('کالا به سبد سفارش اضافه شد', {
        type: 'success',
        action: { label: 'مشاهده سبد', href: 'index.php?page=cart' },
      });
      setTimeout(() => {
        btn.classList.remove('added', 'busy');
        if (label) label.textContent = original;
      }, 1500);
      if (data.stock_left === 0) {
        btn.classList.add('disabled');
        btn.classList.remove('js-add-cart');
        if (label) label.textContent = 'ناموجود';
      }
    })
    .catch((err) => {
      btn.classList.remove('busy');
      if (label) label.textContent = original;
      showToast(err.message || 'خطا در ارتباط با سرور', { type: 'error' });
    });
});

/* ------------------------------------------------------------
   سایه هدر هنگام اسکرول + دکمه بازگشت به بالا
------------------------------------------------------------ */
const header = document.getElementById('siteHeader');
const toTop = document.getElementById('toTop');

function onScroll() {
  if (header) header.classList.toggle('scrolled', window.scrollY > 10);
  if (toTop) toTop.classList.toggle('visible', window.scrollY > 480);
}
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

if (toTop) {
  toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}
