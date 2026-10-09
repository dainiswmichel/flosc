# FLOSC 8.0.0 — Codex candidate V134 source checkpoint

V134 starts from the exact V133 commit
`fce961176dbd7af37b9fbe02f879ae8140234420`. The plugin version and stable tag
remain 8.0.0. The plugin header records V133 as the parent source.

## Scope

- Removed four known incorrect or unavailable citation entries from the sample
  personality catalog.
- Kept the Hildegard of Bingen, Teresa of Ávila, Maat, and Desert Fathers
  archetypes and their personality instructions unchanged.
- Clarified that the catalog contains optional sample references maintained by
  their publishers and that reference pages may change or become unavailable.
- Retained V133's **Include influences** behavior without modification.

The removed references were:

- `https://archive.org/details/hildegard-of-bingen-scivias`
- `https://www.gutenberg.org/ebooks/8120`
- `https://www.gutenberg.org/ebooks/15121`
- `https://archive.org/details/sayings-of-the-desert-fathers`

## Build status

This commit is a source checkpoint for administrator testing. `flosc.zip`,
`SHA256SUMS`, and `build-manifest.json` remain the previously verified V133
artifacts and have not been rebuilt or deployed. They must not be represented
as V134 artifacts.
