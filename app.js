/* ==========================================================================
   پارس سازه و آفیس | اسکریپت‌های رابط کاربری
   ========================================================================== */
'use strict';

const PS = {
  csrf: document.querySelector('input[name="csrf_token"]')?.value || '',
  base: 'api.php',
};

/* ------------------------------------------------------------ درخواست AJAX */
async function api(doAction, params = {}, options = {}) {
  const url = new URL(PS.base, window.location.href);
  url.searchParams.set('do', doAction);
  const body = new FormData();
  body.append('csrf_token', PS.csrf);
  Object.entries(params).forEach(([k, v]) => body.append(k, v));

  const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'fetch' } });
  if (!res.ok) throw new Error('خطا در ارتباط با سرور');
  return res.json();
}

/* ------------------------------------------------------------------ توست */
function toast(message, type = 'info') {
  let wrap = document.querySelector('.toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'toast-wrap no-print';
    document.body.appendChild(wrap);
  }
  const el = document.createElement('div');
  el.className = `toast-bar ${type}`;
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.transition = 'opacity .35s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 350);
  }, 3800);
}

/* ------------------------------------------------------- مودال و تأیید */
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

/* ------------------------------------------------------- افزودن به سبد (AJAX) */
document.addEventListener('submit', async (e) => {
  const form = e.target;
  if (!(form instanceof HTMLFormElement)) return;

  // فرم دکمه‌های تأیید حذف
  const hiddenAction = form.querySelector('input[name="action"]');

  if (hiddenAction && form.dataset.ajax !== 'off' && hiddenAction.value === 'add_cart' && form.closest('.pro-card, .add-to-cart')) {
    e.preventDefault();
    const id = form.querySelector('input[name="id"]')?.value;
    const qty = form.querySelector('input[name="qty"]')?.value || 1;
    try {
      const data = await api('add_cart', { id, qty });
      if (data.ok) {
        toast(data.message + ' (تعداد سبد: ' + data.count + ')', 'success');
        document.querySelectorAll('.badge-count').forEach((b) => (b.textContent = data.count));
      } else {
        toast(data.error || 'خطا در افزودن به سبد', 'error');
      }
    } catch (err) {
      toast('ارتباط با سرور برقرار نشد؛ از دکمه معمولی فرم استفاده کنید.', 'error');
      form.submit();
    }
    return;
  }

  // فرم علاقه‌مندی در کارت کالا
  if (hiddenAction && hiddenAction.value === 'favorite_toggle' && form.closest('.pro-card')) {
    e.preventDefault();
    const id = form.querySelector('input[name="id"]')?.value;
    try {
      const data = await api('favorite', { id });
      if (data.ok) {
        const btn = form.querySelector('button');
        btn.textContent = data.active ? '★' : '☆';
        btn.classList.toggle('active', !!data.active);
        toast(data.message, 'success');
      } else {
        toast(data.error || 'ابتدا وارد حساب خریدار شوید.', 'error');
      }
    } catch (err) {
      toast('خطا در ارتباط', 'error');
    }
  }
});

/* ------------------------------------------------------ استپر تعداد کالا */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-step]');
  if (!btn) return;
  const input = btn.parentElement.querySelector('input[type=number]');
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
  try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
  ta.remove();
}

/* -------------------------------------------------- جست‌وجوی سریع کالا */
function initLiveSearch() {
  const input = document.querySelector('.header-search input[name=q]');
  if (!input) return;
  const box = document.createElement('div');
  box.className = 'search-suggest';
  box.style.cssText = 'position:absolute;background:#fff;border:1px solid var(--g200);border-radius:12px;box-shadow:var(--shadow-lg);z-index:900;display:none;max-height:340px;overflow:auto;padding:6px;min-width:300px';
  input.parentElement.style.position = 'relative';
  input.parentElement.appendChild(box);

  let timer = null;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) {
      box.style.display = 'none';
      return;
    }
    timer = setTimeout(async () => {
      try {
        const res = await fetch(`api.php?do=product_search&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.items || !data.items.length) {
          box.innerHTML = '<div class="empty-mini">کالایی یافت نشد</div>';
        } else {
          box.innerHTML = data.items.map((it) => `
            <a href="${it.url}" style="display:flex;gap:10px;align-items:center;padding:8px 10px;border-radius:8px;font-size:12.5px">
              <span style="font-size:20px">${it.icon}</span>
              <span style="flex:1"><strong style="display:block">${it.name}</strong>
              <small style="color:var(--g500)">${it.brand} — ${it.available ? 'موجود' : 'ناموجود'}</small></span>
              <span style="font-weight:800;white-space:nowrap">${it.price}</span>
            </a>`).join('');
        }
        box.style.display = 'block';
        box.style.width = input.parentElement.offsetWidth + 'px';
        box.style.top = '100%';
      } catch (err) { /* ignore */ }
    }, 260);
  });

  document.addEventListener('click', (e) => {
    if (!input.parentElement.contains(e.target)) box.style.display = 'none';
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
      if (data.unread > 0 && badge && badge.textContent !== String(data.unread)) {
        toast('اعلان جدیدی در پنل شما ثبت شد.', 'info');
        badge.textContent = data.unread;
      }
    } catch (err) { /* ignore */ }
  }, 60000);
}

/* --------------------------------------------------- فیلتر خودکار فرم‌ها */
function initAutoSubmitFilters() {
  document.querySelectorAll('form.filter-bar select, form.filter-bar input[type=checkbox]').forEach((el) => {
    el.addEventListener('change', () => el.form && el.form.submit());
  });
}

/* ---------------------------------------------------- درج خودکار حساب‌ها */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fill-phone]');
  if (!btn) return;
  const form = btn.closest('form') || document.querySelector('form.auth-form');
  if (!form) return;
  form.querySelector('input[name=phone]').value = btn.dataset.fillPhone;
  form.querySelector('input[name=password]').value = btn.dataset.fillPass;
  const btnSubmit = form.querySelector('button[type=submit]');
  if (btnSubmit) btnSubmit.focus();
});

/* --------------------------------------------------------------- راه‌اندازی */
window.addEventListener('DOMContentLoaded', () => {
  initLiveSearch();
  initAutoSubmitFilters();
  initNotificationPoll();

  // محو خودکار پیام‌های بالای صفحه
  document.querySelectorAll('.toast-bar').forEach((toastEl, idx) => {
    setTimeout(() => {
      toastEl.style.transition = 'opacity .4s';
      toastEl.style.opacity = '0';
      setTimeout(() => toastEl.remove(), 400);
    }, 4200 + idx * 400);
  });

  // فرم‌های دارای data-ajax="off" به صورت معمولی ارسال شوند
  document.querySelectorAll('form[data-ajax="off"]').forEach((f) => (f.dataset.ajax = 'off'));
});
