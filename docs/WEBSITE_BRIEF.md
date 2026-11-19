# Website Brief & Sitemap — Yashadhya Organics (Phase 7A, task 7A.1)

Public website for **Yashadhya Organics — Dairy Farm**. Built in the same Laravel app (Blade + Bootstrap 5 + jQuery,
no npm). Owner's 10 rules: `docs/PROGRESS.md` → Phase 7A. Sample content is used until launch (DECISIONS #204);
everything sample is on the **Go-live checklist** at the end of this file.

## 1. Goal and visitors

**Goal:** tell people clearly what we offer (pure milk from desi cows and buffaloes, home delivery, bulk supply) and turn
visitors into **enquiries (leads)** that the team follows up from the ERP.

| Visitor | What they want | Where they go |
|---|---|---|
| Family / household (main) | Pure milk at the door every morning, price, trust | Home → Products → Home delivery → Enquiry |
| Hotel, sweet shop, tea stall (B2B) | Daily bulk milk, steady rate | Bulk orders → Enquiry |
| Farmer (village) | Sell milk to our collection center, fair rate on fat/SNF | Farmers → Call / Enquiry |
| Cattle buyer / seller | Buy or sell desi cow / buffalo | Our herd → Cattle trade enquiry |
| Google searcher | "A2 desi cow milk near me", "Gir cow milk" | Any page via SEO |

## 2. Brand and design direction

- **Feel:** a real Indian family farm — earthy, clean, warm. Big real photos, short honest text, calm motion.
  Not a supermarket app, not a template with sliders and pop-ups.
- **Colours (from the logo, Appearance defaults):**

  | Token | Light (day) | Dark (night) | Use |
  |---|---|---|---|
  | `--w-primary` forest green | `#093F1E` | `#23A541` | Headings, header, main buttons |
  | `--w-leaf` leaf green | `#176C2B` | `#23A541` | Links, icons, badges |
  | `--w-accent` saffron | `#FD8D00` | `#FD8D00` | Call-to-action buttons, highlights (sparingly) |
  | `--w-bg` page | `#F8F4E8` cream | `#0E1A12` deep green-black | Page background |
  | `--w-surface` cards | `#FFFFFF` | `#16261B` | Cards, header |
  | `--w-text` | `#1C2A20` | `#F8F4E8` | Body text |
  | `--w-muted` | `#5E6B61` | `#B9C2B4` | Small text |

  Text on saffron is always dark (`#1C2A20`) for contrast. Dark mode uses `public/yashadhya-logos-dark/`.
- **Font (rule 5):** one family for everything — **Mukta** (Devanagari + Latin in one font, so Hindi and English look the
  same), weights 400 / 600 / 800, self-hosted WOFF2 in `public/vendor/fonts/mukta/`. Fixed type scale as CSS variables
  (mobile → desktop): body 16 → 17 px, small 14 px, h4 18 → 20, h3 22 → 26, h2 26 → 34, h1 32 → 48. Line height 1.6
  (Devanagari needs room). No other sizes in the CSS.
- **Shapes:** radius 12 px on cards, 999 px on buttons; soft shadow; a small saffron leaf / sun-arc motif from the logo
  as section divider (inline SVG, no image request).
- **Day / night:** toggle in header (sun / moon icon); first visit follows the phone setting; choice saved in a cookie
  so the server renders the right theme (no flash).
- **Language:** Hindi default. Header switch "हिं / EN" keeps the same page. `/` Hindi, `/en/...` English.

## 3. What we take from reference sites (rule 9)

| Site | What we take | What we leave |
|---|---|---|
| Akshayakalpa (akshayakalpa.org) | Ecosystem story in tabs (Cows · People · Environment · Food); 3-step "how it works"; farm-visit invitation; delivery areas in footer | Mega-menu of 20 products, repeated pop-ups |
| Sid's Farm (sidsfarm.com) | "Free from" claims strip (no adulterants / antibiotics / hormones / preservatives); quality & test reports page; visit-the-farm; bulk order link | Animated counters with big claims, app-store banners |
| Gir Organic (girorganic.com) | How to present desi breed + A2 + bilona ghee; "Why us" values with founder quote; story timeline; FAQ on home page; farm video | Long e-commerce category lists, cart |
| Country Delight (countrydelight.in) | Clear delivery promise (time window, daily / alternate-day plans, pause on holiday) | Huge product grid, discount banners, keyword-stuffed SEO text |
| Straus Family Creamery (strausfamilycreamery.com) | Calm, photo-led family-farm look; short all-caps labels; "Discover / Learn" links to deeper pages | Store locator, newsletter (out of scope) |

