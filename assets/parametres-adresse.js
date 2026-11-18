(function () {
  'use strict';
  const formulaire = document.querySelector('[data-formats-adresse]');
  const formats = JSON.parse(formulaire.dataset.formatsAdresse);
  const pays = formulaire.elements.adresse_pays;
  const zone = formulaire.querySelector('.adresse-grille');
  const champs = [...zone.querySelectorAll('[data-adresse-champ]')];

  pays.addEventListener('change', () => {
    const format = formats[pays.value];
    for (const champ of champs) {
      const position = format.ordre.indexOf(champ.dataset.adresseChamp);
      champ.hidden = position === -1;
      champ.querySelector('input').disabled = champ.hidden;
      champ.querySelector('span').textContent = format.libelles[champ.dataset.adresseCle] ?? champ.dataset.libelle;
    }
    for (const type of format.ordre) {
      for (const champ of champs.filter(element => element.dataset.adresseChamp === type)) zone.append(champ);
    }
  });
})();
