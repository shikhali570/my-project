/* ==========================================================================
   پارس سازه و آفیس | اسکریپت‌های رابط کاربری (نسخه ۲.۱)
   ========================================================================== */
'use strict';

const PS = {
  csrf: document.querySelector('input[name="csrf_token"]')?.value || '',
  base: 'api.php',
};

/* ------------------------------------------------------------ ابزارهای کمکی */
/** جایگزینی امن متن در HTML (برای نتایج AJAX) */
function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

/** عدد انگلیسی را به فارسی تبدیل می‌کند */
function toFa(value) {
  return String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

/* ------------------------------------------------------------ درخواست AJAX */
async function api(doAction, params = {}) {
  const url = new URL(PS.base, window.location.href);
  url.searchParams.set('do', doAction);
  const body = new FormData();
  body.append('csrf_token', PS.csrf);
  Object.entries(params).forEach(([k, v]) => body.append(k, v));

  const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
  // پاسخ‌های خطا هم JSON هستند (مثلاً «برای ذخیره علاقه‌مندی‌ها وارد شوید»)
  let data = null;
  try { data = await res.json(); } catch (err) { data = null; }
  if (!data) throw new Error('خطا در ارتباط با سرور');
  return data;
}

/* ------------------------------------------------------------------ توست */
const TOAST_MS = { error: 9000, normal: 5000 };

function dismissToast(el) {
  if (!el || el.classList.contains('is-leaving')) return;
  clearTimeout(el._timer);
  el.classList.add('is-leaving');
  setTimeout(() => el.remove(), 350);
}

function armToast(el) {
  clearTimeout(el._timer);
  const ms = el.classList.contains('error') ? TOAST_MS.error : TOAST_MS.normal;
  el._timer = setTimeout(() => dismissToast(el), ms);
}

/** پیام‌ها را فعال می‌کند؛ با ماوس روی پیام یا فوکوس روی آن، محو شدن متوقف می‌شود */
function initToasts(root = document) {
  root.querySelectorAll('.toast-bar').forEach((el) => {
    if (el.dataset.ready) return;
    el.dataset.ready = '1';
    armToast(el);
    el.addEventListener('pointerenter', () => clearTimeout(el._timer));
    el.addEventListener('pointerleave', () => armToast(el));
    el.addEventListener('focusin', () => clearTimeout(el._timer));
    el.addEventListener('focusout', () => armToast(el));
  });
}

function toast(message, type = 'info') {
  let wrap = document.querySelector('.toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'toast-wrap no-print';
    wrap.setAttribute('aria-live', 'polite');
    document.body.appendChild(wrap);
  }
  const el = document.createElement('div');
  el.className = `toast-bar ${type}`;
  el.setAttribute('role', type === 'error' ? 'alert' : 'status');

  const text = document.createElement('span');
  text.className = 'toast-text';
  text.textContent = message;

  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'toast-close';
  close.setAttribute('aria-label', 'بستن پیام');
  close.textContent = '×';

  el.append(text, close);
  wrap.appendChild(el);
  initToasts(wrap);
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.toast-close');
  if (btn) dismissToast(btn.closest('.toast-bar'));
});

/* ------------------------------------------------------- مودال و منوها */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'flex';
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

window.addEventListener('click', (e) => {
  if (e.target.classList && e.target.classList.contains('modal-overlay')) {
    e.target.style.display = 'none';
  }
  // بستن منوهای کشویی با کلیک بیرون
  document.querySelectorAll('details.drop[open]').forEach((d) => {
    if (!d.contains(e.target)) d.removeAttribute('open');
  });
});

/* ------------------------------------------------ شمارنده سبد و علاقه‌مندی */
function updateCartCount(count) {
  document.querySelectorAll('.badge-count').forEach((b) => (b.textContent = toFa(count)));
  document.querySelectorAll('.cart-btn').forEach((a) => {
    a.setAttribute('aria-label', `سبد سفارش، ${toFa(count)} قلم`);
  });
}