**Combined result:** Gir Organic's breed story + Sid's Farm's trust/quality + Akshayakalpa's ecosystem tabs and steps +
Country Delight's clear delivery promise, in Straus's calm photo-first style — and everything ends at one simple
**enquiry** (form, call or WhatsApp link). No cart, no login, no app.

## 4. Sitemap

All pages exist in Hindi (`/...`) and English (`/en/...`). Slugs are English in both for clean URLs.

| # | Page | URL | Purpose |
|---|---|---|---|
| 1 | Home / मुख्य पृष्ठ | `/` | Story in one scroll, ends in enquiry |
| 2 | About / हमारे बारे में | `/about` | Family, farm, values, timeline, team |
| 3 | Our herd / हमारी गौशाला | `/our-herd` | Desi cow + buffalo breeds, how they are cared for, cattle trade enquiry |
| 4 | Products / उत्पाद | `/products` | All products with price or "ask for price" |
| 5 | Product detail | `/products/{slug}` | Photos, details, packing, price, enquiry |
| 6 | Home delivery / घर पर डिलीवरी | `/home-delivery` | How it works, plans, timings, area, pause / billing |
| 7 | Bulk orders / थोक ऑर्डर | `/bulk-orders` | Hotels, sweet shops, events — daily supply |
| 8 | Farmers / किसान भाई | `/farmers` | Sell milk to our collection center: fat/SNF testing, fair rate, payment cycle |
| 9 | Quality / गुणवत्ता | `/quality` | Testing, hygiene, "free from" list, lab reports |
| 10 | Blog / लेख | `/blog`, `/blog/{slug}` | Articles (A2 milk, desi breeds, ghee, recipes) |
| 11 | FAQ / सवाल-जवाब | `/faq` | Common questions, grouped |
| 12 | Contact / संपर्क | `/contact` | Form, phone, WhatsApp link, address, map, timings |
| 13 | Thank you | `/thank-you` | After enquiry: what happens next |
| 14 | Privacy · Terms · Refund | `/privacy`, `/terms`, `/refund-policy` | Legal |
| — | 404 page, `sitemap.xml`, `robots.txt` | | |

**Header menu (desktop):** Logo · Home · Products · Home delivery · Our herd · Quality · About · Contact ·
[हिं/EN] [☀/☾] · **Enquiry** button (saffron). "Bulk orders", "Farmers", "Blog", "FAQ" sit in the footer and in a
"More" dropdown. **Mobile:** logo + menu button (full-screen menu) + fixed bottom bar with **Call · WhatsApp · Enquiry**.

**Footer:** short about + logo · quick links · products · address, phone, email, timings · delivery areas · social icons
· FSSAI number · legal links · © year.

## 5. Page sections

**Home**
1. Hero — real photo (cows at dawn), headline "देसी गाय का शुद्ध दूध, हर सुबह आपके घर", 1-line promise, buttons
   "दूध मंगवाएं" (enquiry) + "हमारी गौशाला देखें"; trust line (fresh daily · no adulteration · tested).
2. "Free from" strip — no water mixing, no preservatives, no hormones, no powder milk.
3. What we offer — 4 cards: A2 desi cow milk · buffalo milk · home delivery · bulk supply (each → its page).
4. Our desi breeds — breed cards (Gir, Sahiwal, Tharparkar, Murrah) with photo and one line each.
5. From our farm to your glass — 4 steps: grazing & feed → hygienic milking → testing & chilling → morning delivery.
6. Products preview — 4–6 products with price / "ask for price".
7. Home delivery in 3 steps — enquire → choose quantity & plan → milk at your door by 7 AM; pause anytime, monthly bill.
8. Why Yashadhya — values (desi breeds only · own herd · tested every day · fair to farmers) + founder quote and photo.
9. Ecosystem tabs — Cows · Farmers · Environment · Family (Akshayakalpa idea).
10. Reviews — 3 customer reviews (sample in design only; real ones at launch).
11. FAQ — 5 top questions (with link to full FAQ).
12. Enquiry band — short form (name, phone, interest) + Call / WhatsApp.

