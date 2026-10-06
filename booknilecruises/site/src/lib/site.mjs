// Business details and navigation shared by every page.

import { exportSettings } from './export-file.mjs';

// Contact details: the admin panel's settings when building from its export, else these defaults.
const S = exportSettings();
const pick = (key, fallback) => (S[key] ? S[key] : fallback);

export const SITE = {
  name: 'Book Nile Cruises',
  origin: 'https://booknilecruises.net',
  email: pick('email', 'info@booknilecruises.net'),
  whatsapp: pick('whatsapp', '201096611124'),
  phoneDisplay: pick('phone_display', '+20 109 661 1124'),
  phoneAlt: pick('phone_alt', '+20 101 800 3960'),
  address: pick('address', 'Khaled Ibn El Waleed St., Luxor, Egypt'),
};

// Search engine verification and analytics. Without an export the current Google code stays.
export const VERIFY = {
  google: S.google_site_verification ?? 'SsaoNo-o-vaVrxWj1PFiSWLm9JcNNGWC_Bs663QgEbs',
  bing: S.bing_site_verification ?? '',
  ga4: S.ga4_id ?? '',
};

export function whatsappUrl(text = '') {
  const q = text ? `?text=${encodeURIComponent(text)}` : '';
  return `https://wa.me/${SITE.whatsapp}${q}`;
}

export function mailtoUrl(subject = '', body = '') {
  const params = new URLSearchParams();
  if (subject) params.set('subject', subject);
  if (body) params.set('body', body);
  const q = params.toString().replace(/\+/g, '%20');
  return `mailto:${SITE.email}${q ? `?${q}` : ''}`;
}

// The menu mirrors the current WordPress header, same URLs.
export const NAV = [
  {
    label: 'Nile Cruises',
    href: '/nile-cruise/',
    children: [
      { label: 'Standard 5 Star Nile Cruises', href: '/standard-5-star-nile-cruises/' },
      { label: 'Deluxe Nile Cruises', href: '/deluxe-nile-cruises/' },
      { label: 'Luxury Nile Cruises', href: '/luxury-nile-cruises/' },
      { label: 'Luxury Dahabiya Nile Cruise', href: '/luxury-dahabiya-nile-cruise-packages/' },
      { label: 'Lake Nasser Nile Cruises', href: '/lake-nasser-nile-cruises/' },
    ],
  },
  {
    label: 'Day Tours',
    href: '/day-tours/',
    children: [
      { label: 'Luxor Day Tours', href: '/luxor-day-tours/' },
      { label: 'Aswan Day Tours', href: '/activities/day-tour/aswan-day-tour/' },
      { label: 'Cairo Day Tours', href: '/cairo-day-tours/' },
      { label: 'Hurghada Day Tours', href: '/hurghada-day-tours/' },
    ],
  },
  { label: 'Egypt Tour Packages', href: '/egypt-tour-packages/' },
  { label: 'Transfers', href: '/transfers/' },
  { label: 'About', href: '/about-us/' },
  { label: 'Contact', href: '/contact-us/' },
];

