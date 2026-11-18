(function () {
  'use strict';
  const barre = document.querySelector('.admin .topbar');
  const bouton = barre.querySelector('.plus-admin');
  const options = document.getElementById('options-admin');
  const compact = matchMedia('(max-width: 900px), (max-height: 520px) and (pointer: coarse)');
  function menu(ouvert) {
    barre.dataset.menu = ouvert ? 'ouvert' : 'ferme';
    bouton.setAttribute('aria-expanded', String(ouvert));
    if (ouvert) options.querySelector('a').focus();
  }
  menu(false);
  bouton.addEventListener('click', () => menu(barre.dataset.menu !== 'ouvert'));
  compact.addEventListener('change', () => menu(false));
  barre.addEventListener('keydown', e => {
    if (e.key === 'Escape' && compact.matches && barre.dataset.menu === 'ouvert') {
      menu(false);
      bouton.focus();
    }
  });
  document.addEventListener('click', e => {
    if (compact.matches && barre.dataset.menu === 'ouvert' && !barre.contains(e.target)) menu(false);
  });
})();
