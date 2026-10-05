export function money(n, currency = 'USD') {
  if (n == null) return null;
  return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(n);
}

export function durationLabel({ days, nights } = {}) {
  if (!days) return null;
  const d = `${days} ${days === 1 ? 'day' : 'days'}`;
  return nights ? `${d} / ${nights} ${nights === 1 ? 'night' : 'nights'}` : d;
}

export function placesLabel(trip) {
  return trip.destinations.map((d) => d.name).join(' · ');
}

export function categoryLabel(trip) {
  return trip.activities[0]?.name ?? trip.tripTypes[0]?.name ?? '';
}
