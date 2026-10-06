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
