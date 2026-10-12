/* HOMËA — interactions du site.
   Règle : le script ne fait que basculer des classes. Aucun contenu n'est
   injecté : tout ce qui s'affiche est dans la page, donc modifiable dans
   l'éditeur. Sélection par classe, jamais par id. */
(function () {
  'use strict';

  /* Lien actif du menu (secours si WordPress ne l'a pas marqué) */
  var menu = document.querySelector('.menu');
  if (menu && !menu.querySelector('.current-menu-item')) {
    var ici = location.pathname.replace(/\/+$/, '') || '/';
    [].forEach.call(menu.querySelectorAll('a'), function (a) {
      var chemin = new URL(a.href, location.href).pathname.replace(/\/+$/, '') || '/';
      if (chemin === ici) {
        a.classList.add('actif');
        if (a.parentElement) a.parentElement.classList.add('current-menu-item');
      }
    });
  }

  /* Diaporama du héros : puces 01 / 02 / 03 */
  var puces = [].slice.call(document.querySelectorAll('.hero-pagination .puce'));
  var diapos = [].slice.call(document.querySelectorAll('.hero-visuels .diapo'));
  if (puces.length && diapos.length) {
    var courant = 0, minuterie = null;
    var montrer = function (i) {
      courant = (i + diapos.length) % diapos.length;
      puces.forEach(function (p, k) { p.classList.toggle('est-actif', k === courant); });
      diapos.forEach(function (d, k) { d.classList.toggle('est-actif', k === courant); });
    };
    var relancer = function () {
      clearInterval(minuterie);
      if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        minuterie = setInterval(function () { montrer(courant + 1); }, 6500);
      }
    };
    puces.forEach(function (p, k) {
      p.setAttribute('role', 'button');
      p.setAttribute('tabindex', '0');
      p.setAttribute('aria-label', 'Afficher la photo ' + (k + 1));
      var activer = function () { montrer(k); relancer(); };
      p.addEventListener('click', activer);
      p.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activer(); }
      });
    });
    relancer();
  }
})();
