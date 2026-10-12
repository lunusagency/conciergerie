/* HOMËA — prise de rendez-vous téléphonique et formulaire de contact.
   Les créneaux viennent de /wp-json/homea/v1/creneaux. En maquette
   (window.HomeaContact.demo), des disponibilités fictives sont générées. */
(function () {
  'use strict';

  var reglage = window.HomeaContact || {};
  var DEMO = !!reglage.demo;
  var fmtJour = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
  var fmtMois = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
  var fmtLong = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

  function enDate(ymd) {
    var p = ymd.split('-');
    return new Date(+p[0], +p[1] - 1, +p[2], 12);
  }
  function heureFr(h) {
    var p = h.split(':');
    return +p[0] + ' h ' + p[1];
  }
  function el(tag, cls, texte) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (texte != null) e.textContent = texte;
    return e;
  }

  /* ---------- Données ---------- */

  function demoCreneaux() {
    var jours = [], d = new Date(), graine = 7;
    var alea = function () { graine = (graine * 9301 + 49297) % 233280; return graine / 233280; };
    for (var i = 1; i <= 21; i++) {
      var j = new Date(d.getFullYear(), d.getMonth(), d.getDate() + i, 12);
      var n = j.getDay();
      if (n === 0) continue;
      var plages = n === 6 ? [[570, 750]] : [[540, 750], [840, n === 5 ? 1080 : 1110]];
      var liste = [];
      plages.forEach(function (p) {
        for (var m = p[0]; m + 30 <= p[1]; m += 30) {
          if (alea() < 0.28) continue;
          liste.push(String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'));
        }
      });
      var ymd = j.getFullYear() + '-' + String(j.getMonth() + 1).padStart(2, '0') + '-' + String(j.getDate()).padStart(2, '0');
      if (liste.length) jours.push({ date: ymd, creneaux: liste });
    }
    return Promise.resolve({ duree: 30, jours: jours });
  }

  function charger(api) {
    if (DEMO) return demoCreneaux();
    return fetch(api + 'creneaux', { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); });
  }

  function envoyer(api, route, donnees) {
    if (DEMO) {
      return new Promise(function (ok) {
        setTimeout(function () {
          ok({ ok: true, message: route === 'rendez-vous'
            ? 'Votre rendez-vous est réservé pour le ' + fmtLong.format(enDate(donnees.date)) + ' à ' + heureFr(donnees.heure) + '. Une confirmation vient de vous être envoyée par e-mail.'
            : 'Merci, votre message est bien envoyé. Nous vous répondons personnellement dans les meilleurs délais.' });
        }, 600);
      });
    }
    return fetch(api + route, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(donnees)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) { var e = new Error(j.message || 'Une erreur est survenue. Merci de réessayer.'); e.statut = r.status; throw e; }
        return j;
      });
    });
  }

  function lireForm(form) {
    var d = {};
    [].forEach.call(form.elements, function (c) {
      if (!c.name) return;
      d[c.name] = c.type === 'checkbox' ? c.checked : c.value.trim();
    });
    return d;
  }

  function verifier(form) {
    var premier = null;
    [].forEach.call(form.querySelectorAll('[required]'), function (c) {
      var vide = c.type === 'checkbox' ? !c.checked : !c.value.trim();
      var faux = !vide && c.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(c.value.trim());
      if (!vide && c.type === 'tel') faux = c.value.replace(/\D/g, '').length < 9;
      var ko = vide || faux;
      var champ = c.closest('.homea-champ, .homea-accord');
      if (champ) champ.classList.toggle('est-invalide', ko);
      c.setAttribute('aria-invalid', ko ? 'true' : 'false');
      if (ko && !premier) premier = c;
    });
    if (premier) premier.focus();
    return !premier;
  }

  /* ---------- Rendez-vous ---------- */

  function rendezVous(bloc) {
    var api = bloc.getAttribute('data-api');
    var zoneJours = bloc.querySelector('.homea-jours');
    var zoneHeures = bloc.querySelector('.homea-heures');
    var form = bloc.querySelector('.homea-rdv-form');
    var recap = bloc.querySelector('.homea-recap-valeur');
    var retour = form.querySelector('.homea-retour');
    var bouton = form.querySelector('.homea-bouton');
    var ouverture = Date.now();
    var choix = { date: null, heure: null };
    var donnees = { jours: [] };

    function majRecap() {
      bloc.classList.toggle('a-choisi', !!(choix.date && choix.heure));
      recap.textContent = choix.date && choix.heure
        ? fmtLong.format(enDate(choix.date)) + ' à ' + heureFr(choix.heure)
        : choix.date ? fmtLong.format(enDate(choix.date)) + ' — choisissez une heure' : 'À choisir dans le calendrier';
    }

    function afficherHeures(jour) {
      zoneHeures.textContent = '';
      jour.creneaux.forEach(function (h) {
        var b = el('button', 'homea-heure', heureFr(h));
        b.type = 'button';
        b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', function () {
          choix.heure = h;
          [].forEach.call(zoneHeures.children, function (x) { x.classList.remove('est-actif'); x.setAttribute('aria-pressed', 'false'); });
          b.classList.add('est-actif');
          b.setAttribute('aria-pressed', 'true');
          retour.textContent = '';
          majRecap();
        });
        zoneHeures.appendChild(b);
      });
    }

    function afficherJours() {
      zoneJours.textContent = '';
      if (!donnees.jours.length) {
        zoneJours.appendChild(el('p', 'homea-info', 'Aucun créneau n’est disponible pour le moment. Écrivez-nous, nous vous répondrons rapidement.'));
        zoneHeures.textContent = '';
        return;
      }
      donnees.jours.forEach(function (j, i) {
        var d = enDate(j.date);
        var b = el('button', 'homea-jour');
        b.type = 'button';
        b.setAttribute('aria-pressed', 'false');
        b.setAttribute('aria-label', fmtLong.format(d) + ', ' + j.creneaux.length + ' créneaux');
        b.appendChild(el('span', 'homea-jour-nom', fmtJour.format(d).replace('.', '')));
        b.appendChild(el('span', 'homea-jour-num', String(d.getDate())));
        b.appendChild(el('span', 'homea-jour-mois', fmtMois.format(d).replace('.', '')));
        b.addEventListener('click', function () {
          choix.date = j.date;
          choix.heure = null;
          [].forEach.call(zoneJours.children, function (x) { x.classList.remove('est-actif'); x.setAttribute('aria-pressed', 'false'); });
          b.classList.add('est-actif');
          b.setAttribute('aria-pressed', 'true');
          afficherHeures(j);
          majRecap();
        });
        zoneJours.appendChild(b);
        if (i === 0) b.click();
      });
    }

    function rafraichir() {
      return charger(api).then(function (r) {
        donnees = r || { jours: [] };
        choix = { date: null, heure: null };
        afficherJours();
        majRecap();
      }).catch(function () {
        zoneJours.textContent = '';
        zoneJours.appendChild(el('p', 'homea-info', 'Le calendrier n’a pas pu être chargé. Merci de réessayer ou de nous écrire.'));
      });
    }

    // défilement des jours
    var defile = function (sens) { zoneJours.scrollBy({ left: sens * zoneJours.clientWidth * 0.8, behavior: 'smooth' }); };
    var prec = bloc.querySelector('.homea-prec'), suiv = bloc.querySelector('.homea-suiv');
    if (prec) prec.addEventListener('click', function () { defile(-1); });
    if (suiv) suiv.addEventListener('click', function () { defile(1); });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      retour.textContent = '';
      retour.classList.remove('est-erreur');
      if (!choix.date || !choix.heure) {
        retour.textContent = 'Merci de choisir un jour et une heure.';
        retour.classList.add('est-erreur');
        return;
      }
      if (!verifier(form)) {
        retour.textContent = 'Merci de compléter les champs indiqués.';
        retour.classList.add('est-erreur');
        return;
      }
      var d = lireForm(form);
      d.date = choix.date;
      d.heure = choix.heure;
      d.duree = Date.now() - ouverture;
      bouton.disabled = true;
      bloc.classList.add('est-en-cours');
      envoyer(api, 'rendez-vous', d).then(function (r) {
        bloc.querySelector('.homea-confirmation-texte').textContent = r.message;
        bloc.classList.add('est-confirme');
        bloc.querySelector('.homea-confirmation').hidden = false;
        bloc.querySelector('.homea-confirmation').scrollIntoView({ block: 'center', behavior: 'smooth' });
      }).catch(function (err) {
        retour.textContent = err.message;
        retour.classList.add('est-erreur');
        if (err.statut === 409) rafraichir();
      }).then(function () {
        bouton.disabled = false;
        bloc.classList.remove('est-en-cours');
      });
    });

    rafraichir();
  }

  /* ---------- Contact ---------- */

  function contact(form) {
    var api = form.getAttribute('data-api');
    var retour = form.querySelector('.homea-retour');
    var bouton = form.querySelector('.homea-bouton');
    var ouverture = Date.now();
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      retour.textContent = '';
      retour.classList.remove('est-erreur', 'est-ok');
      if (!verifier(form)) {
        retour.textContent = 'Merci de compléter les champs indiqués.';
        retour.classList.add('est-erreur');
        return;
      }
      var d = lireForm(form);
      d.duree = Date.now() - ouverture;
      bouton.disabled = true;
      envoyer(api, 'message', d).then(function (r) {
        form.reset();
        retour.textContent = r.message;
        retour.classList.add('est-ok');
      }).catch(function (err) {
        retour.textContent = err.message;
        retour.classList.add('est-erreur');
      }).then(function () { bouton.disabled = false; });
    });
  }

  function demarrer() {
    [].forEach.call(document.querySelectorAll('[data-homea="rendez-vous"]'), rendezVous);
    [].forEach.call(document.querySelectorAll('[data-homea="contact"]'), contact);
    // effacer l'alerte d'un champ dès qu'on le corrige
    document.addEventListener('input', function (e) {
      var champ = e.target.closest && e.target.closest('.est-invalide');
      if (champ) champ.classList.remove('est-invalide');
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', demarrer);
  else demarrer();
})();
