import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { defaultCatalog } from './rental';
import { resolveGameImageSources } from './game-presentation';

const imageUrl = (name: string) => `/assets/images/${name}.webp`;
const sample = defaultCatalog.games[0];

describe('game artwork URLs', () => {
  it('uses one identical full-quality URL for each bundled detail and 720px card', () => {
    for (const game of defaultCatalog.games) {
      const sources = resolveGameImageSources(game,imageUrl);
      expect(sources.srcSet).toContain(`${sources.src} 720w`);
      const original = readFileSync(`wordpress/joyrent/assets/images/${game.image}.webp`);
      const full = readFileSync(`wordpress/joyrent/assets/images/${game.image}-720.webp`);
      expect(full.equals(original),game.image).toBe(true);
    }
  });

  it('preserves owner artwork URLs and nonbundled assets', () => {
    for (const image of ['https://example.test/cover.webp','/uploads/cover.webp','./cover.webp','../cover.webp']) {
      expect(resolveGameImageSources({...sample,imageUrl:image},imageUrl)).toEqual({src:image});
    }
    expect(resolveGameImageSources({...sample,image:'owner-cover'},imageUrl)).toEqual({src:'/assets/images/owner-cover.webp'});
    expect(resolveGameImageSources({...sample,image:'../../secret'},imageUrl)).toEqual({src:''});
  });
});
