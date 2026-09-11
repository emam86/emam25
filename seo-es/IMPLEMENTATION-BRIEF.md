# Implementation Brief — Spanish (`/es/`) parity for luxorandaswantours.net

> **How to use this file:** paste everything below the line into Codex (or any
> coding agent) **inside the repository that contains the website source**.
> It is written to stand alone — it assumes no prior conversation.
>
> **ملاحظة بالعربي:** الملف ده هو البرومبت الجاهز للّصق في Codex. لازم يتلصق
> في الريبو اللي فيه كود الموقع نفسه — مش في الريبو ده (ده فيه التقارير بس).

---

## Context

`luxorandaswantours.net` is a bilingual Egypt travel site serving `/en/` and
`/es/`. Spanish-speaking countries produce roughly half of the site's Google
impressions (~997/month, Spain alone 781) but convert at ~1.1% CTR because the
Spanish pages sit at average position 19.

A Screaming Frog crawl (410 URLs, 2026-09-11) plus Google Search Console data
established the following. **All numbers below are measured, not estimated.**

| Metric | Value |
|---|---:|
| Crawlable pages | 277 |
| `/en/` pages | 242 |
| `/es/` pages | 34 |
| `/en/tour/` pages | 199 |
| `/es/tour/` pages found by crawler | 6 |
| English cruise pages | 92 |
| Spanish cruise pages (confirmed indexed in GSC) | ~21 |
| English cruise pages with no Spanish version | **71** |
| URLs carrying `hreflang` | 22 of 410 |

Work the tasks in the order given. Tasks 1–4 affect pages that are already
indexed and already receive impressions, so they pay back fastest. Task 5 is the
largest but is worthless before 1 and 2 are done.

---

## Task 1 — Fix orphaned Spanish cruise pages (highest priority)

**The problem.** These ~21 Spanish cruise pages exist and are indexed by Google
(they appear in Search Console with impressions), but the crawler found none of
them. Nothing links to them in server-rendered HTML:

```
ms-zeina · royal-viking · al-hambra · moon-dance · blue-shadow · acamar
le-fayan · royal-ruby · ms-amwaj-living-stone · steigenberger-minerva
sonesta-st-george-i · princess-sarah · princess-sarah-ii · radamis-ii
ms-radamis-i · ms-medea · ms-esplanade · ms-nile-goddess · m-s-nile-style
m-s-nile-dolphin · iberotel-crown-empress · nour-el-nil · assouan-crucero-dahabiya
```

Their URL pattern is `/es/tour/{name}-crucero-por-el-nilo`.

