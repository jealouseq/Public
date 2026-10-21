import type { FeatureCollection, Polygon, Position } from 'geojson';

export type DeliveryZone = 'green' | 'yellow' | 'red';
export type DeliveryPointZone = DeliveryZone | 'outside' | 'boundary';
export type DeliveryZones = FeatureCollection<Polygon, { zone: DeliveryZone; id: string }> & { attribution: { source_url: string; checked: string } };
export type DeliveryFees = { freeDeliveryFrom: number; greenFee: number | null; yellowFee: number | null };
export type DeliveryTerms = { kind: 'fixed'; fee: number | null; includedFrom: number } | { kind: 'taxi' | 'confirm' };

// Coordinates are GeoJSON longitude/latitude. Membership uses the original rings,
// including holes; bounds are used only by the map camera, never for pricing.
function ringMembership(point: Position, ring: Position[]): 'inside' | 'outside' | 'boundary' {
  const [x, y] = point;
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [ax, ay] = ring[j], [bx, by] = ring[i];
    const dx = bx - ax, dy = by - ay;
    const length = Math.hypot(dx, dy);
    if (length === 0) {
      if (Math.hypot(x - ax, y - ay) <= 1e-9) return 'boundary';
      continue;
    }
    const distance = Math.abs((x - ax) * dy - (y - ay) * dx) / length;
    const projection = ((x - ax) * dx + (y - ay) * dy) / (length * length);
    if (distance <= 1e-9 && projection >= 0 && projection <= 1) return 'boundary';
    if ((ay > y) !== (by > y) && x < (bx - ax) * (y - ay) / (by - ay) + ax) inside = !inside;
  }
  return inside ? 'inside' : 'outside';
}

export function classifyDeliveryPoint(point: Position, data: DeliveryZones): DeliveryPointZone {
  let match: DeliveryPointZone = 'outside';
  for (const feature of data.features) {
    const memberships = feature.geometry.coordinates.map(ring => ringMembership(point, ring));
    if (memberships.includes('boundary')) return 'boundary';
    if (memberships[0] === 'inside' && !memberships.slice(1).includes('inside')) match = feature.properties.zone;
  }
  return match;
}

export function deliveryTerms(zone: DeliveryPointZone, fees: DeliveryFees): DeliveryTerms {
  if (zone === 'green' || zone === 'yellow') return { kind: 'fixed', fee: zone === 'green' ? fees.greenFee : fees.yellowFee, includedFrom: fees.freeDeliveryFrom };
  return { kind: zone === 'red' ? 'taxi' : 'confirm' };
}
