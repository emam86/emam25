#!/usr/bin/env python3
"""
جرد فجوة الصفحات الإسبانية مقابل الإنجليزية — من ملف sitemap.xml

الاستخدام:
    python3 es-gap.py sitemap.xml
    curl -s https://www.luxorandaswantours.net/sitemap.xml | python3 es-gap.py -

لو الملف فهرس خرائط (sitemap index) هيطبعلك أسماء الخرائط الفرعية عشان تجيبها.
يقبل أكتر من ملف مرة واحدة:
    python3 es-gap.py sitemap-en.xml sitemap-es.xml
"""
import re, sys, collections

# اللواحق اللي بتتشال عشان نوصل لاسم الباخرة/الجولة نفسه
SUFFIXES = [
    "-crucero-dahabiya-por-el-nilo", "-crucero-por-el-nilo", "-crucero-nilo",
    "-dahabiya-nile-cruise", "-luxury-nile-cruise", "-nile-cruise", "-cruise",
]
PREFIXES = ["m-s-", "ms-", "mv-"]
ROMAN = re.compile(r"-(i{1,3}|iv|v|vi{1,3}|ix|x)$")


def read_urls(sources):
    urls, indexes = [], []
    for src in sources:
        raw = sys.stdin.read() if src == "-" else open(src, encoding="utf-8", errors="replace").read()
        locs = re.findall(r"<loc>\s*(.*?)\s*</loc>", raw, re.I | re.S)
        if re.search(r"<sitemapindex", raw, re.I):
            indexes.extend(locs)
        else:
            urls.extend(locs)
    return urls, indexes


def stem(slug):
    """اسم الكيان بالشرطات، بعد شيل لاحقة اللغة وبادئة الباخرة."""
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


def core(slug):
    """مفتاح المطابقة بين EN و ES — بلا شرطات ولا رموز."""
    return re.sub(r"[^a-z0-9]", "", stem(slug))


def split(urls):
    buckets = {"en": collections.defaultdict(dict), "es": collections.defaultdict(dict)}
    other = []
    for u in urls:
        m = re.search(r"/(en|es)/([^/]+)/(.+?)/?$", u)
        if not m:
            other.append(u)
            continue
        lang, section, slug = m.group(1), m.group(2), m.group(3)
        buckets[lang][section][core(slug)] = slug
    return buckets, other


def main():
    args = [a for a in sys.argv[1:] if a != "-h"]
    if not args:
        print(__doc__)
        return 1

    urls, indexes = read_urls(args)
    if indexes:
        print("⚠️  ده فهرس خرائط. هات الخرائط الفرعية دي وشغّل السكربت عليها:\n")
        for i in indexes:
            print("   " + i)
        return 0
    if not urls:
        print("❌ مفيش <loc> في الملف. اتأكد إنه sitemap.xml صحيح.")
        return 1

    buckets, other = split(urls)
    en_sections = set(buckets["en"]) | set(buckets["es"])
    print(f"إجمالي الروابط: {len(urls)}   |   EN: {sum(len(v) for v in buckets['en'].values())}"
          f"   |   ES: {sum(len(v) for v in buckets['es'].values())}\n")

    total_missing = 0
    for section in sorted(en_sections):
        en, es = buckets["en"].get(section, {}), buckets["es"].get(section, {})
        missing = sorted(set(en) - set(es))
        orphan = sorted(set(es) - set(en))
        print("=" * 72)
        print(f"القسم /{section}/   —   EN: {len(en)}   ES: {len(es)}   ناقص بالإسباني: {len(missing)}")
        print("=" * 72)
        if missing:
            print("\n❌ صفحات إنجليزية بلا مقابل إسباني:")
            for k in missing:
                print(f"   /en/{section}/{en[k]}")
            total_missing += len(missing)
        if orphan:
            print("\n⚠️  صفحات إسبانية بلا مقابل إنجليزي (أو اسمها مختلف تمامًا — راجعها يدويًا):")
            for k in orphan:
                print(f"   /es/{section}/{es[k]}")
        print()

    # مشاكل تسمية داخل القسم الإسباني
    print("=" * 72)
    print("مشاكل التسمية في الروابط الإسبانية")
    print("=" * 72)
    flagged = False
    for section, pages in buckets["es"].items():
        for _, slug in sorted(pages.items()):
            notes = []
            if slug.startswith("m-s-"):
                notes.append("صيغة m-s- بدل ms-")
            if re.search(r"[a-z]{4,}-[a-z]{4,}-[a-z]{4,}-crucero", slug):
                notes.append("اسم طويل — الاسم الشائع مدفون")
            if ROMAN.search(stem(slug)):
                notes.append("يحمل رقمًا رومانيًا — تأكد إن العنوان يوضّح أي نسخة")
            if notes:
                flagged = True
                print(f"   /es/{section}/{slug}\n      → " + " · ".join(notes))
    if not flagged:
        print("   لا شيء لافت.")

    # أزواج الأرقام الرومانية — مؤشر تنافس داخلي
    print("\n" + "=" * 72)
    print("تنافس داخلي محتمل (نفس الاسم بأرقام رومانية مختلفة)")
    print("=" * 72)
    for lang in ("en", "es"):
        fams = collections.defaultdict(list)
        for section, pages in buckets[lang].items():
            for _, slug in pages.items():
                base = re.sub(r"[^a-z0-9]", "", ROMAN.sub("", stem(slug)))
                fams[base].append(f"/{lang}/{section}/{slug}")
        for base, members in sorted(fams.items()):
            if len(members) > 1:
                print(f"   [{base}]")
                for m in members:
                    print(f"      {m}")

    print(f"\n\nالخلاصة: {total_missing} صفحة إنجليزية محتاجة نسخة إسبانية.")
    if other:
        print(f"({len(other)} رابط خارج نمط /lang/section/slug — صفحات رئيسية أو فئات غالبًا)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