**About:** story (why we started) · timeline · values · family / team photos · farm visit invitation · enquiry band.
**Our herd:** desi cow intro (A2) · breed cards with details (origin, milk, nature) · buffalo (Murrah) · care: feed,
shade, water, vet, no hormones · gallery · cattle buy / sell enquiry (owner confirms if shown).
**Products list:** filter chips (Milk · Ghee · Curd/Paneer) · product cards (photo, name, packing, price / ask) ·
**Product detail:** gallery · description · packing sizes · storage · price · "Enquire / Order" · related products.
**Home delivery:** steps · plans (daily / alternate day / custom) · delivery time · area list · pause, holiday, billing
monthly · bottle return · FAQ · enquiry.
**Bulk orders:** who we supply · daily quantity & rate on enquiry · reliability points · enquiry with business name.
**Farmers:** how collection works (fat/SNF test in front of you, rate chart, slip) · payment cycle · center address &
timings · call / enquiry.
**Quality:** testing at collection and before dispatch · hygiene steps · "free from" list · lab reports (download) ·
FSSAI.
**Blog:** list with category chips, cards, pagination · article page with cover, reading time, share links, related.
**FAQ:** accordion grouped (milk, delivery, payment, farm). **Contact:** form, call, WhatsApp, email, map, timings.

## 6. Enquiry form (UI in Stage 1, saved as lead in Stage 2)

Name*, phone* (10-digit Indian mobile), area / locality, interest* (home delivery · bulk · product · farmer · cattle ·
other), product (optional), quantity per day (optional), message. Hidden: page, language, UTM source, honeypot.
After submit → Thank-you page. Every form also offers Call and a WhatsApp **link** (no API — Phase 8).

## 7. Sample content plan (until launch)

- **Business name:** Yashadhya Organics — Dairy Farm. Tagline (hi): "शुद्धता परंपरा की" / (en): "Purity, the desi way".
- **Breeds (sample):** Gir, Sahiwal, Tharparkar (desi cows), Murrah (buffalo).
- **Products (sample):** A2 desi cow milk (500 ml / 1 L, ₹90/L), buffalo milk (₹70/L), A2 bilona ghee (500 ml,
  ask for price), dahi, paneer — marked "sample" in data.
- **Delivery (sample):** 5:30–7:30 AM, daily / alternate day, monthly bill.
- **Contact (sample):** address "Village —, District —, State", phone `+91 90000 00000`, email `hello@example.com`.
- **Photos:** Pexels / Unsplash free-licence photos of Indian cows, buffaloes, milk, farm; WebP in
  `public/images/website/`; source of each in `public/images/website/CREDITS.md`.
- **Text:** written by Claude in Hindi and English; legal pages are drafts.

## 8. Go-live checklist (owner replaces before launch)

- [ ] Real photos: herd, each breed, farm, milking, delivery, family / team (and delete unused stock photos)
- [ ] Breeds and herd count
- [ ] Products, packing, prices (or "ask for price")
- [ ] Delivery areas, timings, plans, minimum order
- [ ] Address, Google Maps pin, phone, WhatsApp number, email, office timings
- [ ] Social links (Instagram, Facebook, YouTube)
- [ ] FSSAI licence number; lab reports (PDF)
- [ ] Farm story, founder name / photo / quote, timeline years
- [ ] Real customer reviews (with permission) — remove all sample reviews and numbers
- [ ] Show cattle buy / sell enquiry? Show farmers page? (yes / no)
- [ ] Domain + SSL; Google Search Console; Google Business Profile link
- [ ] Privacy, terms, refund text checked
- [ ] Hindi text checked by a native reader
