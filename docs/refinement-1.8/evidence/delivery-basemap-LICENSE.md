# Delivery basemap source and attribution

The static map image `../images/delivery-basemap-odessa.webp` was rendered for JOYRENT from real OpenStreetMap roads, coastline and water geometry retrieved on 2026-10-03. It is a produced work based on OpenStreetMap data, licensed under the Open Database License (ODbL) 1.0. No raster tile service, CARTO tile, generated geography or Google basemap was used.

Visible attribution: **©OpenStreetMap contributors**, linked to https://www.openstreetmap.org/copyright .

Source-data license: https://opendatacommons.org/licenses/odbl/1-0/ . The reusable source database used for this image is included as [`delivery-basemap-osm.geojson.gz`](delivery-basemap-osm.geojson.gz), a gzip-compressed GeoJSON FeatureCollection with original OSM coordinates, public OSM IDs and selected cartographic tags. This extract is offered under ODbL 1.0. It includes complete feature geometries, which can extend beyond the map viewport. The projection, source timestamps and SHA-256 hashes are recorded in [`delivery-basemap-manifest.json`](delivery-basemap-manifest.json).

Public data endpoint: https://overpass-api.de/api/interpreter . The bounded OSM query was:

```overpass
[out:json][timeout:25];
(
  way["highway"~"^(motorway|trunk|primary|secondary|tertiary)$"](46.3039694520,30.3937261173,46.6165161266,31.0536811827);
  way["natural"="coastline"](46.29,30.38,46.63,31.07);
  way["natural"="water"](46.3039694520,30.3937261173,46.6165161266,31.0536811827);
);
out geom;
```

The two major northern estuaries were retrieved as full OSM multipolygons:

```overpass
[out:json][timeout:15];
relation(id:2248813,2248818);
out geom;
```

Road and visible shoreline coordinates were preserved. Coastal segments were joined at exact shared OSM endpoints; the sea-fill closure edges lie outside the visible viewport. Rendering changes are projection, clipping and a charcoal color palette. Minor street classes and building footprints are omitted for clarity. The image contains no delivery-zone geometry: the approved delivery polygons are a separate local overlay with their own reference attribution.

Logical map size: 640 × 440. Delivered raster: 1280 × 880. Projection: Web Mercator, uniform scale. WGS84 bounds: west 30.39372611729199, east 31.053681182707997, south 46.303969452017704, north 46.61651612659042. The map must be displayed without stretching or cropping relative to its matching delivery overlay.
