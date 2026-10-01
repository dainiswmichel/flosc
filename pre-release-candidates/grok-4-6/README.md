# FLOSC 8.0.0 — Grok candidate V118

Plugin header stays 8.0.0. This folder is candidate V118.

V118 is the V117 tree. The companion frame stays visible. Personality attach now answers as soon as the flow row is stored.

## What was broken

On AI → This flow, choosing a personality disables the dropdown and shows “Attaching…”. The handler writes `personality_library_id`, then `updated_option` rewrites that flow’s `.md` before any JSON is sent. The designer reloads only after that response. The row for `dainis_net_ivr` was already `dadjokedan` while the header still read Br3nda and the control still read “Attaching…”.

## Runtime delta from V117

`includes/flosc-personality-library.php`: nonce is checked first. The IVR file mirror is detached for the option write. The stored id is read back. JSON is sent. On shutdown the response is flushed with `fastcgi_finish_request()` when that function exists, and then `flosc_sync_flow_option_to_ivr_file()` mirrors the file.

`admin/ai-configuration.php`: the same request times out at 20 seconds. A timeout, or an HTTP 200 body that is not JSON, reloads the page onto the stored personality. A 4xx or 5xx response re-enables the dropdown and shows the server message.

Chat turns already resolve the attached library row on each prompt. That path is unchanged.

## Exact artifact

```text
916762c2f9a681559371c290b49cfb8711f2230a34dc7bd68cc7f78e0401b3f7  flosc.zip
```

282 entries, 242 files, one `flosc/` root, `tests/` absent, `unzip -t` clean. Version and Stable tag 8.0.0. 2,818,472 bytes.

## Measured in this build

- Attachment contract: passed, including the mirror-after-response assertions.
- Plugin Check was not run on this zip.
- The AI settings dropdown was not click-tested on dainis.net.
- This candidate was not deployed. Live dainis.net remains V117.

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```