// Landing pages that list the trips of one activity term. Same URLs as the
// WordPress pages; the listing now comes from the term, not a hand-built grid.
export const CATEGORY_PAGES = {
  'nile-cruise': {
    term: 'nile-cruise',
    heading: 'Nile Cruises',
    intro:
      'Every Nile cruise we sell, from standard five-star ships to private dahabiyas and Lake Nasser. Prices are per person and include full board and the guided visits listed on each trip.',
    description:
      'Compare Nile cruises between Luxor and Aswan: standard 5-star, deluxe, dahabiya and Lake Nasser cruises with itineraries and per-person prices.',
  },
  'standard-5-star-nile-cruises': {
    term: 'standard-5-star-nile-cruises',
    heading: 'Standard 5 Star Nile Cruises',
    intro:
      'Comfortable five-star ships on the classic Luxor and Aswan route. The best value way to see Karnak, Edfu, Kom Ombo and Philae with a guide.',
    description:
      'Standard 5-star Nile cruises between Luxor and Aswan with full board, guided temple visits and per-person prices.',
  },
  'deluxe-nile-cruises': {
    term: 'deluxe-nile-cruises',
    heading: 'Deluxe Nile Cruises',
    intro:
      'Deluxe ships with larger cabins, better dining and more space on deck, sailing the same route between Luxor and Aswan.',
    description:
      'Deluxe Nile cruises between Luxor and Aswan: larger cabins, better dining, full board and guided visits. See itineraries and prices.',
  },
  'luxury-nile-cruises': {
    term: 'luxury-nile-cruises',
    heading: 'Luxury Nile Cruises',
    intro:
      'Our luxury ships are booked on request. Tell us your dates and we will send the ships with cabins available, with prices.',
    description: 'Luxury Nile cruises between Luxor and Aswan, booked on request with a Luxor-based team.',
  },
  'luxury-dahabiya-nile-cruise-packages': {
    term: 'dahabiya-nile-cruise',
    heading: 'Luxury Dahabiya Nile Cruise',
    intro:
      'Dahabiyas are small sailing boats with a handful of cabins. They moor at quiet places the big ships pass by, and feel like a private yacht.',
    description:
      'Luxury dahabiya Nile cruises between Luxor and Aswan: small sailing boats, few cabins and quiet moorings. Itineraries and prices.',
  },
  'lake-nasser-nile-cruises': {
    term: 'lake-naser-nile-cruises',
    heading: 'Lake Nasser Nile Cruises',
    intro:
      'Cruises between Aswan and Abu Simbel on Lake Nasser, visiting Nubian temples that the Nile ships cannot reach.',
    description: 'Lake Nasser cruises between Aswan and Abu Simbel, visiting the Nubian temples. Itineraries and prices.',
  },
  'day-tours': {
    term: 'day-tour',
    heading: 'Day Tours',
    intro: 'Day tours in Luxor, Aswan, Cairo and Hurghada, with a guide and transport.',
    description: 'Egypt day tours in Luxor, Aswan, Cairo and Hurghada with per-person prices.',
  },
  'luxor-day-tours': {
    term: 'luxor-day-tours',
    heading: 'Luxor Day Tours',
    intro: 'The East and West Banks, the Valley of the Kings, balloon rides and sound and light shows.',
    description: 'Luxor day tours: Karnak, Valley of the Kings, hot air balloon, felucca and sound and light shows. Prices per person.',
  },
  'cairo-day-tours': {
    term: 'cairo-day-tours',
    heading: 'Cairo Day Tours',
    intro: 'The Giza Pyramids, the Grand Egyptian Museum, Old Cairo and trips out of the city.',
    description: 'Cairo day tours: Giza Pyramids, Grand Egyptian Museum, Old Cairo, Sakkara and more. Prices per person.',
  },
  'hurghada-day-tours': {
    term: 'hurghada-day-tour',
    heading: 'Hurghada Day Tours',
    intro: 'Snorkelling islands, desert safaris and day trips from Hurghada to Luxor and Cairo.',
    description: 'Hurghada day tours: Giftun and Mahmya snorkelling, desert safari, submarine and trips to Luxor and Cairo.',
  },
  'egypt-tour-packages': {
    term: 'tour-packages',
    heading: 'Egypt Tour Packages',
    intro: 'Multi-day trips that join Cairo, the Nile cruise and the coast into one plan, from three to twelve days.',
    description: 'Egypt tour packages from 3 to 12 days combining Cairo, Alexandria, a Nile cruise, the oases and the Red Sea.',
  },
};

// Transfer prices exactly as published on the current /transfers/ page.
export const TRANSFERS = [
  ['Luxor Airport', 'Luxor Hotels / Nile cruises', '10 km', '20 min', 20, 30],
  ['Luxor Hotels', 'Luxor Airport', '10 km', '20 min', 20, 30],
  ['Luxor', 'Aswan', '220 km', '3.5 hrs', 85, 120],
  ['Aswan', 'Luxor', '220 km', '3.5 hrs', 85, 120],
  ['Aswan Airport', 'Aswan Hotels', '18 km', '25 min', 20, 30],
  ['Aswan Hotels', 'Aswan Airport', '18 km', '25 min', 20, 30],
  ['Aswan', 'Abu Simbel', '280 km', '3 hrs', 95, 130],
  ['Abu Simbel', 'Aswan', '280 km', '3 hrs', 95, 130],
  ['Luxor', 'Hurghada', '290 km', '4 hrs', 110, 150],
  ['Hurghada', 'Luxor', '290 km', '4 hrs', 110, 150],
];
