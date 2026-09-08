# FLOSC Vegan Latvian Kitchen — IVR Configuration
# Flow slug suggestion: vegan-latvian-kitchen
# IVR file name: flosc_vegan_latvian_kitchen_ivr.md
# Personality: warm kitchen host — cultural pride, clear freeline, plant-based Latvian cooking.
# Content model: 14 recipes · 2 visitor · 2 guest · 10 member + PDF for members
# Member: $10 USD one-time, or access code garšīgs
# Pair with: WordPress import (14 recipes + PDF) + DA1 catalog.
# Identity: recipe freeline host — not a "lessons coach."
# Portable demo: ## Settings (YAML) applies on Create New Flow / Apply to current flow.

## Settings (YAML)
```yaml
name: Vegan Latvian Kitchen
title: Vegan Latvian Kitchen
tagline: Classic Latvian dishes, fully plant-based
slug: vegan-latvian-kitchen
status: active
default_member_level: member
default_guest_level: guest
content_item_category: vegan_latvian_recipes
free_content_item_mode: fixed
free_content_item_count: 2
free_content_item_pool_category: vegan_latvian_recipes
member_levels:
  member:
    slug: member
    name: Member
    description: All 14 recipes on the site plus cookbook PDF
  guest:
    slug: guest
    name: Guest
    description: Logged-in freeline on this flow
content_item_groups:
  0:
    quiz_id: ""
    category: vegan_latvian_recipes
companion_enabled: true
companion_content_display_mode: both
companion_show_for_visitors: true
companion_pass_page_context: true
companion_flow_slug: vegan-latvian-kitchen
companion_greeting: Chat with the kitchen
companion_subtitle: Recipes, freeline, membership
access_code: "garšīgs"
access_code_role: member
offers:
  vlkit_full_pack:
    id: vlkit_full_pack
    name: Full recipe pack + PDF
    description: All 14 vegan Latvian recipes on the site plus the cookbook PDF. One-time $10 (PayPal/Stripe keys on Payments) or access code.
    status: active
    active: true
    type: one_time
    price: 10
    display_price: "$10 one-time"
    display_format: card
    grants_level: member
    grants:
      level: member
      features:
        0: full_access
    access_codes:
      0: "garšīgs"
      1: garsigs
    pricing:
      price: 10
      currency: USD
      processor: paypal
    display_formats:
      card:
        enabled: true
```

---

# Freeline Messages (visitors)

## Welcome
MessageName: vlkit_welcome
MessageType: auto
MessageStyle: card
MessageContent: Labdien! Welcome to **Vegan Latvian Kitchen** — classic dishes, fully plant-based. As a visitor you can cook **two freeline recipes** right away: **pelēkie zirņi** and **kartupeļu pankūkas**. Log in for two more free recipes. **Members** get all **14 recipes** on the site plus the **cookbook PDF** ($10 one-time, or access code **garšīgs**). What would you like to taste first?
MessageConditions: is_visitor && first_show_session

---

## Show free recipes
MessageName: vlkit_visitor_free
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Show me the free recipes
MessageContent: Your freeline pair: (1) **Pelēkie zirņi ar “speķi”** — grey peas with smoky mushroom–tofu topping. (2) **Kartupeļu pankūkas** — crispy grated potato pancakes. Ask for either by name for the full method, or say **login** when you want **ķimeņu siers** and **aukstā zupa** next.
MessageConditions: is_visitor
Keywords: free, freeline, recipes, menu, what can i cook, catalog, list

---

## Grey peas
MessageName: vlkit_visitor_peas
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Pelēkie zirņi please
MessageContent: **Pelēkie zirņi ar “speķi”** — national dish, veganized. Soak and boil grey peas; fry onions, mushrooms and smoked tofu with paprika and liquid smoke; pile on hot. Full steps are in the recipe post. Want **kartupeļu pankūkas** next, or shall I nudge you to log in for more?
MessageConditions: is_visitor || is_guest || is_member
Keywords: zirni, zirņi, grey peas, pelekie, speki, speķi, national

---

