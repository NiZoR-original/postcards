// jsVectorMap ne publie pas ses fonds de carte (Map.maps est interne) : on garde une copie de chaque
// fond au moment où son fichier l'enregistre. map.js y lit la liste des zones dessinées et la projection.
// À charger après jsvectormap.min.js et avant les fichiers de fonds.
(function () {
  'use strict';
  var ajouter = jsVectorMap.addMap;
  window.PC_FONDS = window.PC_FONDS || {};
  jsVectorMap.addMap = function (nom, fond) {
    window.PC_FONDS[nom] = fond;
    return ajouter.apply(this, arguments);
  };
})();
