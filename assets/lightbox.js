// Agrandissement des photos : tout lien [data-lightbox] ouvre l'image en plein écran.
// Les liens d'un même conteneur (.galerie, .swap-photos) sont navigables avec ← →.
(function () {
  'use strict';
  const box = document.getElementById('lightbox');
  if (!box) return;
  const img = box.querySelector('img');
  let groupe = [];
  let idx = 0;

  function montrer(i) {
    idx = (i + groupe.length) % groupe.length;
    img.src = groupe[idx].href;
    img.alt = (groupe[idx].querySelector('img') || {}).alt || '';
  }
  function fermer() { box.hidden = true; img.src = ''; }

  document.addEventListener('click', e => {
    const a = e.target.closest('a[data-lightbox]');
    if (!a) return;
    e.preventDefault();
    groupe = Array.from(a.parentElement.querySelectorAll('a[data-lightbox]'));
    montrer(groupe.indexOf(a));
    box.hidden = false;
  });
  box.addEventListener('click', e => { if (e.target !== img) fermer(); });
  img.addEventListener('click', () => { if (groupe.length > 1) montrer(idx + 1); });
  document.addEventListener('keydown', e => {
    if (box.hidden) return;
    if (e.key === 'Escape') fermer();
    if (e.key === 'ArrowRight') montrer(idx + 1);
    if (e.key === 'ArrowLeft') montrer(idx - 1);
  });
})();
