// Agrandissement des photos : tout lien [data-lightbox] ouvre l'image en plein écran.
// Les liens d'un même conteneur (.galerie, .swap-photos) sont navigables avec ← →.
(function () {
  'use strict';
  const box = document.getElementById('lightbox');
  if (!box || typeof box.showModal !== 'function') return;
  const img = box.querySelector('img');
  const commandes = box.querySelector('.lightbox-commandes');
  const compteur = commandes.querySelector('span');
  let groupe = [];
  let idx = 0;
  let ouverture;
  let debut;
  let balaye = false;

  function montrer(i) {
    idx = (i + groupe.length) % groupe.length;
    img.src = groupe[idx].href;
    img.alt = (groupe[idx].querySelector('img') || {}).alt || '';
    compteur.textContent = (idx + 1) + ' / ' + groupe.length;
  }

  document.addEventListener('click', e => {
    const a = e.target.closest('a[data-lightbox]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    ouverture = a;
    groupe = Array.from(a.parentElement.querySelectorAll('a[data-lightbox]'));
    commandes.hidden = groupe.length < 2;
    debut = null;
    balaye = false;
    montrer(groupe.indexOf(a));
    box.showModal();
  });
  box.addEventListener('click', e => {
    if (e.target === box || e.target.closest('.lightbox-fermer')) box.close();
    const bouton = e.target.closest('[data-photo-pas]');
    if (bouton) montrer(idx + Number(bouton.dataset.photoPas));
  });
  box.addEventListener('close', () => {
    img.removeAttribute('src');
    ouverture?.focus({ preventScroll: true });
  });
  img.addEventListener('click', () => {
    if (groupe.length > 1 && !balaye) montrer(idx + 1);
    balaye = false;
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight') montrer(idx + 1);
    if (e.key === 'ArrowLeft') montrer(idx - 1);
  });
  img.addEventListener('touchstart', e => {
    debut = e.touches.length === 1 ? e.touches[0] : null;
    balaye = false;
  }, { passive: true });
  img.addEventListener('touchcancel', () => { debut = null; });
  img.addEventListener('touchend', e => {
    if (!debut || e.touches.length || groupe.length < 2) return;
    const fin = e.changedTouches[0];
    const x = fin.clientX - debut.clientX;
    const y = fin.clientY - debut.clientY;
    debut = null;
    if (Math.abs(x) < 45 || Math.abs(x) <= Math.abs(y)) return;
    balaye = true;
    montrer(idx + (x < 0 ? 1 : -1));
  }, { passive: true });
})();
