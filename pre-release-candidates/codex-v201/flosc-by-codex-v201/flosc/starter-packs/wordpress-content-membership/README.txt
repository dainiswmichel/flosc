FLOSC WORDPRESS CONTENT MEMBERSHIP JOURNEY STARTER PACK
=======================================================

PURPOSE
-------
This starter pack demonstrates the WordPress-content version of a FLOSC
Freeline -> Login -> Offer -> Sale -> Content journey.

The product being sold is Membership access to the complete 100-post library.
The sample content is intentionally goofy so a FLOSC administrator can test
access, conversation, navigation, offers, and post-purchase behavior without
confusing the demonstration with real customer content.

FILES
-----
WCMJ.md
    FLOSC IVR / flow configuration for the WordPress Content Membership Journey.

100-content-items.xml
    Standard WordPress WXR 1.2 import containing the actual 100 sample posts.

README.txt
    This file.

CONTENT ACCESS
--------------
Visitor / Freeline:
    Items 1-10

Guest:
    Items 1-30 (Freeline + Items 11-30)

Member:
    Items 1-100

The WXR creates three child categories under the starter-pack parent category:
    FLOSC Starter - Freeline
    FLOSC Starter - Guests
    FLOSC Starter - Members

The posts also contain FLOSC starter access metadata.

PERSONALITY
-----------
No personality is bundled.

WCMJ.md references the existing FLOSC personality library ID:
    bubblybetty

A FLOSC administrator may keep that personality or assign another installed
personality to the flow.

MEMBERSHIP OFFER
----------------
WCMJ.md includes an IVR offer entry with Offer ID:
    wcmj_membership

Its job is to sell Membership access to all 100 posts.

The starter pack intentionally does NOT invent a live membership price or ship
payment credentials. After importing the flow, configure the real price,
payment processor, currency, and any provider-specific fields in FLOSC -> Offers,
then activate the offer.

This keeps the starter pack portable while still supplying the complete journey
structure and offer trigger.

INSTALL / TEST ORDER
--------------------
1. Import 100-content-items.xml with the standard WordPress Importer.
2. In FLOSC IVR Management, create/import a flow from WCMJ.md.
3. Confirm the flow uses the WordPress Content Membership Journey settings.
4. Confirm BubblyBetty exists, or select another installed personality.
5. Open FLOSC -> Offers and configure wcmj_membership with the desired price and
   payment processor, then activate it.
6. Test the journey as Visitor, Guest, and Member.

EXPECTED JOURNEY
----------------
Visitor:
    Can explore Items 1-10 and is invited to log in for more.

Guest:
    Can explore Items 1-30 and can be shown the Membership offer.

Sale:
    Successful purchase grants FLOSC member access for this flow.

Member:
    Can explore all 100 posts, including Item 46:
    Why Pigeons Never Pay Parking Tickets.

NOTES
-----
- This pack demonstrates WordPress post content, not DA1 catalog content.
- The separate DA1 Catalog Sales Journey starter pack demonstrates DA1.
- No AI provider keys, payment credentials, or personality files are included.
