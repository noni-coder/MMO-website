/* ============================================================
   MMO — Bandeau & Agenda dynamiques
   Lit data/concerts.json et met à jour :
   - le bandeau défilant (.bknews-track)
   - la section Agenda (#agendaGrid)
   Si le fichier est illisible, le contenu HTML existant reste
   affiché (aucune casse possible).
   ============================================================ */
(function () {
  'use strict';

  var MOIS = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUIN', 'JUIL', 'AOÛT', 'SEP', 'OCT', 'NOV', 'DÉC'];
  var MOIS_LONG = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

  function parseDate(s) {
    var p = (s || '').split('-');
    if (p.length !== 3) return null;
    return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  }

  function aujourdHui() {
    var n = new Date();
    return new Date(n.getFullYear(), n.getMonth(), n.getDate());
  }

  function dateLongueFr(d) {
    return d.getDate() + ' ' + MOIS_LONG[d.getMonth()] + ' ' + d.getFullYear();
  }

  function esc(t) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(t || ''));
    return div.innerHTML;
  }

  function prochainConcert(concerts) {
    var today = aujourdHui();
    var futurs = concerts
      .map(function (c) { return { c: c, d: parseDate(c.date) }; })
      .filter(function (x) { return x.d && x.d >= today; })
      .sort(function (a, b) { return a.d - b.d; });
    return futurs.length ? futurs[0] : null;
  }

  /* ---------- BANDEAU ---------- */
  function majBandeau(data) {
    var track = document.querySelector('.bknews-track');
    if (!track) return;

    var messages = [];
    var next = prochainConcert(data.concerts || []);

    if (data.bandeau && data.bandeau.auto_prochain_concert && next) {
      messages.push('PROCHAIN CONCERT LE ' + dateLongueFr(next.d).toUpperCase() +
        ' - ' + (next.c.lieu || '').toUpperCase() + ' *');
    }
    (data.bandeau && data.bandeau.messages_fixes || []).forEach(function (m) { messages.push(m); });
    (data.bandeau && data.bandeau.messages_supplementaires || []).forEach(function (m) { messages.push(m + ' *'); });

    if (!messages.length) return;

    // Deux copies du contenu pour la boucle infinie (l'animation translate -50%)
    var html = '';
    for (var copie = 0; copie < 2; copie++) {
      html += '<span>';
      messages.forEach(function (m) { html += '<span>' + esc(m) + '&nbsp;&nbsp;•&nbsp;&nbsp;</span>'; });
      html += '</span>';
    }
    track.innerHTML = html;
  }

  /* ---------- AGENDA ---------- */
  function carteConcert(c, estProchain) {
    var d = parseDate(c.date);
    var passe = d < aujourdHui();
    var html = '';
    html += '<div class="acard' + (estProchain ? ' next' : '') + '">';
    html += '  <div class="adbox">';
    html += '    <span class="adday">' + d.getDate() + '</span>';
    html += '    <span class="admon">' + MOIS[d.getMonth()] + '</span>';
    html += '    <span class="adyr">' + d.getFullYear() + '</span>';
    html += '  </div>';
    html += '  <div class="adets">';
    html += '    <h3>' + esc(c.titre) + '</h3>';
    html += '    <p class="loc">' + esc(c.lieu) + '</p>';
    if (c.note) html += '    <p class="note">' + esc(c.note) + '</p>';
    if (passe) {
      html += '<span class="abadge bpast">Passé</span>';
    } else if (estProchain) {
      html += '<span class="abadge bnext"' + (c.popup ? ' data-popup="' + esc(c.id) + '"' : '') + '>Prochain</span>';
    } else {
      html += '<span class="abadge bnext"' + (c.popup ? ' data-popup="' + esc(c.id) + '"' : '') + '>À venir</span>';
    }
    if (c.photo) {
      html += '<img class="aphoto" src="' + esc(c.photo) + '" alt="' + esc(c.titre) + '" loading="lazy">';
    }
    html += '  </div>';
    html += '</div>';
    return html;
  }

  function popupConcert(c) {
    var d = parseDate(c.date);
    return '<div class="popup-overlay" id="popup-' + esc(c.id) + '" onclick="this.classList.remove(\'active\')">' +
      '<div class="popup-box" onclick="event.stopPropagation()">' +
      '<button class="popup-close" onclick="this.closest(\'.popup-overlay\').classList.remove(\'active\')">&times;</button>' +
      '<h3>' + esc(c.titre) + ' — ' + dateLongueFr(d) + '</h3>' +
      '<p>' + esc(c.popup) + '</p>' +
      '</div></div>';
  }

  function majAgenda(data) {
    var grid = document.querySelector('.agrid');
    if (!grid || !(data.concerts || []).length) return;

    var next = prochainConcert(data.concerts);
    var tries = data.concerts.slice().sort(function (a, b) {
      return parseDate(a.date) - parseDate(b.date);
    });

    var html = '';
    var popups = '';
    tries.forEach(function (c) {
      var estProchain = !!(next && next.c.id === c.id);
      html += carteConcert(c, estProchain);
      if (c.popup) popups += popupConcert(c);
    });

    grid.innerHTML = html;

    // Popups : on les insère après la section agenda
    var anciens = document.querySelectorAll('.popup-overlay[id^="popup-"]');
    anciens.forEach(function (p) { p.remove(); });
    if (popups) grid.closest('section').insertAdjacentHTML('afterend', popups);

    // Clic sur badge → ouvrir popup
    grid.querySelectorAll('[data-popup]').forEach(function (b) {
      b.style.cursor = 'pointer';
      b.addEventListener('click', function () {
        var p = document.getElementById('popup-' + b.getAttribute('data-popup'));
        if (p) p.classList.add('active');
      });
    });
  }

  /* ---------- CHARGEMENT ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    fetch('data/concerts.json?t=' + Date.now())
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (data) {
        majBandeau(data);
        majAgenda(data);
      })
      .catch(function (e) {
        // Le contenu statique du HTML reste affiché : aucune casse.
        console.warn('concerts.json non chargé :', e);
      });
  });
})();
