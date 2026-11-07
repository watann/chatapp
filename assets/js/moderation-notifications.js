/* Read-only, role-scoped admin post alerts; polls while the admin dashboard is open. */
(() => {
  'use strict';
  const badge = document.querySelector('[data-moderation-badge]');
  if (!badge) return;
  const label = badge.parentElement?.querySelector('span:not(.moderation-nav-badge)')?.textContent || 'ئاگادارکردنەوە';
  const key = 'chatapp_mod_alert_' + label;
  async function refresh() {
    try {
      const response = await fetch('api/moderation-counts.php', { credentials: 'same-origin', cache: 'no-store' });
      if (!response.ok) return;
      const json = await response.json();
      const isIT = label.includes('IT');
      const count = Number(isIT ? json.words : json.reports) || 0;
      const latest = Number(isIT ? json.latest_word : json.latest_report) || 0;
      badge.textContent = String(count);
      badge.hidden = count < 1;
      const previous = Number(sessionStorage.getItem(key) || 0);
      if (previous > 0 && latest > previous && count > 0) {
        const note = document.createElement('a');
        note.className = 'moderation-toast';
        note.href = 'moderation.php';
        note.textContent = isIT ? 'ئاگادارکردنەوەی نوێی وشەی فلتەرکراو — کرتە بکە بۆ بینین' : 'ڕیپۆرتێکی نوێ بۆ پۆست هاتووە — کرتە بکە بۆ بینین';
        document.body.appendChild(note);
        setTimeout(() => note.remove(), 7000);
      }
      sessionStorage.setItem(key, String(Math.max(previous, latest)));
    } catch (_) { /* Network failure must not interrupt admin work. */ }
  }
  refresh();
  setInterval(() => { if (!document.hidden) refresh(); }, 25000);
})();
