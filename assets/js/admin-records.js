(() => {
  const select = document.getElementById('registry-person-type');
  if (!select) return;
  const blocks = [...document.querySelectorAll('[data-role-fields]')];
  const update = () => {
    blocks.forEach((block) => {
      const active = block.dataset.roleFields === select.value;
      block.hidden = !active;
      block.querySelectorAll('input,select').forEach((el) => { el.required = active; if (!active) el.value = ''; });
    });
  };
  select.addEventListener('change', update);
  update();
})();
