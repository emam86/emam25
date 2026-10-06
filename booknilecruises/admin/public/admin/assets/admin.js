// Ask before submitting destructive forms: <form data-confirm="...">.
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});

// Copy public image links without inline scripts.
document.addEventListener('click', async (e) => {
  const button = e.target.closest('[data-copy]');
  if (!button) return;
  const status = button.parentElement.querySelector('[data-copy-status]');
  try {
    await navigator.clipboard.writeText(button.dataset.copy);
    if (status) status.textContent = 'تم نسخ الرابط.';
  } catch {
    if (status) status.textContent = 'تعذر النسخ. انسخ الرابط يدويًا.';
  }
});

// All editor input remains in ordinary form controls when JavaScript is unavailable.
function button(text, attributes = {}) {
  const el = document.createElement('button');
  el.type = 'button'; el.textContent = text;
  Object.entries(attributes).forEach(([key, value]) => el.setAttribute(key, value));
  return el;
}
function enhanceRich(root) {
  root.querySelectorAll('textarea[data-rich]').forEach((textarea) => {
    if (textarea.dataset.enhanced) return;
    textarea.dataset.enhanced = '1';
    const wrapper = document.createElement('div'); wrapper.className = 'rich-editor';
    const toolbar = document.createElement('div'); toolbar.className = 'rich-toolbar';
    toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', 'تنسيق النص');
    const area = document.createElement('div'); area.contentEditable = 'true'; area.className = 'rich-area';
    area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true');
    area.setAttribute('aria-label', textarea.name.includes('faqs') ? 'الإجابة' : 'المحتوى');
    // Parse into an inert document first. User-supplied validation input must not execute.
    const inert = new DOMParser().parseFromString(textarea.value, 'text/html');
    inert.querySelectorAll('script,style,iframe,object,embed,svg,math,link,meta,base').forEach((node) => node.remove());
    inert.querySelectorAll('*').forEach((node) => {
      Array.from(node.attributes).forEach((attr) => {
        if (!['href', 'src', 'alt', 'title'].includes(attr.name)) node.removeAttribute(attr.name);
        if (['href', 'src'].includes(attr.name) && !safeEditorUrl(attr.value)) node.removeAttribute(attr.name);
      });
    });
    area.innerHTML = inert.body.innerHTML;
    const sync = () => { textarea.value = area.innerHTML; };
    area.addEventListener('input', sync);
    // Force pasted markup through the server on save; avoid executable pasted markup locally.
    area.addEventListener('paste', (event) => {
      event.preventDefault();
      document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); sync();
    });
    [['عريض', 'bold'], ['مائل', 'italic'], ['عنوان 2', 'formatBlock', 'h2'], ['عنوان 3', 'formatBlock', 'h3'], ['قائمة نقطية', 'insertUnorderedList'], ['قائمة مرقمة', 'insertOrderedList'], ['رابط', 'createLink'], ['إزالة التنسيق', 'removeFormat']].forEach(([label, command, value]) => {
      const control = button(label);
      control.addEventListener('mousedown', (event) => event.preventDefault());
      control.addEventListener('click', () => {
        area.focus();
        if (command === 'createLink') {
          value = window.prompt('رابط (https:// أو /مسار أو mailto: أو tel:)');
          if (!value || !safeEditorUrl(value)) return;
        }
        document.execCommand(command, false, value || null); sync();
      });
      toolbar.append(control);
    });
    wrapper.append(toolbar, area); textarea.after(wrapper); textarea.hidden = true;
  });
}
function safeEditorUrl(value) {
  const url = value.replace(/[\x00-\x20\x7f]/g, '');
  return !url.startsWith('//') && /^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url);
}
function showControls(root) {
  root.querySelectorAll('.js-control').forEach((el) => { el.hidden = false; });
}
function renumberRepeat(fieldset) {
  const field = fieldset.dataset.repeat;
  fieldset.querySelectorAll('[data-rows] > [data-ordered-item]').forEach((row, index) => {
    row.querySelectorAll('input[name],textarea[name]').forEach((el) => {
      el.name = el.name.replace(new RegExp(`^${field}\\[[^\\]]+\\]`), `${field}[${index}]`);
    });
  });
}
document.querySelectorAll('[data-trip-form], [data-term-form], [data-post-form], [data-seo-form]').forEach((form) => {
  showControls(form); enhanceRich(form);
  const source = form.querySelector('[data-slug-source]');
  const slug = form.querySelector('[data-slug-target]');
  if (source && slug) {
    let manual = slug.value !== '';
    slug.addEventListener('input', () => { manual = true; });
    source.addEventListener('input', () => {
      if (!manual) {
        slug.value = source.value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 190).replace(/-$/, '');
        slug.dispatchEvent(new Event('change', {bubbles: true}));
      }
    });
  }
  form.addEventListener('click', (event) => {
    const control = event.target.closest('button');
    if (!control) return;
    const item = control.closest('[data-ordered-item]');
    const containingRepeat = control.closest('[data-repeat]');
    if (item && control.hasAttribute('data-remove')) item.remove();
    if (item && control.dataset.move === 'up' && item.previousElementSibling) item.parentElement.insertBefore(item, item.previousElementSibling);
    if (item && control.dataset.move === 'down' && item.nextElementSibling) item.parentElement.insertBefore(item.nextElementSibling, item);
    if (item && containingRepeat) renumberRepeat(containingRepeat);
    if (control.hasAttribute('data-add-row')) {
      const repeat = control.closest('[data-repeat]'); const rows = repeat.querySelector('[data-rows]');
      if (rows.children.length >= Number(repeat.dataset.limit)) { window.alert('تم الوصول إلى الحد الأقصى للصفوف.'); return; }
      const fragment = repeat.querySelector('template').content.cloneNode(true);
      rows.append(fragment); renumberRepeat(repeat); showControls(rows); enhanceRich(rows);
      const added = rows.lastElementChild;
      if (control.dataset.addRow === 'heading') { added.classList.add('group-heading'); added.querySelector('textarea').closest('label').hidden = true; }
      added.querySelector('input').focus();
    }
  });
  form.addEventListener('submit', () => form.querySelectorAll('[data-repeat]').forEach(renumberRepeat));
  if (form.hasAttribute('data-term-form')) {
    const taxonomy = form.elements.taxonomy; const parent = form.elements.parent_id;
    const update = () => {
      Array.from(parent.options).forEach((option) => { if (option.value) option.disabled = taxonomy.value === 'destination' || option.dataset.taxonomy !== taxonomy.value; });
      if (parent.selectedOptions[0]?.disabled) parent.value = '';
    };
    taxonomy.addEventListener('change', update); update();
    return;
  }
  if (form.elements.image_id) {
    form.elements.image_id.type = 'hidden';
    form.elements.image_id.closest('label').hidden = true;
    form.querySelectorAll('[data-gallery] input').forEach((input) => { input.type = 'hidden'; input.closest('label').hidden = true; });
  }
  const order = form.querySelector('[data-term-order]');
  order?.querySelectorAll('input').forEach((input) => { input.name = 'term_ids[]'; });
  form.querySelectorAll('[data-term-checkbox]').forEach((checkbox) => {
    checkbox.removeAttribute('name');
    checkbox.addEventListener('change', () => {
      const existing = Array.from(order.children).find((row) => row.querySelector('input').value === checkbox.value);
      if (!checkbox.checked) { existing?.remove(); return; }
      if (existing) return;
      const row = document.createElement('div'); row.className = 'row'; row.dataset.orderedItem = '';
      const input = document.createElement('input'); input.type = 'hidden'; input.name = 'term_ids[]'; input.value = checkbox.value;
      const label = document.createElement('span'); label.textContent = checkbox.parentElement.textContent;
      row.append(input, label, button('↑', {'data-move': 'up'}), button('↓', {'data-move': 'down'})); order.append(row);
    });
  });
  const preview = () => {
    const val = (name) => form.elements[name]?.value.trim() || '';
    form.querySelector('[data-seo-title]').textContent = val('seo_title') || `${val('title')} - Book Nile cruises`;
    let path = `/trip/${val('slug')}/`;
    if (form.hasAttribute('data-post-form')) path = `/${val('published_at').slice(0, 10).replaceAll('-', '/')}/${val('slug')}/`;
    if (form.hasAttribute('data-seo-form')) path = val('path');
    form.querySelector('[data-seo-url]').textContent = `${form.dataset.siteUrl.replace(/\/$/, '')}${path}`;
    form.querySelector('[data-seo-description]').textContent = val('seo_description') || val('excerpt');
    form.querySelectorAll('[data-counter]').forEach((el) => {
      const length = Array.from(el.value).length; const count = el.parentElement.querySelector('[data-count]');
      count.textContent = `${length} / ${el.dataset.counter}`; count.classList.toggle('danger', length > Number(el.dataset.counter));
    });
  };
  form.addEventListener('input', preview); form.addEventListener('change', preview); preview();
  if (form.hasAttribute('data-seo-form')) return;
  const dialog = document.createElement('dialog'); dialog.className = 'media-picker';
  const heading = document.createElement('h2'); heading.textContent = 'اختيار الصور';
  const search = document.createElement('input'); search.placeholder = 'بحث الصور'; search.setAttribute('aria-label', 'بحث الصور');
  const grid = document.createElement('div'); grid.className = 'media-grid';
  const status = document.createElement('p'); status.setAttribute('role', 'status');
  const pager = document.createElement('div'); pager.className = 'row';
  const previous = button('السابق'); const next = button('التالي'); const done = button('تم / إغلاق');
  pager.append(previous, next, done); dialog.append(heading, search, status, grid, pager); document.body.append(dialog);
  let mode = 'cover'; let page = 1; let pages = 1; let request = 0; let contentRange = null;
  const thumb = (path) => path.startsWith('/images/') ? `${form.dataset.imagesUrl || '/images'}${path.slice(7)}` : path;
  function choose(photo) {
    const image = document.createElement('img'); image.src = thumb(photo.thumb); image.alt = photo.alt; image.className = 'trip-thumb';
    if (mode === 'cover') {
      form.elements.image_id.value = photo.id; form.querySelector('[data-cover-preview]').replaceChildren(image); dialog.close(); return;
    }
    if (mode === 'content') {
      const area = form.querySelector('.rich-area');
      const photoNode = document.createElement('img'); photoNode.src = photo.path; photoNode.alt = photo.alt;
      if (contentRange && area.contains(contentRange.commonAncestorContainer)) {
        contentRange.deleteContents(); contentRange.insertNode(photoNode);
        contentRange.setStartAfter(photoNode); contentRange.collapse(true);
      } else area.append(photoNode);
      area.dispatchEvent(new Event('input')); dialog.close(); area.focus();
      if (contentRange) { const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(contentRange); }
      return;
    }
    const gallery = form.querySelector('[data-gallery]');
    if (Array.from(gallery.querySelectorAll('input')).some((input) => input.value === String(photo.id))) return;
    const row = document.createElement('div'); row.className = 'row'; row.dataset.orderedItem = '';
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'gallery[]'; input.value = photo.id;
    row.append(image, input, button('↑', {'data-move': 'up'}), button('↓', {'data-move': 'down'}), button('إزالة', {'data-remove': ''})); gallery.append(row);
    status.textContent = 'تمت إضافة الصورة. يمكنك اختيار صور أخرى.';
  }
  async function fetchPhotos() {
    const current = ++request; status.textContent = 'جاري تحميل الصور…';
    try {
      const url = new URL(form.dataset.pickerUrl, window.location.origin); url.searchParams.set('q', search.value); url.searchParams.set('page', page);
      const response = await fetch(url); if (!response.ok) throw new Error();
      const data = await response.json(); if (current !== request) return;
      page = data.page; pages = data.pages; grid.replaceChildren();
      data.items.forEach((photo) => {
        const tile = button(''); tile.className = 'media-tile';
        const image = document.createElement('img'); image.src = thumb(photo.thumb); image.alt = photo.alt;
        const label = document.createElement('span'); label.textContent = photo.alt || photo.path;
        tile.append(image, label); tile.addEventListener('click', () => choose(photo)); grid.append(tile);
      });
      previous.disabled = page <= 1; next.disabled = page >= pages; status.textContent = `صفحة ${page} / ${pages}`;
    } catch { if (current === request) status.textContent = 'تعذر تحميل الصور. حاول مرة أخرى.'; }
  }
  form.querySelectorAll('[data-pick]').forEach((control) => {
    control.addEventListener('mousedown', (event) => { if (control.dataset.pick === 'content') event.preventDefault(); });
    control.addEventListener('click', () => {
      mode = control.dataset.pick;
      if (mode === 'content') {
        const selection = window.getSelection(); const area = form.querySelector('.rich-area');
        contentRange = selection.rangeCount && area.contains(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : null;
      }
      page = 1; dialog.showModal(); fetchPhotos();
    });
  });
  form.querySelector('[data-clear-cover]').addEventListener('click', () => { form.elements.image_id.value = ''; form.querySelector('[data-cover-preview]').replaceChildren(); });
  let searchTimer;
  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { page = 1; fetchPhotos(); }, 250); });
  previous.addEventListener('click', () => { if (page > 1) { page--; fetchPhotos(); } });
  next.addEventListener('click', () => { if (page < pages) { page++; fetchPhotos(); } });
  done.addEventListener('click', () => dialog.close());
});

// Content is mostly English inside an Arabic panel: let each text field follow its own text direction.
document.addEventListener('DOMContentLoaded', () => {
  for (const el of document.querySelectorAll('input:not([type]), input[type="text"], input[type="search"], textarea, [contenteditable="true"]')) {
    if (!el.hasAttribute('dir')) el.setAttribute('dir', 'auto');
  }
});
