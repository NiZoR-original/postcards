// Statistiques de visite anonymes (voir api/visite.php et la page Confidentialité).
// Une visite = un onglet : jeton aléatoire gardé dans sessionStorage (effacé à la fermeture de l'onglet),
// sans cookie ni lien avec l'adresse IP. On envoie le fuseau horaire (d'où le pays), une page vue à
// l'arrivée, puis le temps où la page est restée visible, à chaque fois qu'elle est masquée ou quittée.
// Désactivé si le visiteur s'y oppose (bouton de la page Confidentialité, Global Privacy Control, Do Not Track).
(function () {
  'use strict';
  const URL_API = document.currentScript.dataset.api;
  const CLE_REFUS = 'pc_stats_refus';
  let refusMemoire = null;
  let jeton = null;
  let depuis = null;

  // Signal « ne pas me suivre » du navigateur : prioritaire, le bouton ne peut pas le lever
  const signalNavigateur = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1';

  function refusLocal() {
    if (refusMemoire !== null) return refusMemoire;
    try { return localStorage.getItem(CLE_REFUS) === '1'; } catch (e) { return false; }
  }
  function refuse() {
    return signalNavigateur || refusLocal();
  }

  // Bouton d'opposition / de réactivation (page Confidentialité)
  const bouton = document.querySelector('[data-stats-choix]');
  function majBouton() {
    if (!bouton) return;
    const etat = document.querySelector('[data-stats-etat]');
    const off = refusLocal();
    bouton.hidden = signalNavigateur;
    bouton.textContent = off ? bouton.dataset.texteOui : bouton.dataset.texteNon;
    etat.textContent = signalNavigateur ? bouton.dataset.etatSignal
      : (off ? bouton.dataset.etatNon : bouton.dataset.etatOui);
  }
  if (bouton) {
    bouton.addEventListener('click', () => {
      if (signalNavigateur) return;
      const off = !refusLocal();
      try {
        if (off) localStorage.setItem(CLE_REFUS, '1');
        else localStorage.removeItem(CLE_REFUS);
        refusMemoire = null;
      } catch (e) { refusMemoire = off; }
      actualiser();
    });
  }

  let tz = '';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* inconnu */ }

  function envoyer(pages, secondes) {
    if (!jeton || refuse()) return;
    const donnees = new URLSearchParams({ v: jeton, tz, p: pages, d: secondes });
    navigator.sendBeacon(URL_API, donnees);
  }

  // Temps visible : compté seulement quand l'onglet est au premier plan
  function solder() {
    if (depuis === null) return;
    const s = Math.round((Date.now() - depuis) / 1000);
    depuis = null;
    if (s > 0) envoyer(0, s);
  }

  function actualiser() {
    majBouton();
    if (refuse()) {
      depuis = null;
      jeton = null;
      try { sessionStorage.removeItem('pc_visite'); } catch (e) { /* stockage bloqué */ }
      return;
    }
    if (jeton || !navigator.sendBeacon || !URL_API) return;
    try {
      const existant = sessionStorage.getItem('pc_visite');
      if (/^[a-f0-9]{32}$/.test(existant || '')) {
        jeton = existant;
      } else {
        const octets = crypto.getRandomValues(new Uint8Array(16));
        const nouveau = Array.from(octets, o => o.toString(16).padStart(2, '0')).join('');
        sessionStorage.setItem('pc_visite', nouveau);
        jeton = nouveau;
      }
    } catch (e) {
      return; // sans sessionStorage, impossible de regrouper les pages d'une visite
    }
    envoyer(1, 0);
    depuis = document.visibilityState === 'visible' ? Date.now() : null;
  }

  actualiser();
  window.addEventListener('storage', e => {
    if (e.key === CLE_REFUS || e.key === null) actualiser();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') solder();
    else if (jeton && !refuse()) depuis = Date.now();
  });
  window.addEventListener('pagehide', solder);
  window.addEventListener('pageshow', e => {
    if (!e.persisted) return;
    actualiser();
    if (jeton && document.visibilityState === 'visible') depuis = Date.now();
  });
})();
