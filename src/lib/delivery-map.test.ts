import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import geometry from '../../wordpress/joyrent-rentals/data/delivery-zones.json';
import { classifyDeliveryPoint, deliveryTerms, type DeliveryZones } from './delivery-map';

const zones = geometry as DeliveryZones;
const settings = { greenFee: 200, yellowFee: 300, freeDeliveryFrom: 7 };

describe('exact delivery geometry', () => {
  it('preserves every coordinate in the six approved polygons', () => {
    expect(zones.features.map(feature => createHash('sha256').update(JSON.stringify(feature.geometry)).digest('hex'))).toEqual([
      'cf4b19858c643787fb4b6852211cfdf8d18cdf29d9cc560df7d9c47210a2b93e',
      '29132bb30278c9a81f0569d658201f83db4344da0d302eac4b8268b9aa2f29c3',
      'f74820285c0e92148f3240a5a08992f28a6db0c1c6dcc186ca176cf68b28d45a',
      '83a86311006528f543373702ad4a17eb92bdca51755497135c86897055d06950',
      'e0d659aaf7b8193dd2de883933143ff4435b6b6869b0d1fa2d2411012459c755',
      'd2b82fb80e6c0900ae2a987e6b1990742e9cedb88bac950836f80a698a16b09e',
    ]);
    expect(zones.features.map(feature => feature.properties.zone)).toEqual(['green', 'yellow', 'yellow', 'red', 'red', 'red']);
    expect(zones.features.every(feature => feature.geometry.type === 'Polygon')).toBe(true);
    expect(zones.features.every(feature => Object.keys(feature.properties).sort().join() === 'id,zone')).toBe(true);
  });

  it.each([
    [[30.75422074, 46.44246705], 'green'],
    [[30.72635924, 46.38095215], 'yellow'],
    [[30.68557994, 46.4881564], 'yellow'],
    [[30.6659289, 46.36417926], 'red'],
    [[30.82030181, 46.56505988], 'red'],
    [[30.6537142, 46.46954308], 'red'],
  ] as const)('identifies a point in each disconnected polygon: %j', (point, expected) => {
    expect(classifyDeliveryPoint([...point], zones)).toBe(expected);
  });

  it.each([
    [30.7728803, 46.497246],
    [30.7522532, 46.364826],
    [30.6566005, 46.5120682],
    [30.6594875, 46.3197394],
    [30.8315251, 46.6008314],
    [30.6158822, 46.4424654],
  ])('keeps a point inside a bounding box but outside its polygon unspecified: %j', (lng, lat) => {
    expect(classifyDeliveryPoint([lng, lat], zones)).toBe('outside');
  });

  it('requires confirmation on exact polygon boundaries', () => {
    const [first, second] = zones.features[0].geometry.coordinates[0];
    expect(classifyDeliveryPoint(first, zones)).toBe('boundary');
    expect(classifyDeliveryPoint([(first[0] + second[0]) / 2, (first[1] + second[1]) / 2], zones)).toBe('boundary');
  });

  it('excludes polygon holes while preserving their boundaries', () => {
    const withHole: DeliveryZones = { ...zones, features: [{ type: 'Feature', properties: { zone: 'green', id: 'test' }, geometry: { type: 'Polygon', coordinates: [
      [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
      [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]],
    ] } }] };
    expect(classifyDeliveryPoint([2, 2], withHole)).toBe('green');
    expect(classifyDeliveryPoint([5, 5], withHole)).toBe('outside');
    expect(classifyDeliveryPoint([4, 5], withHole)).toBe('boundary');
  });
});

describe('delivery zone terms', () => {
  it('uses configured fees and applies free delivery only to green and yellow', () => {
    expect(deliveryTerms('green', settings)).toEqual({ kind: 'fixed', fee: 200, includedFrom: 7 });
    expect(deliveryTerms('yellow', settings)).toEqual({ kind: 'fixed', fee: 300, includedFrom: 7 });
    expect(deliveryTerms('red', settings)).toEqual({ kind: 'taxi' });
    expect(deliveryTerms('green', { ...settings, greenFee: null })).toEqual({ kind: 'fixed', fee: null, includedFrom: 7 });
  });

  it('requires confirmation outside all polygons and at their boundaries', () => {
    expect(deliveryTerms('outside', settings)).toEqual({ kind: 'confirm' });
    expect(deliveryTerms('boundary', settings)).toEqual({ kind: 'confirm' });
  });
});
