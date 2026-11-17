// Bandeau d'information cookies, affiché seulement là où la loi impose d'informer les visiteurs :
// Union européenne / EEE, Royaume-Uni, Suisse et territoires d'outre-mer européens (RGPD et directive ePrivacy),
// Brésil (LGPD). Le pays est déduit du fuseau horaire du navigateur (aucun service externe) ; fuseau inconnu = affiché.
// Le site ne pose que des cookies nécessaires : simple information, pas de consentement à recueillir.
// « J'ai compris » pose le cookie pc_cookies pour 30 jours ; le serveur n'affiche plus le bandeau tant qu'il existe.
(function () {
  'use strict';
  const bandeau = document.getElementById('bandeau-cookies');
  if (!bandeau) return;

  const ZONES = /^(Europe\/|Arctic\/Longyearbyen$|Atlantic\/(Canary|Madeira|Azores|Reykjavik|Faroe)$|Asia\/(Nicosia|Famagusta)$|Indian\/(Reunion|Mayotte)$|America\/(Guadeloupe|Martinique|Cayenne|St_Barthelemy|Marigot|Miquelon|Sao_Paulo|Bahia|Fortaleza|Recife|Belem|Maceio|Araguaina|Santarem|Manaus|Cuiaba|Campo_Grande|Porto_Velho|Boa_Vista|Rio_Branco|Eirunepe|Noronha)$|Pacific\/(Noumea|Tahiti|Marquesas|Gambier|Wallis)$)/;

  let zone = '';
  try { zone = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { /* fuseau inconnu */ }
  if (zone && !ZONES.test(zone)) return;

  bandeau.hidden = false;
  bandeau.querySelector('[data-cookies-ok]').addEventListener('click', () => {
    document.cookie = 'pc_cookies=1; path=/; max-age=' + 30 * 86400 + '; SameSite=Lax';
    bandeau.hidden = true;
  });
})();
