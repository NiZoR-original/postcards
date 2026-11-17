// Sélecteur de thème (système / clair / sombre). Le choix est gardé un an dans le cookie pc_theme,
// que le serveur relit pour poser data-theme sur <html> dès l'affichage (voir theme_courant() en PHP).
// « auto » = pas d'attribut, le thème suit le système ; le cookie est alors supprimé.
(function () {
  'use strict';
  const boutons = document.querySelectorAll('[data-theme-choix]');
  boutons.forEach(btn => btn.addEventListener('click', () => {
    const choix = btn.dataset.themeChoix;
    if (choix === 'auto') {
      document.documentElement.removeAttribute('data-theme');
      document.cookie = 'pc_theme=; path=/; max-age=0; SameSite=Lax';
    } else {
      document.documentElement.setAttribute('data-theme', choix);
      document.cookie = 'pc_theme=' + choix + '; path=/; max-age=31536000; SameSite=Lax';
    }
    boutons.forEach(b => {
      const actif = b === btn;
      b.classList.toggle('actif', actif);
      b.setAttribute('aria-pressed', actif ? 'true' : 'false');
    });
  }));
})();
