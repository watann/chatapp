const form = document.querySelector('.signup form');
const submitBtn = form.querySelector('.button input');
const errorText = document.querySelector('.error-text');

form.addEventListener('submit', (e) => {
  e.preventDefault();
  errorText.style.display = 'none';
  submitBtn.disabled = true;
  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'api/signup.php', true);
  xhr.onload = () => {
    submitBtn.disabled = false;
    if(xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200){
      const data = xhr.response.trim();
      if(data === 'success') location.href = 'home.php';
      else { errorText.style.display = 'block'; errorText.textContent = data; }
    }
  };
  xhr.onerror = () => { submitBtn.disabled = false; };
  xhr.send(new FormData(form));
});
