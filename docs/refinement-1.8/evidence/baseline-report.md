# JOYRENT 1.8 — fresh pre-change baseline

Captured live `https://flowers-luxury.shop/` and packaged local WordPress `http://localhost:8080/` on 2026-10-03 at 11:32 UTC. Each origin has eight actual viewport captures: UA/RU at 1440×1000 and 390×844, process and delivery. Fresh isolated contexts, DPR1, reduced motion, fonts and visible images loaded/decoded, incidental focus blurred. All non-GET/HEAD requests were blocked before transmit; forms were untouched. Zero mutation requests, browser exceptions, HTTP failures or horizontal overflow in these baseline contexts.

Both origins served identical 1.7 frontend bytes: `main-CNh65H_Y.js`, SHA256 `99b37d873bfc96b85b555b7255a2aca4d7e755d46f4137756d1c94d9eac25b08`; `main-hpKQBRcV.css`, SHA256 `3a577b6042e4db7b60caebb9ebed5e1a53d222d18eff2a27e42967fa86ac5fdd`. This is runtime evidence, independent of the version now being edited in source.

Visual inspection confirms three left aligned steps: desktop icons sit near the left edge of each column; mobile uses an icon rail beside left aligned titles/copy. Delivery uses the small portrait outline within the text column, a separate fee column and a desktop map opener below the fees. The existing mobile pill remains centered, 196×52px with round corners. No lazy Leaflet or geometry chunk loads before opening the dialog.

Live delivery intro remains the concise service sentence. The local saved delivery intro repeats the fee conditions already present in the fee table. Neither site's saved business settings were changed by QA. The baseline captures intentionally preserve that difference.

Evidence: `local-baseline/manifest.json` and `live-baseline/manifest.json` contain individual PNG hashes, requests, served asset hashes and timestamps. `baseline-summary.json` contains measured node/title/body centers and opener geometry. Screens are in each origin's `screens/` directory; `inspection.png` is a contact sheet used for review. Remote capture was made on the authorized srv1999219 device; transferred ZIP SHA256 is `0acf0f30e24d9c641c4259da44ba552c9271ae3dca0b3807c5ad67e7762f579e`.

These are observations of the pre-change pages, not acceptance claims for the 1.8 build. The final matrix will run only after root supplies the new packaged hashes.
