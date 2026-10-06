// Ask before submitting destructive forms: <form data-confirm="...">.
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});