function setFavState(btn, active) {
  btn.textContent = active ? '★' : '☆';
  btn.classList.toggle('active', active);
  btn.setAttribute('aria-pressed', String(active));
  const name = btn.dataset.name || '';
  btn.setAttribute('aria-label', (active ? 'حذف از علاقه‌مندی‌ها: ' : 'افزودن به علاقه‌مندی‌ها: ') + name);
  btn.title = active ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها';
}

/* ------------------------------------------------ افزودن به سبد و علاقه‌مندی (AJAX) */
document.addEventListener('submit', async (e) => {
  const form = e.target;
  if (!(form instanceof HTMLFormElement)) return;

  const hiddenAction = form.querySelector('input[name="action"]');
  if (!hiddenAction) return;

  if (hiddenAction.value === 'add_cart' && form.dataset.ajax !== 'off' && form.closest('.pro-card, .add-to-cart')) {
    e.preventDefault();
    const id = form.querySelector('input[name="id"]')?.value;
    const qty = form.querySelector('input[name="qty"]')?.value || 1;
    try {
      const data = await api('add_cart', { id, qty });
      if (data.ok) {
        toast(`${data.message} (تعداد سبد: ${toFa(data.count)})`, 'success');
        updateCartCount(data.count);
      } else {
        toast(data.error || 'خطا در افزودن به سبد', 'error');
      }
    } catch (err) {
      toast('ارتباط با سرور برقرار نشد؛ لطفاً دوباره تلاش کنید.', 'error');
    }
    return;
  }

  if (hiddenAction.value === 'favorite_toggle' && form.closest('.pro-card')) {
    e.preventDefault();
    const btn = form.querySelector('button');
    const id = form.querySelector('input[name="id"]')?.value;
    btn.disabled = true;
    try {
      const data = await api('favorite', { id });
      if (data.ok) {
        setFavState(btn, !!data.active);
        toast(data.message, 'success');
      } else {
        toast(data.error || 'ابتدا وارد حساب خریدار شوید.', 'error');
      }
    } catch (err) {
      toast('ارتباط با سرور برقرار نشد؛ لطفاً دوباره تلاش کنید.', 'error');
    } finally {
      btn.disabled = false;
    }
  }
});

/* ------------------------------------------------------ استپر تعداد کالا */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-step]');
  if (!btn) return;
  const input = btn.parentElement.querySelector('input[type="number"]');
  if (!input) return;
  const step = parseInt(btn.dataset.step, 10);
  const min = parseInt(input.min || '1', 10);
  const max = parseInt(input.max || '9999', 10);
  let val = (parseInt(input.value, 10) || min) + step;
  val = Math.max(min, Math.min(max, val));
  input.value = val;
});

/* ---------------------------------------------- تأیید عملیات‌های حساس */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  e.preventDefault();
  if (!window.confirm(btn.dataset.confirm)) return;
  const formId = btn.dataset.form;
  if (formId) {
    const form = document.getElementById(formId);
    if (form) form.submit();
  } else if (btn.dataset.url) {
    window.location.href = btn.dataset.url;
  }
});

/* ---------------------------------------------------- کپی متن در کلیپ‌بورد */
function copyText(text, btn) {
  const done = () => {
    if (btn) {
      const old = btn.textContent;
      btn.textContent = '✅ کپی شد';
      setTimeout(() => (btn.textContent = old), 1800);
    } else {
      toast('در حافظه کپی شد.', 'success');
    }
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
  } else {
    fallbackCopy(text, done);
  }
}
function fallbackCopy(text, done) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); done(); } catch (e) { /* نادیده */ }
  ta.remove();
}

