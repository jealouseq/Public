# Real landscape delivery basemap

Created `wordpress/joyrent/assets/images/delivery-basemap-odessa.webp` from actual OpenStreetMap vector geometry, without generated roads, a generated coastline, external runtime loading, raster tile archival or CARTO imagery. The image is 1280×880 retina for a logical 640×440 stage, 26,934 bytes. It contains geography only; the six approved delivery polygons stay in the independent overlay owned by the map builder.

Asset SHA-256: `e2ab79de5709088c0294d5524b0a4a0b5a7519f160ad045174e33b49889079c5`.

## Public provenance and source offer

- `wordpress/joyrent/assets/data/delivery-basemap-LICENSE.md`: ODbL attribution, exact Overpass queries, source/renderer limits.
- `wordpress/joyrent/assets/data/delivery-basemap-osm.geojson.gz`: compressed machine-readable source database with 2,809 OSM features, 280,644 bytes. Contains original coordinates, public OSM IDs and only cartographic tags. No customer/operator private data.
- `wordpress/joyrent/assets/data/delivery-basemap-manifest.json`: projection coefficients, geographic and EPSG:3857 bounds, source timestamps, source/raster hashes and verified sea-label point.

Visible attribution must read **©OpenStreetMap contributors**, linked to https://www.openstreetmap.org/copyright . Source database is ODbL 1.0: https://opendatacommons.org/licenses/odbl/1-0/ . The public reusable extract and license travel with the theme assets. No CARTO attribution applies because CARTO was not used.

## Source and geometry

Public endpoint: https://overpass-api.de/api/interpreter . The authorized connected remote reader fetched a bounded actual OSM extract successfully. The extract has 775 secondary, 723 tertiary, 672 primary and 196 trunk road ways; 92 coastline ways; 349 closed water ways. Northern estuaries were supplemented from exact OSM multipolygon relations 2248813 (Kuyalnik) and 2248818 (Khadzhibey). Source timestamps are 2026-10-03T12:27:36Z and 2026-10-03T12:32:36Z.

Coastline ways join by original exact shared endpoints. The two open coastal chains cross the viewport borders; their sea-polygon closure edges are outside the visible frame. A closed island ring is retained. Both estuary relations assemble into closed original rings without invented connecting segments. Roads are projected original polylines. The palette/stroke widths are JOYRENT cartographic styling. Minor streets and buildings are omitted for clarity; the map is a delivery-region overview.

Supplemental broad water-relation queries timed out; the targeted two-relation query succeeded. Natural Earth lakes were inspected as a fallback, but no Natural Earth data was used. Local executor networking is restricted; source reads used the authorized remote URL reader, not a direct-network bypass.

## Exact image alignment

Uniform Web Mercator uses the builder’s `work/refinement-1.8/landscape-map/projection.json` unchanged:

```text
x=(lng*pi/180-0.5362297870994994)*55563.326671730516+320
y=(-log(tan(pi/4+lat*pi/360))-(-0.9178932585594465))*55563.326671730516+220
```

WGS84 bounds: west30.39372611729199, east31.053681182707997, south46.303969452017704, north46.61651612659042. EPSG:3857 metres: west3383414.1146871643, east3456879.9765157155, south5829195.064465009, north5879702.844472137. Raster coordinates are exactly2×logical coordinates; there is no crop offset, tile origin or nonuniform scaling.

The image and overlay must use the same 640:440 aspect ratio and must not be independently stretched/cropped. All 6 approved polygons / 219 vertices fit the frame with 22px vertical padding.

Verified sea-label point is longitude30.95, latitude46.44. It projects to logical[539.4538258581458,248.807902337947], equivalent to84.28966029033528%left/56.54725053135159%top. It is inside the sea polygon determined by the original coast, outside closed coastal islands, and samples the rendered sea color. The geographic sea label can be placed there without inventing a coastal landmark.

## Verification

Inspected the rendered 1280×880 PNG: full north/south regional coast, limans and street geometry are coherent; sea occupies the right, with the city near the center. Recomputed the published raster/source hashes, decoded the gzip source database, checked both multipolygon IDs, verified all 219 zone vertices fit the logical image and fixed public asset permissions to 0644 / source-directory permissions to 0755. Story/CSS/packaging were not edited by this agent. Browser composition, visible attribution and responsive cropping checks are delegated to the independent builder/QA agents.

Reproduction script and raw API responses remain in `work/refinement-1.8/landscape-map/`. Published source data and manifest provide the source offer and geographic receipt.