## Potato pancakes (visitor freeline)
MessageName: vlkit_visitor_pankukas
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Kartupeļu pankūkas please
MessageContent: **Kartupeļu pankūkas** — grated potato pancakes, crisp edges, plant-side sour cream or dill. Full steps are in your freeline recipe. After zirņi and pankūkas, login opens Midsummer cheese and cold beet soup.
MessageConditions: is_visitor || is_guest || is_member
Keywords: pankukas, pankūkas, potato pancakes, kartupelu, kartupeļu

---

## Piragi (member recipe)
MessageName: vlkit_member_piragi
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: I want pīrāgi!
MessageContent: **Vegānie pīrāgi** — soft yeast dough, smoky mushroom–tofu filling, crescent shape, golden bake. Full method is in the member recipe post on the site.
MessageConditions: is_member
Keywords: piragi, pīrāgi, spekrausi, buns, pastries

---

## Why login
MessageName: vlkit_visitor_login_nudge
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Why should I log in?
MessageContent: Login makes you a **guest of this flow** and opens **two more free recipes**: **Ķimeņu siers (Jāņu siers)** and **Aukstā zupa**. **Members** get the other **ten recipes** on the site plus the **PDF** — **$10 one-time**, or access code **garšīgs**. Ready to create an account or sign in?
MessageConditions: is_visitor
Keywords: login, log in, sign in, register, account, more recipes

---

## Full book tease
MessageName: vlkit_visitor_offer_tease
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What's in the full book?
MessageContent: **14 vegan Latvian recipes** on the site — freeline four for visitors and guests, plus ten more for members (rasols, sklandrausis, burkānlaša, roast, desserts, and more) and the **downloadable PDF**. **$10 one-time**, or redeem access code **garšīgs**. Browse freeline first.
MessageConditions: is_visitor || is_guest
Keywords: full, book, all recipes, pdf, buy, purchase, member, cookbook, 10, dollar, garsigs, garšīgs

---

## About vegan Latvian
MessageName: vlkit_about
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is vegan Latvian food?
MessageContent: Latvian cooking loves rye, potatoes, grey peas, dill, caraway, mushrooms, beets, and smoke. We keep that soul — swap dairy, eggs, and speķis for plants (smoked tofu, aquafaba, cashew cream, liquid smoke). Ask for a dish by name anytime.
MessageConditions: is_visitor || is_guest || is_member
Keywords: latvian, vegan, cuisine, about, tradition, culture

---

## AI nudge
MessageName: vlkit_ai_api
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: Enable smarter kitchen chat (AI)
MessageContent: This flow is IVR + keywords until floscAdmin connects a preferred AI API under **Settings → AI** (BYOK). Then free-form questions like “how do I fix soggy pīrāgi dough?” get much smarter answers.
MessageConditions: is_visitor || is_guest || is_member
Keywords: ai, api, grok, xai, openai, smarter, intelligent

---

# Guest Messages

## Guest Welcome
MessageName: vlkit_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Welcome back — you're a **guest of Vegan Latvian Kitchen**. You keep the two visitor recipes and also get **Ķimeņu siers** and **Aukstā zupa**. Members get the other ten recipes + PDF ($10 or **garšīgs**). What shall we cook?
MessageConditions: is_guest && first_show_session

---

## Guest free pair
MessageName: vlkit_guest_free
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: My free guest recipes
MessageContent: Guest freeline: **Ķimeņu siers (Jāņu siers)** and **Aukstā zupa**, plus your visitor pair (zirņi + kartupeļu pankūkas). Say a name for the method, or ask about membership for the full 14 + PDF.
MessageConditions: is_guest
Keywords: free, guest, my recipes, janu, ķimeņu, auksta

---

## Janu siers
MessageName: vlkit_guest_janu
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Ķimeņu / Jāņu siers
MessageContent: **Ķimeņu siers (Jāņu siers)** — blend tofu, soy yogurt, starches, nutritional yeast, lemon; cook thick; press with caraway overnight; slice. Full recipe is in your guest freeline posts. Perfect with dark rye.
MessageConditions: is_guest || is_member
Keywords: janu, jāņu, kimenu, ķimeņu, siers, caraway, midsummer, cheese

