// Carte du monde publique (jsVectorMap 1.6). Données injectées par index.php dans window.PC :
//   stats  : { FR: {recue, envoyee, nom}, … }   — cartes publiques uniquement
//   coords : { SG: [lat, lng], … }              — petits pays absents du fond « world »
(function () {
  'use strict';
  const PC = window.PC;
  const BUCKETS = [
    { key: 'b1', min: 1, max: 1, color: '#f6d6a8' },
    { key: 'b2', min: 2, max: 5, color: '#efa95a' },
    { key: 'b3', min: 6, max: 20, color: '#d9762b' },
    { key: 'b4', min: 21, max: Infinity, color: '#9c4a14' },
  ];
  let mode = 'recue';
  let map = null;
  let paysActif = null;
  const noms = new Intl.DisplayNames([PC.lang], { type: 'region' });
  const GRIS = '#b9b2a7';

  function nomPays(code) {
    try { return noms.of(code) || code; } catch (e) { return code; }
  }

  // Fond « world » capturé par index.php (jsVectorMap ne l'expose pas)
  const fond = (window.PC_FONDS && window.PC_FONDS.world) || { paths: {}, projection: {} };
  const worldPaths = fond.paths;
  const meridien = (fond.projection && fond.projection.centralMeridian) || 0;

  function nb(code) {
    const s = PC.stats[code];
    if (!s) return 0;
    return mode === 'tous' ? s.recue + s.envoyee : s[mode];
  }

  function bucket(n) {
    return BUCKETS.find(b => n >= b.min && n <= b.max);
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function construire() {
    if (map) map.destroy();
    document.getElementById('map').innerHTML = '';

    const values = {};
    const markers = [];
    for (const code of Object.keys(PC.stats)) {
      const n = nb(code);
      if (n && worldPaths[code]) values[code] = bucket(n).key;
    }
    // Petits pays absents du fond : un point, coloré comme un pays s'il a des cartes, gris sinon
    for (const code of Object.keys(PC.coords)) {
      if (worldPaths[code]) continue;
      const n = nb(code);
      // Le fond est centré sur le méridien 11,5° E : une longitude à moins de -168,5°
      // (Samoa, Tonga, Wallis…) sortirait du cadre et le point serait ignoré.
      let [lat, lng] = PC.coords[code];
      if (lng - meridien < -180) lng += 360;
      markers.push({
        name: nomPays(code), coords: [lat, lng], code: code,
        style: { initial: { fill: n ? bucket(n).color : GRIS, r: n ? 5 : 3.5 } },
      });
    }
    // Les points avec des cartes dessinés en dernier, donc au-dessus des points gris
    markers.sort((a, b) => (nb(a.code) > 0) - (nb(b.code) > 0));
    const scale = Object.fromEntries(BUCKETS.map(b => [b.key, b.color]));

    map = new jsVectorMap({
      selector: '#map',
      map: 'world',
      zoomOnScroll: false,
      zoomButtons: true,
      regionsSelectable: true,
      regionsSelectableOne: true,
      selectedRegions: paysActif && worldPaths[paysActif] ? [paysActif] : [],
      regionStyle: {
        initial: { fill: '#e4e1da', stroke: '#fff', strokeWidth: 0.4, fillOpacity: 1 },
        hover: { fillOpacity: 0.75, cursor: 'pointer' },
        selected: { fill: '#2f6f8f' },
      },
      markerStyle: {
        initial: { r: 5, stroke: '#fff', strokeWidth: 1.5, fillOpacity: 1 },
        hover: { cursor: 'pointer', stroke: '#2f6f8f' },
      },
      markers: markers,
      series: { regions: [{ attribute: 'fill', scale: scale, values: values }] },
      onRegionTooltipShow(event, tooltip, code) {
        const s = PC.stats[code];
        let nom = code;
        try { nom = noms.of(code) || code; } catch (e) { /* code non reconnu par Intl */ }
        tooltip.text(s ? nom + ' — ' + s.recue + ' ↓ · ' + s.envoyee + ' ↑' : nom);
      },
      onMarkerTooltipShow(event, tooltip, index) {
        const m = markers[index];
        const s = PC.stats[m.code];
        tooltip.text(s ? m.name + ' — ' + s.recue + ' ↓ · ' + s.envoyee + ' ↑' : m.name);
      },
      onRegionClick(event, code) { ouvrirPays(code); },
      onMarkerClick(event, index) { ouvrirPays(markers[index].code); },
    });
  }

  function legende() {
    document.getElementById('legende').innerHTML = BUCKETS.map(b =>
      '<span><i style="background:' + b.color + '"></i>' + b.min + (b.max === Infinity ? '+' : (b.max > b.min ? '–' + b.max : '')) + '</span>'
    ).join('');
  }

  async function ouvrirPays(code) {
    paysActif = code;
    const panneau = document.getElementById('panneau');
    afficherPanneau(true);
    panneau.classList.add('charge');
    try {
      const sens = mode === 'tous' ? '' : mode;
      const r = await fetch('api/pays.php?code=' + encodeURIComponent(code) + '&sens=' + sens + '&lang=' + PC.lang);
      const d = await r.json();
      panneau.innerHTML = rendu(d);
    } catch (e) {
      panneau.innerHTML = '<p class="erreur">Erreur de chargement.</p>';
    }
    panneau.classList.remove('charge');
    if (window.matchMedia('(max-width: 900px)').matches) panneau.scrollIntoView({ behavior: 'smooth' });
  }

  // Ouvre / ferme la colonne du pays ; la carte change de largeur, il faut la redimensionner
  function afficherPanneau(ouvert) {
    const layout = document.getElementById('carte-layout');
    if (layout.classList.contains('avec-panneau') === ouvert) return;
    layout.classList.toggle('avec-panneau', ouvert);
    document.getElementById('panneau').hidden = !ouvert;
    if (map) map.updateSize();
  }

  document.getElementById('panneau').addEventListener('click', e => {
    if (!e.target.closest('[data-fermer]')) return;
    paysActif = null;
    afficherPanneau(false);
    if (map) map.clearSelectedRegions();
  });

  function rendu(d) {
    let html = '<div class="panneau-titre"><h2>' + esc(d.drapeau) + ' ' + esc(d.nom) + '</h2>' +
      '<button type="button" data-fermer aria-label="' + esc(PC.t.fermer) + '">&times;</button></div>';
    if (!d.cartes.length) return html + '<p class="aide">' + esc(PC.t.aucune) + '</p>';
    html += '<div class="vignettes">';
    for (const c of d.cartes) {
      const img = c.photo
        ? '<img loading="lazy" src="photo.php?id=' + c.photo + '" alt="' + esc(c.titre) + '">'
        : '<div class="sans-photo"><i class="fa-regular fa-image"></i><span>' + esc(PC.t.sansPhoto) + '</span></div>';
      const icone = c.sens === 'recue' ? 'fa-inbox' : 'fa-paper-plane';
      html += '<a class="vignette" href="carte.php?id=' + c.id + '">' + img +
        '<span class="meta"><i class="fa-solid ' + icone + '"></i> ' + esc(c.reference || c.titre || '') +
        (c.en_route ? ' <em>' + esc(PC.t.enRoute) + '</em>' : '') + '</span></a>';
    }
    return html + '</div>';
  }

  document.querySelectorAll('.mode-switch button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mode-switch button').forEach(b => b.classList.toggle('actif', b === btn));
      mode = btn.dataset.mode;
      construire();
      if (paysActif) ouvrirPays(paysActif);
    });
  });
  document.querySelectorAll('.liste-pays [data-pays]').forEach(btn => {
    btn.addEventListener('click', () => {
      ouvrirPays(btn.dataset.pays);
      document.getElementById('panneau').scrollIntoView({ behavior: 'smooth' });
    });
  });

  legende();
  construire();
})();
