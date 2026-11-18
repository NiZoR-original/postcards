// Fond jsVectorMap produit à partir des Contours administratifs 2026 (data.gouv.fr, ODbL).
// Télécharger departements-1000m.geojson et departements-100m.geojson :
// https://etalab-datasets.geo.data.gouv.fr/contours-administratifs/2026/geojson/
// node scripts/generer_fond_france.mjs <departements-1000m.geojson> <node_modules> <departements-100m.geojson> > assets/fonds/france.js
// node scripts/generer_fond_france.mjs <departements-1000m.geojson> --php > includes/departements_fr.php
// Installer d3-geo@3.1.1 dans un dossier temporaire, comme pour le fond USA & Canada.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , fichier, modules, fichierDetail] = process.argv;
if (!fichier || !modules || (modules !== '--php' && !fichierDetail)) {
  console.error('Usage : node scripts/generer_fond_france.mjs <departements-1000m.geojson> <node_modules|--php> [departements-100m.geojson]');
  process.exit(1);
}
// Ordre des encarts : les cinq DROM, puis les COM et la Nouvelle-Calédonie.
const encarts = [
  ['971', 'GP'], ['972', 'MQ'], ['973', 'GF'], ['974', 'RE'], ['976', 'YT'],
  ['975', 'PM'], ['977', 'BL'], ['978', 'MF'], ['986', 'WF'], ['987', 'PF'], ['988', 'NC'],
];
const features = JSON.parse(readFileSync(fichier, 'utf8')).features;
const contours = new Map(features.map(f => [f.properties.code, f]));
const departements = features.filter(f => /^(?:\d{2}|2[AB])$/.test(f.properties.code));
if (departements.length !== 96) throw new Error('96 départements métropolitains attendus');
for (const code of [...departements.map(f => f.properties.code), ...encarts.map(([code]) => code)]) {
  if (!contours.get(code)?.geometry) throw new Error('Contour manquant : ' + code);
}
if (modules === '--php') {
  const lignes = departements.map(({ properties: { code, nom } }) =>
    "    'FR-" + code + "' => '" + nom.replaceAll("'", "\\'") + "',"
  );
  console.log('<?php\n// Généré par scripts/generer_fond_france.mjs depuis les Contours administratifs 2026 (ODbL).\n\nconst DEPARTEMENTS_FR = [\n'
    + lignes.join('\n') + '\n];');
  process.exit(0);
}
const d3 = await import(pathToFileURL(resolve(modules, 'd3-geo/src/index.js')).href);
// À l’échelle de leur propre encart, les petits archipels nécessitent leurs contours à 100 m.
const details = new Map(JSON.parse(readFileSync(fichierDetail, 'utf8')).features
  .filter(f => ['975', '977', '978', '986'].includes(f.properties.code))
  .map(f => [f.properties.code, f]));

const width = 1080;
const height = 704;
const paths = {};
const etiquettes = {};
const metropole = { type: 'FeatureCollection', features: departements };
const projection = d3.geoConicConformal().parallels([44, 49]).rotate([-3, 0])
  .center([0, 46.5]).fitExtent([[14, 16], [650, height - 16]], metropole);
const tracerMetropole = d3.geoPath(projection).digits(1);
for (const feature of departements) {
  const { code, nom } = feature.properties;
  paths['FR-' + code] = { path: tracerMetropole(feature), name: nom };
}

for (const [index, [insee, code]] of encarts.entries()) {
  const feature = details.get(insee) ?? contours.get(insee);
  const x = 654 + (index % 2) * 211;
  const y = 15 + Math.floor(index / 2) * 114;
  const xFin = x + 201;
  const yFin = y + 104;
  const [lng, lat] = d3.geoCentroid(feature);
  // Chaque encart est à une échelle propre pour rendre les petits territoires lisibles.
  const projectionLocale = d3.geoAzimuthalEqualArea().rotate([-lng, -lat])
    .fitExtent([[x + 11, y + 28], [xFin - 11, yFin - 8]], feature);
  // Le cadre est dessiné avant le territoire et sert aussi de cible cliquable.
  paths['_' + code] = { path: `M${x},${y}H${xFin}V${yFin}H${x}Z`, name: '' };
  paths[code] = { path: d3.geoPath(projectionLocale).digits(1)(feature), name: feature.properties.nom };
  etiquettes[code] = [x + 9, y + 21];
}

process.stdout.write(
  '// Généré par scripts/generer_fond_france.mjs – Contours administratifs 2026, data.gouv.fr (ODbL).\n'
  + '"use strict";jsVectorMap.addMap("france",'
  + JSON.stringify({ paths, width, height, etiquettes })
  + ');\n'
);
