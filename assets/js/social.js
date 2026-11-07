document.addEventListener('click', (event) => {
  const opener = event.target.closest('[data-open-dialog]');
  if(opener){
    const dialog = document.getElementById(opener.dataset.openDialog);
    if(dialog && typeof dialog.showModal === 'function') dialog.showModal();
    return;
  }

  const closer = event.target.closest('[data-close-dialog]');
  if(closer){
    const dialog = closer.closest('dialog');
    if(dialog) dialog.close();
    return;
  }

  const commentButton = event.target.closest('[data-focus-comment]');
  if(commentButton){
    const input = document.getElementById(commentButton.dataset.focusComment);
    if(input){ input.focus(); input.scrollIntoView({block:'center', behavior:'smooth'}); }
  }
});

document.querySelectorAll('.js-confirm-delete').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if(!window.confirm(form.dataset.confirm || 'Delete?')) event.preventDefault();
  });
});

document.querySelectorAll('dialog.social-dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if(event.target === dialog) dialog.close();
  });
});