/* -------------------------------------------------- جست‌وجوی سریع کالا */
function renderSuggestions(items, q) {
  if (!items.length) {
    return '<div class="empty-mini">کالایی با این عبارت پیدا نشد.</div>'
      + '<a class="suggest-foot" href="index.php?page=rfq">ثبت استعلام قیمت این کالا</a>';
  }
  const rows = items.map((it) => `
    <a class="suggest-item" href="${esc(it.url)}">
      <span class="s-ico" aria-hidden="true">${esc(it.icon)}</span>
      <span class="s-body">
        <strong>${esc(it.name)}</strong>
        <small>${esc(it.brand)} · <span class="${it.available ? '' : 'out'}">${it.available ? 'موجود' : 'ناموجود'}</span></small>
      </span>
      <span class="s-price">${esc(it.price)}</span>
    </a>`).join('');
  return rows + `<a class="suggest-foot" href="index.php?page=home&q=${encodeURIComponent(q)}">نمایش همه نتایج «${esc(q)}»</a>`;
}

function initLiveSearch() {
  const input = document.querySelector('.header-search input[name="q"]');
  if (!input) return;
  // لیست پیشنهادها خارج از فرمِ دارای overflow:hidden قرار می‌گیرد
  const wrap = input.closest('.search-wrap') || input.parentElement;
  const box = document.createElement('div');
  box.className = 'search-suggest';
  box.setAttribute('aria-label', 'پیشنهاد کالا');
  wrap.appendChild(box);

  let timer = null;
  let seq = 0;
  const hide = () => { box.style.display = 'none'; };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) {
      hide();
      return;
    }
    timer = setTimeout(async () => {
      const mine = ++seq;
      try {
        const res = await fetch(`api.php?do=product_search&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (mine !== seq) return; // پاسخ قدیمی
        box.innerHTML = renderSuggestions(data.items || [], q);
        box.style.display = 'block';
      } catch (err) {
        hide();
      }
    }, 260);
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hide();
  });
  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) hide();
  });
}

/* ------------------------------------------------------ اعلان‌های لحظه‌ای */
function initNotificationPoll() {
  const bell = document.querySelector('[data-notify-bell]');
  if (!bell) return;
  setInterval(async () => {
    try {
      const res = await fetch('api.php?do=notifications');
      const data = await res.json();
      if (!data.ok) return;
      const badge = bell.querySelector('.badge-count');
      if (data.unread > 0 && badge && badge.textContent !== toFa(data.unread)) {
        toast('اعلان جدیدی در پنل شما ثبت شد.', 'info');
        badge.textContent = toFa(data.unread);
      }
    } catch (err) { /* نادیده */ }
  }, 60000);
}

/* --------------------------------------------------- فیلتر خودکار فرم‌ها */
function initAutoSubmitFilters() {
  document.querySelectorAll('form.filter-bar select, form.filter-bar input[type="checkbox"]').forEach((el) => {
    el.addEventListener('change', () => el.form && el.form.submit());
  });
}

/* ---------------------------------------------------- درج خودکار حساب‌ها */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fill-phone]');
  if (!btn) return;
  const form = btn.closest('form') || document.querySelector('form.auth-form');
  if (!form) return;
  const phone = form.querySelector('input[name="phone"]');
  const pass = form.querySelector('input[name="password"]');
  if (phone) phone.value = btn.dataset.fillPhone;
  if (pass) pass.value = btn.dataset.fillPass;
  const submitBtn = form.querySelector('button[type="submit"]');
  if (submitBtn) submitBtn.focus();
});

/* --------------------------------------------------------------- راه‌اندازی */
document.addEventListener('DOMContentLoaded', () => {
  initLiveSearch();
  initAutoSubmitFilters();
  initNotificationPoll();
  initToasts();

  // فرم‌های دارای data-ajax="off" به صورت معمولی ارسال شوند
  document.querySelectorAll('form[data-ajax="off"]').forEach((f) => (f.dataset.ajax = 'off'));
});

/* ----------------------------------------------- تأیید پیش از لغو سفارش (مدیر) */
document.addEventListener('submit', (ev) => {
  const form = ev.target;
  if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm-canceled')) return;
  const select = form.querySelector('select[name="status"]');
  if (!select || select.value !== 'canceled' || select.dataset.original === 'canceled') return;
  if (!window.confirm('این سفارش به «لغو شده» تغییر می‌کند. ادامه می‌دهید؟')) {
    ev.preventDefault();
  }
});
