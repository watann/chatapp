(() => {
  const input = document.querySelector('[data-community-search]');
  if (!input) return;
  const cards = Array.from(document.querySelectorAll('[data-community-card]'));
  input.addEventListener('input', () => {
    const q = input.value.trim().toLocaleLowerCase();
    cards.forEach(card => {
      const haystack = (card.dataset.searchText || '').toLocaleLowerCase();
      card.hidden = q !== '' && !haystack.includes(q);
    });
  });
})();
