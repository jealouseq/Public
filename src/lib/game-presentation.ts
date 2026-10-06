import { defaultCatalog, type Game } from './rental';

const bundledImages = new Set(defaultCatalog.games.map(game => game.image));

/** Reuse the card's existing 720px artwork when its detail dialog opens. */
export function resolveGameImageSources(game: Game, imageUrl: (name: string) => string) {
  const custom = game.imageUrl;
  if (custom && /^(https?:\/\/|\/(?!\/)|\.\.?\/)/i.test(custom)) return { src: custom };
  if (!game.image || !/^[a-z0-9-]+$/i.test(game.image)) return { src: '' };
  if (!bundledImages.has(game.image)) return { src: imageUrl(game.image) };
  const full = imageUrl(`${game.image}-720`);
  return { src: full, srcSet: `${imageUrl(`${game.image}-360`)} 360w, ${full} 720w` };
}
