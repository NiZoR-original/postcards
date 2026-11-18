(function () {
  'use strict';
  const formulaire = document.getElementById('formulaire-carte');
  const { plateforme, reference, lien, pays_code: pays, ville, subdivision, distance_km: distance } = formulaire.elements;
  const prefixes = JSON.parse(formulaire.dataset.liens);
  const formats = JSON.parse(formulaire.dataset.formats);
  const aideReference = document.getElementById('format-reference');
  const aidePays = document.getElementById('pays-deduit');
  const codesPays = new Set(Array.from(pays.options, option => option.value));
  const champsLien = formulaire.querySelectorAll('[data-champ-lien]');
  const grilleReference = formulaire.querySelector('.reference-segments');
  const segments = ['prefixe', 'numero', 'suffixe'].map(partie => document.getElementById('reference-' + partie));
  const finReference = grilleReference.querySelectorAll('[data-reference-fin]');
  const ancienne = reference.dataset.existante === '1'
    ? { plateforme: plateforme.value, valeur: reference.value }
    : null;
  const initiaux = decouper(reference.value, formats[plateforme.value]);
  const saisieLibre = ancienne || (reference.value && !initiaux)
    ? { plateforme: plateforme.value, valeur: reference.value }
    : null;
  let lienAutomatique = lien.dataset.deduit === '1';
  let paysDeduit = '';

  function decouper(valeur, format) {
    if (!format) return null;
    const motif = '^(' + format.segments.join(')[\\s-]*(') + ')$';
    return valeur.trim().toUpperCase().match(new RegExp(motif))?.slice(1) ?? null;
  }

  function remplirSegments(valeurs) {
    segments.forEach((champ, index) => { champ.value = valeurs[index] ?? ''; });
  }

  function majPaysReference(format) {
    const envoyee = formulaire.elements.sens.value === 'envoyee';
    const index = envoyee ? 2 : 0;
    let code = '';
    if (format?.segments[index]) {
      code = reference.type === 'hidden'
        ? segments[index].value
        : (decouper(reference.value, format)?.[index] ?? '');
    }
    if (!codesPays.has(code)) code = '';
    if (pays.value && pays.value !== paysDeduit) return;
    if (pays.value !== code) {
      pays.value = code;
      pays.dispatchEvent(new Event('change', { bubbles: true }));
    }
    paysDeduit = code;
    aidePays.textContent = code ? 'Déduit de la référence' : '';
  }

  function majLien() {
    const format = formats[plateforme.value];
    majPaysReference(format);
    if (!format) return;
    const conserver = ancienne?.plateforme === plateforme.value && ancienne.valeur === reference.value;
    if (reference.type === 'text' && !conserver) reference.pattern = format.segments.join('-');
    else reference.removeAttribute('pattern');
    if (!lienAutomatique) return;
    const identifiant = conserver ? reference.value : decouper(reference.value, format)?.join('-');
    lien.value = identifiant ? prefixes[plateforme.value] + encodeURIComponent(identifiant) : '';
  }

  function composerReference() {
    const actifs = segments.filter(champ => !champ.disabled);
    const valeurs = actifs.map(champ => champ.value);
    const renseignee = valeurs.some(Boolean);
    for (const champ of actifs) champ.required = renseignee;
    reference.value = renseignee ? valeurs.join('-') : '';
    majLien();
  }

  function majPlateforme() {
    const format = formats[plateforme.value];
    const visible = Boolean(format);
    const segmente = visible && saisieLibre?.plateforme !== plateforme.value;
    const avecSuffixe = segmente && format.segments.length === 3;
    for (const champ of champsLien) champ.hidden = !visible;
    grilleReference.hidden = !segmente;
    reference.type = segmente ? 'hidden' : 'text';
    reference.disabled = lien.disabled = !visible;
    reference.placeholder = format?.exemple ?? '';
    aideReference.textContent = format ? format.exemple + ' · 1 à 9 chiffres' : '';
    for (const element of finReference) element.hidden = !avecSuffixe;
    segments.forEach((champ, index) => {
      champ.disabled = !segmente || (index === 2 && !avecSuffixe);
    });
    if (segmente) {
      composerReference();
    } else if (visible) {
      reference.value = saisieLibre.valeur;
      majLien();
    } else {
      majPaysReference();
    }
  }

  for (const [index, champ] of segments.entries()) {
    const suivant = segments[index + 1];
    champ.addEventListener('input', event => {
      const initial = champ.value;
      champ.value = initial.toUpperCase().replace(index === 1 ? /[^0-9]/g : /[^A-Z]/g, '').slice(0, champ.maxLength);
      composerReference();
      if (suivant && !suivant.disabled && champ.value.length === champ.maxLength && !event.inputType?.startsWith('delete')) {
        suivant.focus();
      }
    });
    champ.addEventListener('keydown', event => {
      if (event.key !== '-' || !suivant || suivant.disabled || !champ.value) return;
      event.preventDefault();
      suivant.focus();
    });
    champ.addEventListener('paste', event => {
      const valeurs = decouper(event.clipboardData.getData('text'), formats[plateforme.value]);
      if (!valeurs) return;
      event.preventDefault();
      remplirSegments(valeurs);
      composerReference();
    });
  }
  plateforme.addEventListener('change', majPlateforme);
  reference.addEventListener('input', () => {
    if (saisieLibre) saisieLibre.valeur = reference.value;
    majLien();
  });
  reference.addEventListener('blur', () => {
    if (!saisieLibre || ancienne?.valeur === reference.value) return;
    const valeurs = decouper(reference.value, formats[plateforme.value]);
    if (valeurs) reference.value = saisieLibre.valeur = valeurs.join('-');
    majLien();
  });
  lien.addEventListener('input', () => { lienAutomatique = lien.value === ''; });

  for (const sens of formulaire.querySelectorAll('input[name=sens]')) {
    sens.addEventListener('change', () => {
      document.getElementById('lib-pays').textContent = sens.value === 'envoyee' ? 'Pays de destination' : "Pays d’origine";
      majPaysReference(formats[plateforme.value]);
    });
  }

  const champSubdivision = document.getElementById('champ-subdivision');
  const libelleSubdivision = document.getElementById('lib-subdivision');
  function majSubdivision() {
    let visible = false;
    for (const groupe of subdivision.querySelectorAll('optgroup')) {
      const actif = groupe.dataset.pays === pays.value;
      groupe.hidden = !actif;
      groupe.disabled = !actif;
      visible = visible || actif;
    }
    champSubdivision.hidden = !visible;
    libelleSubdivision.textContent = pays.value === 'FR' ? 'Département' : 'État / province';
    if (subdivision.selectedOptions[0]?.parentElement.disabled) subdivision.value = '';
  }
  pays.addEventListener('change', () => {
    paysDeduit = '';
    aidePays.textContent = '';
  });
  pays.addEventListener('change', majSubdivision);
  majSubdivision();

  const bouton = document.getElementById('calculer-distance');
  const statut = document.getElementById('statut-distance');
  const choix = {
    domicile: document.querySelector('#choix-domicile select'),
    correspondant: document.querySelector('#choix-correspondant select'),
  };
  let requete;
  let lieux;
  let valeurCalculee;

  function viderCalcul() {
    requete?.abort();
    requete = null;
    lieux = null;
    if (distance.value === valeurCalculee) distance.value = '';
    valeurCalculee = null;
    statut.textContent = '';
    bouton.disabled = false;
    for (const select of Object.values(choix)) select.parentElement.hidden = true;
  }

  function afficherChoix(select, candidats) {
    select.replaceChildren();
    if (candidats.length !== 1) select.add(new Option('— Choisir une localité —', ''));
    candidats.forEach((lieu, index) => select.add(new Option(lieu.libelle, index)));
    select.parentElement.hidden = candidats.length === 1;
  }

  function calculer() {
    if (!lieux) return;
    const origine = lieux.domicile[choix.domicile.value];
    const destination = lieux.correspondant[choix.correspondant.value];
    if (!origine || !destination) {
      statut.textContent = 'Plusieurs localités correspondent à la recherche. Choisis la bonne dans la liste.';
      return;
    }
    const radians = degres => degres * Math.PI / 180;
    const lat1 = radians(origine.latitude);
    const lat2 = radians(destination.latitude);
    const demiEcartLon = radians(destination.longitude - origine.longitude) / 2;
    const a = Math.sin((lat2 - lat1) / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(demiEcartLon) ** 2;
    valeurCalculee = String(Math.round(2 * 6371 * Math.asin(Math.min(1, Math.sqrt(a)))));
    distance.value = valeurCalculee;
    statut.textContent = origine.libelle + ' ↔ ' + destination.libelle + ' · ' + valeurCalculee + ' km';
  }

  pays.addEventListener('change', viderCalcul);
  subdivision.addEventListener('change', viderCalcul);
  ville.addEventListener('input', viderCalcul);
  for (const select of Object.values(choix)) select.addEventListener('change', calculer);

  bouton.addEventListener('click', async () => {
    viderCalcul();
    const active = new AbortController();
    requete = active;
    bouton.disabled = true;
    statut.textContent = 'Recherche des localités…';
    try {
      const reponse = await fetch('distance.php', {
        method: 'POST',
        body: new URLSearchParams({
          csrf: formulaire.elements.csrf.value,
          pays: pays.value,
          ville: ville.value,
          subdivision: subdivision.value,
        }),
        signal: active.signal,
      });
      const donnees = await reponse.json();
      if (active.signal.aborted) return;
      if (!reponse.ok) {
        statut.textContent = donnees.erreur;
        return;
      }
      lieux = donnees;
      for (const [cle, select] of Object.entries(choix)) afficherChoix(select, lieux[cle]);
      calculer();
    } catch (erreur) {
      if (!active.signal.aborted) statut.textContent = 'Calcul indisponible. Tu peux saisir la distance manuellement.';
    } finally {
      if (requete === active) {
        requete = null;
        bouton.disabled = false;
      }
    }
  });
  remplirSegments(initiaux ?? []);
  majPlateforme();
})();
