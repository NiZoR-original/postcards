// Menu compact et intégration du site public au lanceur du téléphone.
(function () {
  'use strict';
  const barre = document.querySelector('.public .topbar');
  const bouton = barre.querySelector('.menu-mobile');
  const compact = matchMedia('(max-width: 760px), (max-height: 520px) and (pointer: coarse)');
  function menu(ouvert) {
    barre.dataset.menu = ouvert ? 'ouvert' : 'ferme';
    bouton.setAttribute('aria-expanded', String(ouvert));
  }
  menu(false);
  bouton.addEventListener('click', () => menu(barre.dataset.menu !== 'ouvert'));
  compact.addEventListener('change', () => menu(false));
  document.addEventListener('click', e => {
    if (compact.matches && barre.dataset.menu === 'ouvert' && !barre.contains(e.target)) menu(false);
  });
  barre.addEventListener('keydown', e => {
    if (e.key === 'Escape' && compact.matches && barre.dataset.menu === 'ouvert') {
      menu(false);
      bouton.focus();
    }
  });

  if ('serviceWorker' in navigator && window.isSecureContext) {
    const worker = new URL('../sw.js', document.currentScript.src);
    navigator.serviceWorker.register(worker).catch(error => console.warn('Atlas postal', error));
  }
})();
