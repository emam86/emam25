#!/usr/bin/env python3
"""
جرد فجوة EN↔ES من sitemap.xml — يعتمد على hreflang أولًا (دقيق)،
ويسقط على مطابقة أسماء الروابط لما hreflang يكون ناقصًا.

    python3 es-gap.py sitemap.xml
    curl -s https://www.luxorandaswantours.net/sitemap.xml | python3 es-gap.py -
"""
import re, sys, collections, html

SUFFIXES = ["-crucero-dahabiya-por-el-nilo", "-crucero-por-el-nilo", "-crucero-nilo",
            "-dahabiya-nile-cruise", "-luxury-nile-cruise", "-nile-cruise", "-cruise"]
PREFIXES = ["m-s-", "ms-", "mv-"]
ROMAN = re.compile(r"-(i{1,3}|iv|v|vi{1,3}|ix|x)$")
STOP = {"de","la","el","los","las","y","a","en","por","del","un","una","the","of","and",
        "to","in","for","is","are","do","you","need","guia","guide","from","desde","con",
        "trip","visit","book","private","privado","package","packages","paquete","paquetes",
        "experience","experiencia","ultimate","essential","iconic","definitive","best","top"}
# كلمات عامة تُحذف عند فحص التشابه فقط — وإلا اعتُبرت كل صفحات المدن متشابهة
GENERIC = {"day","days","dia","dias","tour","tours","cruise","cruises","nile","nilo",
           "crucero","cruceros","egypt","egipto","excursion","excursiones","viaje","viajes"}


def parse(sources):
    """يرجّع: [(loc, {hreflang: href})] + قائمة خرائط فرعية لو الملف فهرس."""
    entries, indexes = [], []
    for src in sources:
        raw = sys.stdin.read() if src == "-" else open(src, encoding="utf-8", errors="replace").read()
        if re.search(r"<sitemapindex", raw, re.I):
            indexes += re.findall(r"<loc>\s*(.*?)\s*</loc>", raw, re.I | re.S)
            continue
        for block in re.findall(r"<url\b.*?</url>", raw, re.I | re.S):
            m = re.search(r"<loc>\s*(.*?)\s*</loc>", block, re.I | re.S)
            if not m:
                continue
            alts = {}
            for lang, href in re.findall(
                    r'hreflang=["\']([^"\']+)["\'][^>]*href=["\']([^"\']+)["\']', block, re.I):
                alts[lang.lower()] = html.unescape(href.strip())
            for href, lang in re.findall(
                    r'href=["\']([^"\']+)["\'][^>]*hreflang=["\']([^"\']+)["\']', block, re.I):
                alts.setdefault(lang.lower(), html.unescape(href.strip()))
            entries.append((html.unescape(m.group(1)), alts))
    return entries, indexes


def path(u):
    return re.sub(r"^https?://[^/]+", "", u).rstrip("/") or "/"


def lang_of(p):
    m = re.match(r"^/(en|es)(/|$)", p)
    return m.group(1) if m else None


def stem(slug):
    s = slug.lower()
    for suf in SUFFIXES:
        if s.endswith(suf):
            s = s[: -len(suf)]
            break
    for pre in PREFIXES:
        if s.startswith(pre):
            s = s[len(pre):]
            break
    return s


def tokens(p):
    last = p.rstrip("/").split("/")[-1]
    return {t for t in re.split(r"[^a-z0-9]+", stem(last)) if t and t not in STOP and len(t) > 2}


