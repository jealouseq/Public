'use strict';
/* Single source of truth for timing (seconds). Used by the renderer AND the audio builder. */
const TL = {
  total: 32.0,
  fps: 30,
  // act boundaries
  limbEnd: 5.6,        // opening orbital shot ends, globe rotates toward the region
  zoneOn: 6.2,         // search-zone ring appears on the globe
  diveStart: 9.4,      // fast descent towards the region
  revealStart: 11.667,  // terrain "blooms" outwards from the region centre
  revealEnd: 14.6,
  deepEnd: 20.0,       // pull-back starts
  arcStart: 20.6,      // great-circle arcs fly out to cities across Russia
  logoIn: 23.85,       // globe condenses into the emblem
  wordmark: 26.1,
  tagline: 27.0,
  cta: 28.2,
  // text windows [in, out]
  text: {
    a1: [0.9, 3.9], a2: [2.7, 5.4],
    b1: [6.0, 11.2],
    c1: [12.6, 19.6],
    d1: [20.5, 23.3],
  },
  // sonar pings in the deep section (also drive the audio)
  pings: [12.4, 13.9, 15.5, 17.1, 18.7],
  // sound cue times
  bpm: 72,
  cues: { flare: 2.3, zone: 6.3, riser: 9.4, impact: 11.667, pullback: 20.0, logo: 25.0, wordmark: 26.1, tagline: 27.0, cta: 28.2 },
};
if (typeof module !== 'undefined') module.exports = TL;