---

## Auksta zupa guest
MessageName: vlkit_guest_auksta
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Aukstā zupa
MessageContent: **Aukstā zupa** — grated beets, plant kefir or thinned yogurt, cucumber, dill, spring onion, chill hard. Serve with new potatoes. Full method in your guest freeline post.
MessageConditions: is_guest || is_member
Keywords: auksta, aukstā, cold soup, beet soup, kefir

---

## Upgrade
MessageName: vlkit_guest_upgrade
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Get all 14 recipes and the PDF
MessageContent: Membership opens **all 14 recipes** on the site plus the **cookbook PDF**. **$10 one-time**, or access code **garšīgs** in the offer. Open the offer when ready — or keep cooking your four freeline recipes.
MessageConditions: is_guest
Keywords: upgrade, buy, purchase, pdf, member, offer, 10, dollar, garsigs, garšīgs, cookbook, all 14

---

## Full pack offer
MessageName: vlkit_full_pack
MessageType: offer
MessageStyle: card
OfferID: vlkit_full_pack
Price: 10
DisplayFormat: card
UserInput: Show membership offer
MessageContent: **Full recipe pack + PDF** — all 14 recipes on the site and the cookbook PDF. **$10 one-time**, or redeem access code **garšīgs**.
MessageConditions: is_visitor || is_guest
Keywords: offer, membership, buy, purchase, full pack, pdf pack, 10, dollar, garsigs, garšīgs

---

# Member Messages

## Member Welcome
MessageName: vlkit_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Labu apetīti! You have **member** access — all **14 recipes** on the site and the **cookbook PDF**. Ask by name, ask for a menu, or say “download PDF.”
MessageConditions: is_member && first_show_session

---

## Full menu
MessageName: vlkit_member_menu
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Full recipe menu
MessageContent: **1** Pelēkie zirņi · **2** Kartupeļu pankūkas · **3** Ķimeņu siers · **4** Aukstā zupa · **5** Rasols · **6** Vegānie pīrāgi · **7** Sklandrausis · **8** Rupjmaizes kārtojums · **9** Skābeņu zupa · **10** Debesmanna · **11** Kotletes · **12** Bukstiņbiezputra · **13** Burkānlaša smalkmaizītes · **14** Tofu–mushroom roast beef. Name any number or title. PDF: **Cookbook PDF — full pack**.
MessageConditions: is_member
Keywords: menu, all, list, catalog, recipes, fourteen, 14

---

## PDF
MessageName: vlkit_member_pdf
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Download the PDF
MessageContent: Your member pack is **Cookbook PDF — full pack** (slug `vegan-latvian-kitchen-pdf`). Open that post for the download link.
MessageConditions: is_member
Keywords: pdf, download, ebook, cookbook pack, print

---

## Roast beef
MessageName: vlkit_member_roast
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Tofu mushroom roast beef
MessageContent: **Tofu–mushroom “roast beef”** — deeply browned mushrooms + pressed tofu, smoke, seared crust, slice thin. Full method is in the member recipe post. Pair with potatoes or rye.
MessageConditions: is_member
Keywords: roast, roast beef, tofu mushroom, showpiece

---

## Burkanlasa
MessageName: vlkit_member_burkan
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Burkānlaša smalkmaizītes
MessageContent: **Burkānlaša smalkmaizītes ar paštaisītu burkānlasi** — marinated carrot ribbons as “lox” on cream-spread bread with dill and capers. Full method in the member recipe post.
MessageConditions: is_member
Keywords: burkan, burkān, burkanlasa, burkānlaša, smalkmaiz, carrot lox, canape

---

## How membership works
MessageName: vlkit_member_how
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: How did membership work?
MessageContent: Members join with a **$10 one-time** offer, or by redeeming access code **garšīgs**. That grants **member** on this flow — all 14 recipes + PDF.
MessageConditions: is_member
Keywords: access code, garsigs, garšīgs, how, membership, 10
