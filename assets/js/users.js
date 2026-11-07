const searchBar = document.querySelector('.search-bar input');
const usersList = document.querySelector('.users-list');

function loadUsers(searchTerm = ''){
  const xhr = new XMLHttpRequest();
  xhr.open('POST', searchTerm ? 'api/search.php' : 'api/users.php', true);
  xhr.onload = () => {
    if(xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200){
      usersList.innerHTML = xhr.response;
    }
  };
  if(searchTerm){
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.send('searchTerm=' + encodeURIComponent(searchTerm));
  }else{
    xhr.send();
  }
}

searchBar.addEventListener('input', () => loadUsers(searchBar.value.trim()));
loadUsers();
setInterval(() => {
  if(!searchBar.value.trim()) loadUsers();
}, 3000);