**First, diagnose which of the two causes applies:**
- Open `/es/cruceros-nilo` and inspect the server-rendered HTML (`curl`, or
  "View Source", **not** DevTools' inspector, which shows post-JS DOM).
- If the individual cruise links are absent from the HTML source but present in
  the rendered DOM, the links are client-side only.
- If they are absent from both, the pages are genuinely orphaned.

**Then fix it:**
- `/es/cruceros-nilo` must list every Spanish cruise page as a real `<a href>`
  in the server-rendered HTML — mirroring exactly how `/en/nile-cruises` links
  to the English ones. Match the existing English implementation rather than
  inventing a new pattern.
- Add the same links to `/es/cruceros-nilo/luxor-asuan`,
  `/es/cruceros-nilo/dahabiya`, and `/es/cruceros-nilo/lago-nasser`, filtered
  by the relevant cruise type.
- Add each Spanish cruise page to the Spanish sitemap.

**Acceptance:** a crawl with JavaScript rendering **disabled**, starting from
`/es`, reaches every `/es/tour/*-crucero-por-el-nilo` page.

---

## Task 2 — Roll out `hreflang` across the whole site

Only 22 of 410 URLs carry `hreflang`. Where it exists it is **correct** —
reciprocal, absolute URLs, self-referencing, with `x-default`. Do not redesign
it; replicate the existing pattern everywhere.

For every page that has both language versions, emit in `<head>` on **both**:

```html
<link rel="alternate" hreflang="en" href="https://www.luxorandaswantours.net/en/{slug-en}" />
<link rel="alternate" hreflang="es" href="https://www.luxorandaswantours.net/es/{slug-es}" />
<link rel="alternate" hreflang="x-default" href="https://www.luxorandaswantours.net/en/{slug-en}" />
```

Rules that must hold, or Google discards the annotations entirely:
1. **Reciprocal** — if EN points to ES, ES must point back to EN.
2. **Absolute URLs**, always `https://www.` (the `www.` matters: the crawl found
   `https://luxorandaswantours.net/en/destinations` without `www`).
3. **Self-referencing** — each page includes its own URL in the set.
4. Emit `hreflang` **only** where the target actually exists and returns 200.
   Never point at a 404.

Also set `<html lang="es">` on `/es/` pages (verify it is not hardcoded to `en`).

**Acceptance:** every page with a counterpart carries a reciprocal, self-
referencing set; no `hreflang` target returns a non-200 status.

---

## Task 3 — Fix titles on the existing Spanish cruise pages

Spanish cruise pages whose slug and title match what users actually search
average **position 8.2**. Those carrying extra or missing words average **49.5**.
Same site, same content depth — the difference is name matching.

Change `<title>` and `<h1>` only. **Do not change these URLs in this task** —
changing titles is reversible and measurable; changing URLs is neither.

Rule: **common name first, brand in parentheses.**

| Page | New `<title>` |
|---|---|
| `ms-amwaj-living-stone-…` | `Crucero por el Nilo MS Amwaj (Living Stone) 5★ \| Luxor–Asuán` |
| `steigenberger-minerva-…` | `Crucero Minerva por el Nilo (Steigenberger) 5★ \| Luxor–Asuán` |
| `le-fayan-…` | `Crucero por el Nilo Le Fayan II \| Luxor–Asuán 7 noches` |
| `sonesta-st-george-i-…` | `Crucero Sonesta St. George por el Nilo \| Luxor–Asuán` |
| `princess-sarah-…` | `Crucero Sarah por el Nilo (Princess Sarah) \| Luxor–Asuán` |
| `m-s-nile-style-…` | `Crucero por el Nilo MS Nile Style 5★ \| Luxor–Asuán` |
| `radamis-ii-…` | `Crucero por el Nilo MS Radamis II \| Luxor–Asuán` |
| `iberotel-crown-empress-…` | `Crucero por el Nilo Crown Empress (Iberotel) \| Luxor–Asuán` |

Apply the same rule to the rest of the list in Task 1.

---

## Task 4 — Resolve keyword cannibalization

Six pages compete on one topic. Google weakens all of them.

**"How many days in Egypt" — English (3):**
- `/en/blog/how-many-days-do-you-need-in-egypt`
- `/en/blog/how-many-days-in-egypt-is-enough`
- `/en/blog/how-long-do-you-need-in-egypt`

**Spanish (3):**
- `/es/blog/cuantos-dias-necesitas-en-egipto-una-guia-realista` ← **keep this one**
- `/es/blog/cuanto-tiempo-necesitas-en-egipto`
- `/es/blog/cuantos-dias-en-egipto-son-suficientes-guia-practica`

Keep the strongest page per language, merge any unique content into it, and
**301** the others to it. The Spanish winner is chosen on evidence: it ranks #1
in Colombia and #5 in Mexico; the other two do not rank.

For English, pick the strongest by Search Console clicks and 301 the rest.

**Other confirmed pairs:**

| Pair | Action |
|---|---|
| `/en/tour/ultimate-egyptian-odyssey` + `/en/tour/ultimate-14-day-egyptian-odyssey` | 301 to the stronger |
| `/en/tour/2-day-luxor-east-and-west-bank` + `/en/tour/the-ultimate-luxor-east-and-west-bank-day-tour` | 301 to the stronger |
| `/en/tour/2-day-luxor-dendera-and-abydos` + `/en/tour/dendera-and-abydos-day-trip-from-luxor` | Different durations — keep both, make titles state the duration |
| `/en/tour/princess-sarah-*` and `-ii`; `/en/tour/ms-radamis-i` and `radamis-ii` | Different vessels — keep both, unify slug convention, titles must distinguish I from II |
| `/es/buscador-de-viaje` + `/es/buscador-de-cruceros` | Differentiate purpose in copy, or merge |

---

## Task 5 — Build 71 missing Spanish cruise pages

Source list: `missing-es-cruise-pages.csv` (columns `en_url`,
`proposed_es_url`, `proposed_es_title`, `nota`, `modo`).

- `modo=auto` (51 rows) — slug and title are ready to use as-is.
- `modo=manual` (20 rows) — descriptive packages; translate the name by hand,
  keeping the same "common name first" rule.

Each page mirrors the structure of its English counterpart and must contain,
in this order:

1. `Precio desde {X} €` (and MXN where shown elsewhere) plus a
   "Solicitar presupuesto" CTA **above the fold**
2. `Itinerario día a día` using Spanish monument names: Karnak, Valle de los
   Reyes, Edfu, Kom Ombo, Templo de Filae, Alta Presa
3. `Camarotes y cubiertas`
4. `Qué incluye` / `Qué no incluye` — two separate lists
5. `Fechas de salida 2026 / 2027`
6. `Guía en español` — state it explicitly; it is the strongest selling point
   for this market
7. `Preguntas frecuentes` — 5 questions with `FAQPage` schema
8. Internal links to `/es/cruceros-nilo` and two alternative vessels

Add `Product` + `Offer` JSON-LD with `inLanguage: "es"`. Only include
`aggregateRating` if real reviews are rendered on the page — fabricated ratings
risk a manual action.

**This is not machine translation.** Machine-translated boilerplate is exactly
what the competitors publish and why their Spanish pages are weak. Write real
Spanish and include operational detail (departure quay, boarding times, the
Spanish-speaking guide's name) that competitors cannot copy.

---

## Task 6 — Structural fixes

| # | Issue | Fix |
|---|---|---|
| 1 | `/en/blog/que-es-una-esfinge-egipcia` — a Spanish slug under `/en/` | Move to `/es/blog/`, 301 the old URL |
| 2 | `/en/search` and `/es/buscar` are in the sitemap | `noindex`, remove from sitemap |
| 3 | `/es/destinos/aswan` (English spelling) vs `/es/tours-asuan` (Spanish) | Standardise on `asuan`, 301 |
| 4 | `/es/destinos/cairo` | `el-cairo`, 301 |
| 5 | `m-s-` vs `ms-` slug prefixes | Standardise on `ms-`, 301. **Do this after Task 3 has been measured** |
| 6 | `https://luxorandaswantours.net/…` resolving without `www` | 301 to `www`, and make canonical tags consistent |
| 7 | 15 `/en/egypt-tour-packages/{n}-days` pages; `/es/paquetes-egipto/{n}-dias` incomplete | Build the missing Spanish durations |

Also: Spanish searchers type both `aswan` and `asuán`, and both `luxor` and
`lúxor`. In Spanish body copy write the pair once — «Asuán (Aswan)» — so both
spellings are covered. URLs stay unaccented.

---

## Constraints

- **Never** change a URL without a 301 from the old one.
- **Never** emit `hreflang` pointing at a URL that does not return 200.
- **Never** add `aggregateRating` schema without real, visible reviews.
- **Never** create a second Spanish page for a vessel that already has one —
  check the Task 1 list first. Duplicating would deepen the cannibalization.
- Match the existing codebase's routing, i18n and component conventions rather
  than introducing new ones.
- Ship Tasks 1 and 2 as their own change so their effect can be measured before
  Task 5 lands.
