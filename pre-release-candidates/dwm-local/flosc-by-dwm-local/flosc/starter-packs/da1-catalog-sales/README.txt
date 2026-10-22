FLOSC STARTER PACK
DA1 Catalog Sales Journey (DCSJ)
================================

PURPOSE
-------
This starter pack demonstrates the second major FLOSC content pattern:
using a content-agnostic DA1 catalog to create a conversational sales journey.

The example catalog is "The Extremely Ordinary Instruction Manual Collection."
It contains 50 deliberately over-serious manuals about ordinary household tasks.
The customer journey leads to a one-time sale of the compiled UberManual PDF for
$10 USD.

FILES
-----
flow_ivr.md
    FLOSC IVR / portable flow configuration for the DA1 Catalog Sales Journey.

catalog.txt
    DA1 catalog with 50 catalog items.

content.json
    Ten demonstration posts used by the flow's WordPress content index.

pack.json
    Starter-pack manifest used by the FLOSC installer.

UberManual.pdf
    The compiled paid demonstration product containing all 50 manuals.

DA1 SCHEMA
----------
The TSV follows the content-agnostic DA1 model:

Required DA1 control columns:
    Row Key
    Parent Key
    Catalog Key
    Item Type
    Flow Scope
    VGM
    Delivery Instruction
    Delivery Rule
    Fallback Order
    Status

Catalog-defined payload columns used by this starter:
    Title
    Description
    Subject
    Identifier
    Type
    Relation

Title, Description, Subject, Identifier, Type, and Relation are Dublin Core-
compatible descriptive metadata. They are used here because they make sense for
this catalog. DA1 does not require every catalog to use these payload fields.
Additional catalog-specific parameters can be added freely.

ACCESS MODEL
------------
Freeline / Visitor:
    Items 1, 11, 21, 35

Guest:
    All Freeline items plus Items 2, 12, 22, 36

Member / customer:
    All 50 items

The row-level VGM values are cumulative:
    Freeline rows = Visitor Guest Member
    Guest rows    = Guest Member
    Member rows   = Member

SALE
----
Offer ID: dcsj_ubermanual
Product: The UberManual
Price: $10 USD
Type: one-time purchase
Grant: member

flow_ivr.md seeds the $10 offer and Member grant. It is configured for PayPal as the
starter processor; the FLOSC admin must supply valid payment credentials in the
flow's Payments settings before a live transaction can run.

UberManual.pdf is included in this pack as the demonstration product. The starter-
pack installer copies it into the WordPress Media Library. The FLOSC admin must
connect that local attachment to the site's chosen protected post-purchase delivery
method before accepting payment.

PERSONALITY
-----------
No personality file is included. flow_ivr.md references the existing DadJokesDan
personality by personality_library_id. Change that reference in FLOSC if another
installed personality should curate the journey.

BASIC SETUP
-----------
1. Install this starter pack from FLOSC's Starter Packs screen.
2. Confirm the referenced DadJokesDan personality exists, or select another installed
   personality.
3. Confirm the catalog is assigned to dcsj_ivr.md on the DA1 screen.
4. Configure the payment credentials for the flow.
5. Connect the imported UberManual Media Library attachment to the site's chosen
   protected post-purchase delivery method.
6. Test the journey as Visitor, Guest, and purchasing customer.

EXPECTED EXPERIENCE
-------------------
Visitor -> 4 DA1 samples
Guest   -> 8 DA1 samples
Offer   -> $10 UberManual
Sale    -> Member access to all 50 + delivery of UberManual.pdf

This pack is demonstration data. The intentionally silly subject matter exists to
make the access boundaries and catalog behavior easy for a FLOSC admin to test.
