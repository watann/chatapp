(() => {
  const root = document.documentElement;
  const form = document.getElementById('appearance-settings-form');
  if (!form) return;

  const resolveTheme = (mode) => mode === 'system'
    ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : mode;

  const themeSelect = form.querySelector('[data-setting="theme"]');
  const densityInputs = form.querySelectorAll('[data-setting="density"]');
  const accentInputs = form.querySelectorAll('[data-setting="accent"]');

  if (themeSelect) {
    themeSelect.addEventListener('change', () => {
      root.dataset.theme = themeSelect.value;
      root.dataset.resolvedTheme = resolveTheme(themeSelect.value);
    });
  }

  densityInputs.forEach(input => input.addEventListener('change', () => {
    if (input.checked) root.dataset.density = input.value;
  }));

  accentInputs.forEach(input => input.addEventListener('change', () => {
    if (input.checked) root.dataset.accent = input.value;
  }));
})();
