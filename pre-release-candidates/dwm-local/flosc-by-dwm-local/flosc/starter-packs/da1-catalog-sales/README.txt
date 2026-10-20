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
DCSJ.md
    FLOSC IVR / portable flow configuration for the DA1 Catalog Sales Journey.

50-catalog-items.tsv
    DA1 catalog with 50 catalog items.

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

DCSJ.md seeds the $10 offer and Member grant. It is configured for PayPal as the
starter processor; the FLOSC admin must supply valid payment credentials in the
flow's Payments settings before a live transaction can run.

UberManual.pdf is included in this pack as the demonstration product. The current
portable IVR format seeds offer copy, price, and access behavior but does not encode
a local downloadable-file attachment. After import, the FLOSC admin should place
UberManual.pdf in the site's chosen protected/download location and connect that
file to the site's post-purchase delivery method.

PERSONALITY
-----------
No personality file is included. DCSJ.md references the existing DadJokeDan
personality by personality_library_id. Change that reference in FLOSC if another
installed personality should curate the journey.

BASIC SETUP
-----------
1. Import / create the DA1 catalog from 50-catalog-items.tsv.
2. Import DCSJ.md as the IVR / flow configuration.
3. Confirm the referenced personality exists, or select another installed one.
4. Configure the payment credentials for the flow.
5. Put UberManual.pdf in the site's protected/download delivery location and
   connect it to the successful-purchase experience.
6. Test the journey as Visitor, Guest, and purchasing customer.

EXPECTED EXPERIENCE
-------------------
Visitor -> 4 DA1 samples
Guest   -> 8 DA1 samples
Offer   -> $10 UberManual
Sale    -> Member access to all 50 + delivery of UberManual.pdf

This pack is demonstration data. The intentionally silly subject matter exists to
make the access boundaries and catalog behavior easy for a FLOSC admin to test.
