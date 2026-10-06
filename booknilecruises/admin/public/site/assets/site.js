
(()=>{
function compose(form) {
    const f = new FormData(form);
    const trip = form.dataset.trip;
    const lines = [
        trip ? `Trip: ${trip}` : null,
        `Name: ${f.get('name') || ''}`,
        f.get('date') ? `Travel date: ${f.get('date')}` : null,
        `Travellers: ${f.get('adults') || 0} adults, ${f.get('children') || 0} children`,
        f.get('contact') ? `Contact: ${f.get('contact')}` : null,
        f.get('message') ? `Message: ${f.get('message')}` : null,
        trip ? `Page: ${location.href}` : null,
    ].filter(Boolean);
    return { subject: trip ? `Enquiry: ${trip}` : 'Trip enquiry', body: lines.join('\n') };
}
// Copy for the team's inbox. sendBeacon survives the jump to WhatsApp or the mail app.
function log(form, via) {
    const f = new FormData(form);
    const contact = String(f.get('contact') || '').trim();
    const data = {
        name: String(f.get('name') || '').trim(),
        email: contact.includes('@') ? contact : '',
        phone: contact && !contact.includes('@') ? contact : '',
        trip: form.dataset.slug || '',
        travel_date: String(f.get('date') || ''),
        adults: Number(f.get('adults') || 0),
        children: Number(f.get('children') || 0),
        message: String(f.get('message') || ''),
        page_url: location.pathname,
        channel: via,
        website: String(f.get('website') || ''),
    };
    try {
        navigator.sendBeacon?.('/api/enquiries', new Blob([JSON.stringify(data)], { type: 'application/json' }));
    }
    catch {
        /* logging is best effort */
    }
}
for (const form of document.querySelectorAll('form.enquiry')) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const via = e.submitter?.value ?? 'whatsapp';
        const { subject, body } = compose(form);
        log(form, via);
        const url = via === 'email'
            ? `mailto:${form.dataset.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
            : `https://wa.me/${form.dataset.wa}?text=${encodeURIComponent(body)}`;
        if (via === 'email')
            location.href = url;
        else
            window.open(url, '_blank', 'noopener');
    });
}

})();

(()=>{
for (const root of document.querySelectorAll('[data-listing]')) {
    const grid = root.querySelector('[data-grid]');
    const cells = grid ? [...grid.querySelectorAll('.cell')] : [];
    const chips = [...root.querySelectorAll('.chip')];
    const sort = root.querySelector('[data-sort]');
    const count = root.querySelector('[data-count]');
    const params = new URLSearchParams(location.search);
    function apply(dest) {
        let shown = 0;
        for (const c of cells) {
            const card = c.querySelector('.card');
            const ok = !dest || (card?.dataset.destinations ?? '').split(' ').includes(dest);
            c.hidden = !ok;
            if (ok)
                shown++;
        }
        chips.forEach((ch) => ch.setAttribute('aria-pressed', String(ch.dataset.dest === dest)));
        if (count)
            count.textContent = dest ? `${shown} trips` : '';
    }
    chips.forEach((ch) => ch.addEventListener('click', () => apply(ch.dataset.dest ?? '')));
    sort?.addEventListener('change', () => {
        const num = (v) => (v ? Number(v) : Infinity);
        const [key, dir] = sort.value.split('-');
        const sorted = [...cells].sort((a, b) => {
            if (!key)
                return Number(a.dataset.order) - Number(b.dataset.order);
            const va = num(key === 'price' ? a.dataset.price : a.dataset.days);
            const vb = num(key === 'price' ? b.dataset.price : b.dataset.days);
            return dir === 'desc' ? (vb === Infinity ? -1 : va === Infinity ? 1 : vb - va) : va - vb;
        });
        sorted.forEach((c) => grid?.appendChild(c));
    });
    // A destination from the home page finder that this list has no trips for
    // shows everything, with a note instead of a silent no-op.
    const initial = params.get('destination') ?? '';
    if (initial && chips.some((c) => c.dataset.dest === initial))
        apply(initial);
    else if (initial && count)
        count.textContent = 'None of these trips visit that destination, so all trips are shown.';
}

})();

(()=>{
// Send the finder to the chosen trip-type page, keeping the destination filter.
const form = document.getElementById('finder');
form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const dest = form.elements.namedItem('destination').value;
    const type = form.elements.namedItem('type').value || '/trip/';
    location.href = type + (dest ? `?destination=${encodeURIComponent(dest)}` : '');
});

})();

(()=>{
const track = document.getElementById('gal');
for (const b of document.querySelectorAll('.gal-nav button')) {
    b.addEventListener('click', () => {
        if (!track)
            return;
        track.scrollBy({ left: Number(b.dataset.dir) * track.clientWidth * 0.8, behavior: 'smooth' });
    });
}

})();
