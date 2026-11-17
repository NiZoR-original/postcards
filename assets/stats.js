// Statistiques de visite anonymes (voir api/visite.php et la page Confidentialité).
// Une visite = un onglet : jeton aléatoire gardé dans sessionStorage (effacé à la fermeture de l'onglet),
// sans cookie ni lien avec l'adresse IP. On envoie le fuseau horaire (d'où le pays), une page vue à
// l'arrivée, puis le temps où la page est restée visible, à chaque fois qu'elle est masquée ou quittée.
// Désactivé si le visiteur s'y oppose (bouton de la page Confidentialité, Global Privacy Control, Do Not Track).
(function () {
  'use strict';
  const URL_API = document.currentScript.dataset.api;
  const CLE_REFUS = 'pc_stats_refus';

  // Signal « ne pas me suivre » du navigateur : prioritaire, le bouton ne peut pas le lever
  const signalNavigateur = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1';

  function refusLocal() {
    try { return localStorage.getItem(CLE_REFUS) === '1'; } catch (e) { return false; }
  }
  function refuse() {
    return signalNavigateur || refusLocal();
  }

  // Bouton d'opposition / de réactivation (page Confidentialité)
  const bouton = document.querySelector('[data-stats-choix]');
  if (bouton) {
    const etat = document.querySelector('[data-stats-etat]');
    const maj = () => {
      if (signalNavigateur) {
        // Rien à réactiver ici : c'est le réglage du navigateur qui bloque le comptage
        etat.textContent = bouton.dataset.etatSignal;
        bouton.hidden = true;
        return;
      }
      const off = refusLocal();
      bouton.textContent = off ? bouton.dataset.texteOui : bouton.dataset.texteNon;
      etat.textContent = off ? bouton.dataset.etatNon : bouton.dataset.etatOui;
    };
    bouton.addEventListener('click', () => {
      try {
        if (localStorage.getItem(CLE_REFUS) === '1') localStorage.removeItem(CLE_REFUS);
        else localStorage.setItem(CLE_REFUS, '1');
      } catch (e) { /* stockage bloqué : rien à mémoriser */ }
      maj();
    });
    maj();
  }

  if (refuse() || !navigator.sendBeacon || !URL_API) return;

  let jeton = null;
  try {
    jeton = sessionStorage.getItem('pc_visite');
    if (!/^[a-f0-9]{32}$/.test(jeton || '')) {
      const octets = crypto.getRandomValues(new Uint8Array(16));
      jeton = Array.from(octets, o => o.toString(16).padStart(2, '0')).join('');
      sessionStorage.setItem('pc_visite', jeton);
    }
  } catch (e) {
    return; // sans sessionStorage, impossible de regrouper les pages d'une visite
  }

  let tz = '';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* inconnu */ }

  function envoyer(pages, secondes) {
    const donnees = new URLSearchParams({ v: jeton, tz, p: pages, d: secondes });
    navigator.sendBeacon(URL_API, donnees);
  }

  // Temps visible : compté seulement quand l'onglet est au premier plan
  let depuis = document.visibilityState === 'visible' ? Date.now() : null;
  function solder() {
    if (depuis === null) return;
    const s = Math.round((Date.now() - depuis) / 1000);
    depuis = null;
    if (s > 0) envoyer(0, s);
  }

  envoyer(1, 0);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') solder();
    else depuis = Date.now();
  });
  window.addEventListener('pagehide', solder);
  window.addEventListener('pageshow', e => { if (e.persisted) depuis = Date.now(); }); // retour arrière (cache)
})();
