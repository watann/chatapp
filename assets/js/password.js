document.querySelectorAll('.password-field i').forEach((icon) => {
  icon.addEventListener('click', () => {
    const input = icon.parentElement.querySelector('input');
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('active');
  });
});
