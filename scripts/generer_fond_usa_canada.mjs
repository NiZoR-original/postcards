// Génère assets/fonds/usa-canada.js : fond jsVectorMap des États des USA, des provinces / territoires du Canada
// et des territoires américains (Porto Rico, îles Vierges, Guam, Mariannes du Nord, Samoa américaines).
//
// Données : Natural Earth v5.1.2 (domaine public)
//   États / provinces : https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_50m_admin_1_states_provinces.geojson
//   Territoires       : https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_10m_admin_0_countries.geojson
// Dépendance : d3-geo 3.1.1, installée dans un dossier temporaire (npm install d3-geo@3.1.1).
//
// Usage : node scripts/generer_fond_usa_canada.mjs <admin1_50m.geojson> <admin0_10m.geojson> <node_modules> > assets/fonds/usa-canada.js
//
// Projection conique équivalente (Albers) centrée sur l'Amérique du Nord, Alaska à sa vraie place.
// Hawaï et les territoires sont en encarts encadrés, placés dans l'océan.
// Codes des zones : ISO 3166-2 pour les États / provinces (US-CA, CA-QC…, comme cartes.subdivision),
// ISO 3166-1 pour les territoires (PR, VI, GU, MP, AS, comme cartes.pays_code).
// Les cadres des encarts sont des zones dont le code commence par « _ » (non cliquables, voir style.css).
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , fichierAdmin1, fichierAdmin0, dossierModules] = process.argv;
if (!fichierAdmin1 || !fichierAdmin0 || !dossierModules) {
  console.error('Usage : node scripts/generer_fond_usa_canada.mjs <admin1_50m.geojson> <admin0_10m.geojson> <node_modules>');
  process.exit(1);
}
const d3 = await import(pathToFileURL(path.join(path.resolve(dossierModules), 'd3-geo/src/index.js')).href);

// Largeur du continent ; le fond est élargi de chaque côté pour loger les encarts à l'écart des côtes.
const LARGEUR_CONTINENT = 900;
const MARGE_GAUCHE = 120; // Hawaï, Guam / Mariannes, Samoa
const MARGE_DROITE = 110; // Porto Rico / îles Vierges, à l'est de la Floride
const LARGEUR = MARGE_GAUCHE + LARGEUR_CONTINENT + MARGE_DROITE;
const lire = f => JSON.parse(fs.readFileSync(f, 'utf8'));

const regions = lire(fichierAdmin1).features.filter(f => ['US', 'CA'].includes(f.properties.iso_a2));
if (regions.length !== 64) {
  console.error(`Attendu 64 régions (50 États + DC + 13 provinces/territoires), trouvé ${regions.length}`);
  process.exit(1);
}
const TERRITOIRES = ['PR', 'VI', 'GU', 'MP', 'AS'];
const territoires = Object.fromEntries(lire(fichierAdmin0).features
  .filter(f => TERRITOIRES.includes(f.properties.ISO_A2))
  .map(f => [f.properties.ISO_A2, f]));
for (const t of TERRITOIRES) {
  if (!territoires[t]) { console.error(`Territoire ${t} introuvable`); process.exit(1); }
}

const collection = features => ({ type: 'FeatureCollection', features });

// Hawaï : seulement les îles principales. Les îles du Nord-Ouest (inhabitées, jusqu'à Kure, à 2 000 km)
// étireraient l'encart et réduiraient l'archipel habité à quelques pixels.
const hawai = regions.find(f => f.properties.iso_3166_2 === 'US-HI');
if (hawai.geometry.type === 'MultiPolygon') {
  hawai.geometry.coordinates = hawai.geometry.coordinates.filter(poly => poly[0].every(([lng]) => lng > -161));
}
const principales = collection(regions.filter(f => f.properties.iso_3166_2 !== 'US-HI'));

const proj = d3.geoConicEqualArea().parallels([40, 65]).rotate([100, 0]).fitWidth(LARGEUR_CONTINENT - 20, principales);
const [[, y0], [, y1]] = d3.geoPath(proj).bounds(principales);
const H = Math.ceil(y1 - y0 + 20);
proj.fitExtent([[MARGE_GAUCHE + 10, 10], [MARGE_GAUCHE + LARGEUR_CONTINENT - 10, H - 10]], principales);

// Encarts : [x0, y0, x1, y1] en pixels, choisis dans les zones d'océan vides du fond.
// centre = [longitude, latitude] réelles de l'encart ; projection conique locale centrée dessus.
const MARGE = 6;
const encarts = [
  { nom: '_HI', zones: [hawai], boite: [10, H * 0.71, 220, H * 0.91], centre: [-157, 20.5] },
  { nom: '_GU_MP', zones: [territoires.GU, territoires.MP], boite: [10, H * 0.40, 90, H * 0.66], centre: [145.5, 16.5] },
  { nom: '_AS', zones: [territoires.AS], boite: [100, H * 0.53, 180, H * 0.66], centre: [-170.3, -14.3] },
  { nom: '_PR_VI', zones: [territoires.PR, territoires.VI], boite: [LARGEUR - 170, H * 0.86, LARGEUR - 10, H * 0.98], centre: [-65.8, 18.2] },
];

const paths = {};
const centres = {};
function ajouter(code, nom, feature, projection) {
  const p = d3.geoPath(projection).digits(1);
  paths[code] = { path: p(feature), name: nom };
  centres[code] = p.centroid(feature).map(v => Math.round(v * 10) / 10);
}

for (const f of regions) {
  if (f.properties.iso_3166_2 === 'US-HI') continue;
  ajouter(f.properties.iso_3166_2, f.properties.name_en || f.properties.name, f, proj);
}
for (const e of encarts) {
  const [bx0, by0, bx1, by1] = e.boite;
  const [lng, lat] = e.centre;
  const p = d3.geoConicEqualArea().parallels([lat - 3, lat + 3]).rotate([-lng, 0])
    .fitExtent([[bx0 + MARGE, by0 + MARGE], [bx1 - MARGE, by1 - MARGE]], collection(e.zones));
  for (const f of e.zones) {
    const code = f.properties.iso_3166_2 || f.properties.ISO_A2;
    ajouter(code, f.properties.name_en || f.properties.NAME_EN || f.properties.name || f.properties.NAME, f, p);
  }
  const r = v => Math.round(v * 10) / 10;
  paths[e.nom] = { path: `M${r(bx0)},${r(by0)}H${r(bx1)}V${r(by1)}H${r(bx0)}Z`, name: '' };
}

process.stdout.write(
  '// Généré par scripts/generer_fond_usa_canada.mjs — données Natural Earth (domaine public). Ne pas modifier à la main.\n'
  + '"use strict";jsVectorMap.addMap("usa_canada",'
  + JSON.stringify({ paths, width: LARGEUR, height: H, centres })
  + ');\n'
);
