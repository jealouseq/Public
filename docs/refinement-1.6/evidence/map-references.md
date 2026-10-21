# Verified 21st.dev map references — 2026-10-03

The user approved the exact Funbox geographic zones. These references concern presentation only: preserve all six polygons from `funbox-delivery.geojson` without redrawing them by eye.

1. **Recommended visual: mapcn / MarkerContent** — https://21st.dev/@mapcn/components/mapcn-marker-content
   - Verified metadata: MIT; dependencies `maplibre-gl`, `lucide-react`. Public demo uses a real geographic `<Map>` with center/zoom, marker content and tooltips.
   - Visually inspected preview: https://cdn.21st.dev/reapollo/mapcn-marker-content/default/preview.1780350008960.png — dark street basemap, subtle labels, thin rounded border and quiet blue markers. Strongest fit for JOYRENT's premium dark framing.
   - The marker demo does not itself show zones or a legend. Verified upstream docs explicitly support GeoJSON fill/outline zone overlays: https://www.mapcn.dev/docs/geojson . Zoom, compass, location and fullscreen controls: https://www.mapcn.dev/docs/controls .
   - Public demo: https://cdn.21st.dev/reapollo/mapcn-marker-content/default/code.demo.1780350008960.tsx

2. **Recommended interaction reference: Interactive Map / scott clayton** — https://21st.dev/@lovesickfromthe6ix/components/interactive-map
   - Verified metadata: `no-license`; dependencies `leaflet`, `react-leaflet`, `react-leaflet-cluster`.
   - Public demo explicitly passes `polygons`, fill/outline styles, `zoom`, map-click callbacks and `enableControls={true}`. Search, geolocation, satellite/traffic controls and clustering are extras that JOYRENT's six zones do not need.
   - Visually inspected preview: https://cdn.21st.dev/lovesickfromthe6ix/interactive-map/default/preview.1788804028295-d40fbfb8-e1e5-48f4-aa6d-3a1ed5676402.webp — a real labeled street map with +/− zoom and translucent polygon/circle overlays. Its light theme would need JOYRENT's dark styling.
   - Public demo: https://cdn.21st.dev/lovesickfromthe6ix/interactive-map/default/code.demo.1788804028295-d40fbfb8-e1e5-48f4-aa6d-3a1ed5676402.tsx
   - The demo's polygon arrays use `[latitude, longitude]`; GeoJSON uses `[longitude, latitude]`. Vanilla Leaflet can load the verified GeoJSON directly. Use this component as a design/interaction reference because its page lists no source license.

3. **Optional compact-card reference: Expanded Map / Dark map tiles** — https://21st.dev/@dev.shejanmahamud/components/expanded-map/expanded-map-dark
   - Verified metadata: `no-license`; dependencies `next`, `motion`. Public demo passes `tileProvider="carto-dark"` to `LocationMap`, with latitude/longitude and a location label.
   - Visually inspected preview: https://cdn.21st.dev/user_32IAZZ0jCu5Ox98TEx5aAsxVNyq/expanded-map/expanded-map-dark/preview.1787663189319.png — a collapsed Paris location card. Static preview does not prove expanded dark tiles, pan or zone overlays.
   - Public demo: https://cdn.21st.dev/dev.shejanmahamud/expanded-map/expanded-map-dark/code.demo.1784926050725-a76b2a48-ecc4-45dd-ba20-b906bd4934ee.tsx
   - Lower fit because the map starts hidden inside a card. Use only as a frame idea; no source license grant is listed on its 21st page.

**Recommended application:** visible real Odesa street basemap with mapcn's restrained dark style; exact GeoJSON shapes with translucent green/yellow/red fills and clear outlines; +/− zoom, pan, fit-all-zones control and persistent readable three-zone price legend. Vanilla Leaflet fits the existing WordPress frontend without React/Next wrappers. Omit the New York/London/Paris demo markers and decorative globes.

Evidence: read the live map category, all three exact component pages and `.md` metadata, three public demo files and mapcn GeoJSON/controls docs; inspected all three static previews. No registry install, paid API use, source-file edit or live-demo interaction test performed.
