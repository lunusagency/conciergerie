/* HOMËA — interactions de la maquette.
   Règle : le script ne fait que basculer des classes. Aucun contenu n'est
   injecté ; tout ce qui s'affiche est déjà dans la page (et donc éditable). */
(function () {
  'use strict';

  /* Lien actif du menu, déduit de l'URL */
  var page = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.menu a').forEach(function (a) {
    if (a.getAttribute('href') === page) a.classList.add('actif');
  });

  /* Menu mobile */
  var entete = document.querySelector('.entete');
  var burger = document.querySelector('.burger');
  if (entete && burger) {
    burger.addEventListener('click', function () {
      var ouvert = entete.classList.toggle('menu-ouvert');
      burger.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
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
