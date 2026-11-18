// Cartes publiques (jsVectorMap 1.6) : monde (index.php) et USA & Canada (usa-canada.php).
// Données injectées par includes/page_carte.php dans window.PC :
//   fond   : nom du fond jsVectorMap ('world', 'usa_canada'), capturé par assets/fonds/capture.js
//   stats  : { FR: {recue, envoyee, nom, drapeau}, … }   — cartes publiques uniquement
//   coords : { SG: [lat, lng], … } (fond projeté) ou { 'US-DC': [x, y] } (fond en pixels) — zones en points
//   noms   : { code: nom } des zones du fond, pour les tooltips (sinon noms des pays par Intl)
(function () {
  'use strict';
  const PC = window.PC;
  const COULEURS = ['#f4d09b', '#dda45e', '#c47c3b', '#995021'];
  // Classes de la légende, recalculées à chaque construction selon les données affichées (calculerClasses)
  let BUCKETS = [];

  // 4 classes d'entiers : 1, 2, 3, 4 tant que le maximum ne dépasse pas 4 ; au-delà, 4 plages jointives
  // de 1 au maximum, bornes arrondies (max 10 → 1–3, 4–5, 6–8, 9–10). Plages jamais vides : pas > 1.
  function calculerClasses(max) {
    if (max <= COULEURS.length) {
      return COULEURS.map((color, i) => ({ key: 'b' + i, min: i + 1, max: i + 1, color }));
    }
    let debut = 1;
    return COULEURS.map((color, i) => {
      const fin = Math.round(max * (i + 1) / COULEURS.length);
      const classe = { key: 'b' + i, min: debut, max: fin, color };
      debut = fin + 1;
      return classe;
    });
  }
  let mode = 'recue';
  let map = null;
  let paysActif = null;
  let requetePays = null;
  const noms = new Intl.DisplayNames([PC.lang], { type: 'region' });
  const selection = document.getElementById('atlas-selection');
  const panneau = document.getElementById('panneau');
  const attente = document.getElementById('atlas-attente');

  function nomPays(code) {
    if (PC.stats[code]) return PC.stats[code].nom;
    if (PC.noms[code]) return PC.noms[code];
    try { return noms.of(code) || code; } catch (e) { return code; }
  }

  // Fond capturé par assets/fonds/capture.js (jsVectorMap ne l'expose pas)
  const fond = (window.PC_FONDS && window.PC_FONDS[PC.fond]) || { paths: {} };
  const worldPaths = fond.paths;
  const etiquettes = fond.etiquettes || {};
  // Fond projeté (monde) : points en [lat, lng] ; fond sans projection (USA & Canada) : en pixels
  const meridien = fond.projection ? fond.projection.centralMeridian || 0 : null;

  function nb(code) {
    const s = PC.stats[code];
    if (!s) return 0;
    return mode === 'tous' ? s.recue + s.envoyee : s[mode];
  }

  function bucket(n) {
    return BUCKETS.find(b => n >= b.min && n <= b.max);
  }

  function zoneEncart(code) {
    if (code.startsWith('_') && Object.hasOwn(etiquettes, code.slice(1))) return code.slice(1);
    return code;
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function construire() {
    if (map) map.destroy();
    const conteneur = document.getElementById('map');
    conteneur.innerHTML = '';
    conteneur.dataset.fond = PC.fond; // épaisseurs des frontières propres à chaque fond (style.css)

    const max = Math.max(0, ...Object.keys(PC.stats).map(nb));
    BUCKETS = calculerClasses(max);
    legende();
    if (typeof jsVectorMap === 'undefined' || !Object.keys(worldPaths).length) return;

    const values = {};
    const markers = [];
    for (const code of Object.keys(PC.stats)) {
      const n = nb(code);
      if (n && worldPaths[code]) values[code] = bucket(n).key;
    }
    // Zones absentes du fond ou trop petites pour être cliquées (PC.coords) : un point,
    // coloré comme une zone si elle a des cartes, gris sinon
    for (const code of Object.keys(PC.coords)) {
      const n = nb(code);
      // Le fond est centré sur le méridien 11,5° E : une longitude à moins de -168,5°
      // (Samoa, Tonga, Wallis…) sortirait du cadre et le point serait ignoré.
      let [a, b] = PC.coords[code];
      if (meridien !== null && b - meridien < -180) b += 360;
      markers.push({
        name: nomPays(code), coords: [a, b], code: code,
        style: { initial: { fill: n ? bucket(n).color : '#827973', r: n ? 5 : 3.5 } },
      });
    }
    // Les points avec des cartes dessinés en dernier, donc au-dessus des points gris
    markers.sort((a, b) => (nb(a.code) > 0) - (nb(b.code) > 0));
    const scale = Object.fromEntries(BUCKETS.map(b => [b.key, b.color]));

    map = new jsVectorMap({
      selector: '#map',
      map: PC.fond,
      backgroundColor: 'var(--eau)',
      zoomOnScroll: false,
      zoomButtons: true,
      regionsSelectable: true,
      regionsSelectableOne: true,
      selectedRegions: paysActif && worldPaths[paysActif] ? [paysActif] : [],
      regionStyle: {
        // frontières dessinées par tracerFrontieres() au-dessus des zones
        initial: { fill: '#9c9288', stroke: 'none', fillOpacity: 1 },
        hover: { fillOpacity: 0.75, cursor: 'pointer' },
        selected: { fill: '#527a96' },
      },
      markerStyle: {
        initial: { r: 5, stroke: '#fff', strokeWidth: 1.5, fillOpacity: 1 },
        hover: { cursor: 'pointer', stroke: '#527a96' },
      },
      markers: markers,
      series: { regions: [{ attribute: 'fill', scale: scale, values: values }] },
      onRegionTooltipShow(event, tooltip, code) {
        code = zoneEncart(code);
        const s = PC.stats[code];
        const nom = nomPays(code);
        tooltip.text(s ? nom + ' — ' + s.recue + ' ↓ · ' + s.envoyee + ' ↑' : nom);
      },
      onMarkerTooltipShow(event, tooltip, index) {
        const m = markers[index];
        const s = PC.stats[m.code];
        tooltip.text(s ? m.name + ' — ' + s.recue + ' ↓ · ' + s.envoyee + ' ↑' : m.name);
      },
      onRegionClick(event, code) { ouvrirPays(zoneEncart(code)); },
      onMarkerClick(event, index) { ouvrirPays(markers[index].code); },
      // Au premier affichage, jsVectorMap attend DOMContentLoaded avant de dessiner : les zones
      // n'existent qu'à partir de cet événement
      onLoaded() { tracerFrontieres(); },
    });
  }

  // Frontières : trait foncé de 1 px bordé de blanc, identique sur tous les fonds et dans les deux thèmes.
  // jsVectorMap ne dessine qu'un contour par zone : on superpose une copie des contours (sans remplissage,
  // non cliquable) dans le groupe des zones, pour qu'elle suive le zoom et le déplacement.
  function tracerFrontieres() {
    const groupe = document.querySelector('#map #jvm-regions-group');
    if (!groupe) return;
    const SVG = 'http://www.w3.org/2000/svg';
    const couche = document.createElementNS(SVG, 'g');
    couche.setAttribute('class', 'pc-frontieres');
    const liseres = document.createElementNS(SVG, 'g');
    liseres.setAttribute('class', 'pc-liseres');
    const traits = document.createElementNS(SVG, 'g');
    traits.setAttribute('class', 'pc-traits');
    groupe.querySelectorAll('path.jvm-region').forEach(p => {
      if ((p.getAttribute('data-code') || '').startsWith('_')) return; // cadres d'encart
      const d = p.getAttribute('d');
      for (const g of [liseres, traits]) {
        const t = document.createElementNS(SVG, 'path');
        t.setAttribute('d', d);
        g.appendChild(t);
      }
    });
    // tous les liserés d'abord, puis tous les traits : aucun remplissage ne vient les recouvrir
    couche.append(liseres, traits);
    groupe.appendChild(couche);

    for (const [code, [x, y]] of Object.entries(etiquettes)) {
      const texte = document.createElementNS(SVG, 'text');
      texte.setAttribute('class', 'pc-nom-encart');
      texte.setAttribute('aria-hidden', 'true');
      texte.setAttribute('x', x);
      texte.setAttribute('y', y);
      texte.textContent = nomPays(code);
      groupe.appendChild(texte);
    }
  }

  function legende() {
    document.getElementById('legende').innerHTML = BUCKETS.map(b =>
      '<span><i style="background:' + b.color + '"></i>' + b.min + (b.max > b.min ? '–' + b.max : '') + '</span>'
    ).join('');
  }

  async function ouvrirPays(code) {
    requetePays?.abort();
    const requete = new AbortController();
    requetePays = requete;
    paysActif = code;
    selection.value = code;
    if (map) map.setSelectedRegions(worldPaths[code] ? [code] : []);
    panneau.hidden = false;
    attente.hidden = true;
    const titre = entetePanneau(nomPays(code));
    panneau.innerHTML = titre + '<p role="status">' + esc(PC.t.chargement) + '</p>';
    panneau.classList.add('charge');
    try {
      const sens = mode === 'tous' ? '' : mode;
      const r = await fetch('api/pays.php?' + (code.includes('-') ? 'subdivision' : 'code') + '=' + encodeURIComponent(code) + '&sens=' + sens + '&lang=' + PC.lang, {signal: requete.signal});
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const d = await r.json();
      if (requete.signal.aborted) return;
      panneau.innerHTML = rendu(d);
    } catch (e) {
      if (requete.signal.aborted) return;
      panneau.innerHTML = titre + '<p class="erreur" role="status">' + esc(PC.t.erreurChargement) + '</p>';
    }
    requetePays = null;
    panneau.classList.remove('charge');
    // Le panneau est sous la carte sur mobile. La marge tient compte du menu fixe.
    const barre = document.querySelector('.topbar');
    panneau.style.scrollMarginTop = ((barre ? barre.offsetHeight : 0) + 16) + 'px';
    panneau.scrollIntoView({ block: 'nearest' });
  }

  function fermerPanneau() {
    requetePays?.abort();
    requetePays = null;
    paysActif = null;
    selection.value = '';
    panneau.classList.remove('charge');
    panneau.hidden = true;
    attente.hidden = false;
    if (map) map.clearSelectedRegions();
  }

  panneau.addEventListener('click', e => {
    if (!e.target.closest('[data-fermer]')) return;
    fermerPanneau();
    selection.focus();
  });
  selection.addEventListener('change', () => {
    if (selection.value) ouvrirPays(selection.value);
    else fermerPanneau();
  });

  function entetePanneau(nom) {
    return '<div class="panneau-titre"><h2>' + esc(nom) + '</h2>' +
      '<button type="button" data-fermer aria-label="' + esc(PC.t.fermer) + '">&times;</button></div>';
  }

  function rendu(d) {
    let html = entetePanneau(d.drapeau + ' ' + d.nom);
    if (d.lien && !window.location.pathname.endsWith('/' + d.lien.url)) {
      html += '<p><a class="bouton secondaire" href="' + esc(d.lien.url) + '"><i class="fa-solid fa-map"></i> ' + esc(d.lien.texte) + '</a></p>';
    }
    if (!d.cartes.length) return html + '<p class="aide">' + esc(PC.t.aucune) + '</p>';
    html += '<div class="vignettes">';
    for (const c of d.cartes) {
      const img = c.photo
        ? '<img loading="lazy" src="photo.php?v=2&amp;id=' + c.photo + '" alt="' + esc(c.titre) + '">'
        : '<div class="sans-photo"><i class="fa-regular fa-image"></i><span>' + esc(PC.t.sansPhoto) + '</span></div>';
      const icone = c.sens === 'recue' ? 'fa-inbox' : 'fa-paper-plane';
      html += '<a class="vignette" href="carte.php?id=' + c.id + '">' + img +
        '<span class="meta">' + (c.titre ? '<strong>' + esc(c.titre) + '</strong>' : '') +
        '<span><i class="fa-solid ' + icone + '" aria-hidden="true"></i> ' + esc(c.reference || '') +
        (c.en_route ? ' <em>' + esc(PC.t.enRoute) + '</em>' : '') + '</span></span></a>';
    }
    return html + '</div>';
  }

  document.querySelectorAll('.mode-switch button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mode-switch button').forEach(b => {
        b.classList.toggle('actif', b === btn);
        b.setAttribute('aria-pressed', String(b === btn));
      });
      mode = btn.dataset.mode;
      construire();
      if (paysActif) ouvrirPays(paysActif);
    });
  });
  document.querySelectorAll('.liste-pays [data-pays]').forEach(btn => {
    btn.addEventListener('click', () => {
      ouvrirPays(btn.dataset.pays);
    });
  });

  construire();
})();
