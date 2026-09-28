'use strict';

// ==================== مودال‌ها ====================
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = 'flex';
  const focusable = el.querySelector('input:not([disabled]), button, a');
  if (focusable) setTimeout(() => focusable.focus(), 50);
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach((m) => { m.style.display = 'none'; });
}

// بستن مودال با کلیک در پس‌زمینه
window.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.style.display = 'none';
  }
});

// بستن مودال با کلید Esc
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAllModals();
});

// ==================== نمایش سریع کالا ====================
let pmQtyValue = 1;
let pmAddBase = 'index.php?page=cart';

function updatePmAddLink() {
  const link = document.getElementById('pmAdd');
  if (link) link.href = pmAddBase + '&qty=' + pmQtyValue;
}

function setPmQty(n) {
  const input = document.getElementById('pmQty');
  const max = parseInt(input.max, 10) || 1;
  pmQtyValue = Math.min(Math.max(1, n), max);
  input.value = pmQtyValue;
  updatePmAddLink();
}

function openProductModal(card) {
  const d = card.dataset;
  document.getElementById('pmIcon').textContent = d.icon || '📦';
  document.getElementById('pmBrand').textContent = d.brand || '';
  document.getElementById('pmTitle').textContent = d.name || '';
  document.getElementById('pmDesc').textContent = d.desc || 'برای این کالا توضیحات تکمیلی ثبت نشده است.';
  document.getElementById('pmTax').textContent = 'شناسه کالا: ' + (d.tax || '');

  const stock = parseInt(d.stock, 10) || 0;
  const stockEl = document.getElementById('pmStock');
  const addLink = document.getElementById('pmAdd');
  const qtyWrap = document.getElementById('pmQtyWrap');
  document.getElementById('pmPrice').innerHTML =
    (parseInt(d.price, 10) || 0).toLocaleString('en-US') + ' <small>تومان</small>';

  if (stock <= 0) {
    stockEl.className = 'stock-badge out';
    stockEl.textContent = 'ناموجود';
    addLink.textContent = 'ناموجود';
    addLink.classList.add('btn-disabled');
    addLink.removeAttribute('href');
    addLink.setAttribute('aria-disabled', 'true');
    qtyWrap.style.display = 'none';
  } else {
    stockEl.className = 'stock-badge ' + (stock <= 5 ? 'low' : 'ok');
    stockEl.textContent = stock <= 5
      ? 'موجودی محدود: ' + stock + ' عدد'
      : 'موجود — ' + stock + ' عدد';
    addLink.textContent = 'افزودن به سبد خرید';
    addLink.classList.remove('btn-disabled');
    addLink.setAttribute('href', '#');
    addLink.removeAttribute('aria-disabled');
    qtyWrap.style.display = 'flex';

    pmAddBase = d.addurl || 'index.php?page=cart';
    const input = document.getElementById('pmQty');
    input.max = stock;
    setPmQty(1);
  }

  openModal('productModal');
}

// باز شدن نمایش سریع با کلیک روی تصویر/نام کالا
document.addEventListener('click', (e) => {
  const opener = e.target.closest('.pro-open');
  if (!opener) return;
  if (e.target.closest('a')) return;
  const card = opener.closest('.pro-card');
  if (card) openProductModal(card);
});

// باز شدن با کلید Enter/Space
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter' && e.key !== ' ') return;
  const opener = e.target.closest && e.target.closest('.pro-open');
  if (!opener) return;
  e.preventDefault();
  const card = opener.closest('.pro-card');
  if (card) openProductModal(card);
});

// کنترل‌های تعداد در مودال کالا
document.addEventListener('DOMContentLoaded', () => {
  const minus = document.getElementById('pmMinus');
  const plus = document.getElementById('pmPlus');
  const add = document.getElementById('pmAdd');
  if (minus) minus.addEventListener('click', () => setPmQty(pmQtyValue - 1));
  if (plus) plus.addEventListener('click', () => setPmQty(pmQtyValue + 1));
  if (add) add.addEventListener('click', (e) => {
    if (add.getAttribute('aria-disabled') === 'true' || !add.getAttribute('href')) {
      e.preventDefault();
      return;
    }
    e.preventDefault();
    window.location.href = add.getAttribute('href');
  });

  // منوی موبایل
  const burger = document.getElementById('navBurger');
  const nav = document.getElementById('mainNav');
  if (burger && nav) {
    burger.addEventListener('click', () => {
      const open = nav.classList.toggle('nav-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
});

// ==================== تأیید عملیات مخرب ====================
document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-confirm]');
  if (!el) return;
  if (!window.confirm(el.getAttribute('data-confirm'))) {
    e.preventDefault();
    e.stopPropagation();
  }
});

// ==================== پیام‌های لحظه‌ای (Toast) ====================
window.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('siteToast');
  if (toast) {
    setTimeout(() => {
      toast.style.transition = 'opacity 0.4s ease';
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 400);
    }, 5000);
  }
});