def main():
    args = sys.argv[1:]
    if not args:
        print(__doc__)
        return 1
    entries, indexes = parse(args)
    if indexes:
        print("⚠️  ده فهرس خرائط. هات الخرائط الفرعية دي:\n")
        for i in indexes:
            print("   " + i)
        return 0
    if not entries:
        print("❌ مفيش <url> في الملف.")
        return 1

    by_path = {path(loc): alts for loc, alts in entries}
    en = {p: a for p, a in by_path.items() if lang_of(p) == "en"}
    es = {p: a for p, a in by_path.items() if lang_of(p) == "es"}
    # روابط إسبانية معلَنة في hreflang حتى لو مش موجودة كـ <loc> — التصدير قد يكون جزئيًا
    es_all = set(es) | {path(h) for a in by_path.values()
                        for k, h in a.items() if k.startswith("es") and lang_of(path(h)) == "es"}

    print(f"الروابط في الملف: {len(by_path)}   |   EN: {len(en)}   |   ES كـ <loc>: {len(es)}\n")

    def sec(t):
        print("=" * 70); print(t); print("=" * 70)

    print(f"روابط إسبانية معروفة (loc + hreflang): {len(es_all)}\n")

    # 1) hreflang
    sec("١) تغطية hreflang")
    no_hl = [p for p, a in by_path.items() if not a]
    only_self = [p for p, a in by_path.items() if a and not ({"en", "es"} - {k.split("-")[0] for k in a}) is False and len({k.split("-")[0] for k in a} & {"en", "es"}) < 2]
    if no_hl:
        print(f"\n❌ بلا hreflang إطلاقًا ({len(no_hl)}):")
        for p in sorted(no_hl):
            note = "  ← صفحة بحث: يجب أن تكون noindex وخارج الخريطة" if "/search" in p else ""
            print(f"   {p}{note}")
    if only_self:
        print(f"\n⚠️  hreflang ناقص لغة ({len(only_self)}):")
        for p in sorted(only_self):
            print(f"   {p}  → {sorted(by_path[p])}")
    if not no_hl and not only_self:
        print("\n✅ كل رابط يعلن en + es.")

    # 2) تبادلية hreflang
    sec("٢) تبادلية hreflang (الشرط الذي يتجاهله جوجل إن اختل)")
    broken = []
    for p, a in by_path.items():
        for lang, href in a.items():
            if lang == "x-default":
                continue
            t = path(href)
            if t in by_path and by_path[t]:
                back = {path(h) for h in by_path[t].values()}
                if p not in back:
                    broken.append((p, t))
    if broken:
        print(f"\n❌ غير متبادل ({len(broken)}):")
        for a_, b_ in broken:
            print(f"   {a_}\n      ↛ {b_} لا يشير للخلف")
    else:
        print("\n✅ كل الأزواج الموجودة في الملف متبادلة.")

    # 3) الفجوة
    sec("٣) صفحات إنجليزية بلا نسخة إسبانية")
    missing = [p for p, a in en.items()
               if not any(k.startswith("es") for k in a)]
    if missing:
        print()
        for p in sorted(missing):
            print(f"   ❌ {p}")
    else:
        print("\n✅ كل صفحة إنجليزية في هذا الملف تُعلن نسخة إسبانية.")

    declared_es = {path(h) for a in en.values() for k, h in a.items() if k.startswith("es")}
    absent = sorted(declared_es - set(es))
    if absent:
        print(f"\n⚠️  نسخ إسبانية معلَنة في hreflang لكنها غير موجودة في هذا الملف ({len(absent)}):")
        print("    (طبيعي لو التصدير جزئي — تأكد أنها تعمل فعلًا ولا ترجع 404)")
        for p in absent[:40]:
            print(f"   {p}")

    # 4) اتساق التسمية الإسبانية
    sec("٤) اتساق الروابط الإسبانية")
    issues = []
    for p in sorted(es_all):
        last = p.rstrip("/").split("/")[-1]
        if re.search(r"\baswan\b", p):
            issues.append((p, "يستخدم «aswan» بينما روابط أخرى تستخدم «asuan» — وحّدها"))
        if re.search(r"/cairo\b", p) and "el-cairo" not in p:
            issues.append((p, "«cairo» بدل «el-cairo»"))
        if last.startswith("m-s-"):
            issues.append((p, "صيغة m-s- بدل ms-"))
        if ROMAN.search(stem(last)):
            issues.append((p, "رقم روماني — تأكد أن العنوان يميّز النسخة"))
    if issues:
        print()
        for p, n in issues:
            print(f"   ⚠️  {p}\n       → {n}")
    else:
        print("\n✅ لا شيء لافت.")

    # 5) تنافس داخلي: روابط متشابهة داخل نفس اللغة
    sec("٥) تنافس داخلي محتمل (صفحات متشابهة في نفس اللغة)")
    pairs = []
    for lang, group in (("en", set(en)), ("es", es_all)):
        paths = sorted(group)
        for i in range(len(paths)):
            for j in range(i + 1, len(paths)):
                a, b = paths[i], paths[j]
                if a.rsplit("/", 1)[0] != b.rsplit("/", 1)[0]:
                    continue
                ta, tb = tokens(a) - GENERIC, tokens(b) - GENERIC
                if len(ta) < 2 or len(tb) < 2:
                    continue
                jac = len(ta & tb) / len(ta | tb)   # Jaccard: يمنع اعتبار المجموعة الفرعية تطابقًا
                if jac >= 0.6:
                    pairs.append((jac, lang, a, b))
    if pairs:
        pairs.sort(reverse=True)
        print(f"\n   {len(pairs)} زوج مرشَّح — الأعلى تشابهًا أولًا:\n")
        for jac, lang, a, b in pairs[:30]:
            print(f"   [{lang}] {jac:.0%}\n      {a}\n      {b}\n")
        if len(pairs) > 30:
            print(f"   … و{len(pairs)-30} زوج آخر.")
    else:
        print("\n✅ لا تشابه لافت في أسماء الروابط.")
    print("\n   ملاحظة: الفحص ده على أسماء الروابط فقط. التنافس الموضوعي (مقالان بعنوانين"
          "\n   مختلفين عن نفس الموضوع) يظهر في Search Console: صفحتان تظهران لنفس الاستعلام.")

    print("\n" + "=" * 70)
    print(f"الخلاصة: {len(missing)} صفحة إنجليزية بلا نسخة إسبانية معلَنة، "
          f"{len(no_hl)} بلا hreflang، {len(broken)} زوج غير متبادل.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
