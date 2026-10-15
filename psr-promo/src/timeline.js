'use strict';
/* Single source of truth for timing (seconds). Used by the renderer AND the audio builder. */
const TL = {
  total: 32.0,
  fps: 30,
  // act boundaries
  limbEnd: 5.6,        // opening orbital shot ends, globe rotates toward the region
  zoneOn: 6.2,         // search-zone ring appears on the globe
  diveStart: 9.4,      // fast descent towards the region
  revealStart: 10.6, reveal: 10.6,  // terrain "blooms" outwards from the region centre
  revealEnd: 14.6,
  deepEnd: 20.0,       // pull-back starts
  arcStart: 20.6,      // great-circle arcs fly out to cities across Russia
  logoIn: 23.85,       // globe condenses into the emblem
  wordmark: 25.9,
  tagline: 26.7,
  cta: 27.5,
  contact: 28.5,
  // text windows [in, out]
  text: {
    a1: [0.9, 3.9], a2: [2.7, 5.4],
    b1: [6.0, 10.2],
    c1: [11.5, 19.4],
    d1: [20.2, 23.9],
  },
  // sonar pings in the deep section (also drive the audio)
  lights: [13.6, 14.6, 15.6, 16.6, 17.5, 18.4],   // the search lights bloom in groups at these times (picture + sound)
  pings: [13.6, 14.6, 15.6, 16.6, 17.5],
  // sound cue times
  bpm: 72,
  cues: { flare: 2.4, zone: 6.3, riser: 8.0, impact: 10.6, pullback: 20.0, logo: 25.0, wordmark: 25.9, tagline: 26.7, cta: 27.5, contact: 28.5 },
};
if (typeof module !== 'undefined') module.exports = TL;
