(() => {
  const root = document.getElementById('global-chat-root');
  if(!root) return;
  const currentFullChat = Number(root.dataset.currentChat || 0);
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const open = new Map();

  function esc(v){ const d=document.createElement('div'); d.textContent=v||''; return d.innerHTML; }
  function apiPost(url,data){ if(data instanceof FormData){ if(!data.has('csrf_token')) data.append('csrf_token',csrf); } return fetch(url,{method:'POST',body:data,credentials:'same-origin'}); }

  function removeOldestIfNeeded(){
    const max = window.innerWidth < 700 ? 1 : 3;
    while(open.size >= max){
      const first = open.keys().next().value;
      const item = open.get(first); if(item) item.el.remove(); open.delete(first);
    }
  }

  function openMiniChat(person){
    const id=Number(person.user_id); if(!id || id===currentFullChat) return;
    if(open.has(id)){ load(open.get(id), false); return; }
    removeOldestIfNeeded();
    const el=document.createElement('section'); el.className='mini-chat-window'; el.dataset.userId=id;
    el.innerHTML=`<header class="mini-chat-head"><a href="profile.php?user_id=${id}"><img class="avatar tiny" src="${esc(person.avatar)}" alt=""></a><a class="mini-chat-name" href="profile.php?user_id=${id}">${esc(person.name)}</a><div class="mini-chat-head-actions"><button type="button" class="js-call-button" data-call-type="voice" data-user-id="${id}" data-user-name="${esc(person.name)}" data-user-avatar="${esc(person.avatar)}" title="${esc(root.dataset.labelVoice)}"><i class="fas fa-phone"></i></button><button type="button" class="js-call-button" data-call-type="video" data-user-id="${id}" data-user-name="${esc(person.name)}" data-user-avatar="${esc(person.avatar)}" title="${esc(root.dataset.labelVideo)}"><i class="fas fa-video"></i></button><button type="button" class="mini-chat-close" title="${esc(root.dataset.labelClose)}"><i class="fas fa-times"></i></button></div></header><div class="mini-chat-body"><div class="mini-chat-loading">...</div></div><form class="mini-chat-form"><input type="hidden" name="incoming_id" value="${id}"><label class="mini-attach-btn" title="${esc(root.dataset.labelAttach)}"><i class="fas fa-paperclip"></i><input type="file" name="attachment"></label><span class="mini-file-name"></span><input class="mini-chat-input" name="message" maxlength="5000" autocomplete="off" placeholder="${esc(root.dataset.labelMessage)}"><button type="submit"><i class="fas fa-paper-plane"></i></button></form>`;
    root.appendChild(el);
    const item={el, body:el.querySelector('.mini-chat-body'), form:el.querySelector('form'), id, person, timer:null}; open.set(id,item);
    el.querySelector('.mini-chat-close').addEventListener('click',()=>{ clearInterval(item.timer); el.remove(); open.delete(id); });
    const miniFile=item.form.elements.attachment, miniFileName=el.querySelector('.mini-file-name'); miniFile.addEventListener('change',()=>{miniFileName.textContent=miniFile.files[0]?.name||'';});
    item.form.addEventListener('submit', async e=>{
      e.preventDefault(); const text=item.form.elements.message.value.trim(); const file=item.form.elements.attachment.files[0]; if(!text&&!file) return;
      const res=await apiPost('api/insert-chat.php',new FormData(item.form)); if(res.ok){ item.form.reset(); miniFileName.textContent=''; load(item,true); }
    });
    load(item,true); item.timer=setInterval(()=>load(item,false),1800);
  }

  async function load(item, scroll){
    const fd=new FormData(); fd.append('incoming_id',item.id); fd.append('mark_read',document.visibilityState==='visible'?'1':'0');
    try{ const res=await apiPost('api/get-chat.php',fd); if(!res.ok) return; item.body.innerHTML=await res.text(); if(scroll) item.body.scrollTop=item.body.scrollHeight; }catch(e){}
  }

  async function poll(){
    try{
      const res=await fetch('api/chat-notifications.php',{credentials:'same-origin'}); if(!res.ok) return; const data=await res.json();
      const max=window.innerWidth<700?1:3; (data.items||[]).slice(0,max).reverse().forEach(p=>openMiniChat(p));
    }catch(e){}
  }

  document.addEventListener('click',e=>{
    const link=e.target.closest('.js-mini-chat'); if(!link) return;
    if(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey) return;
    const id=Number(link.dataset.userId||0); if(!id) return; e.preventDefault();
    openMiniChat({user_id:id,name:link.dataset.userName||root.dataset.labelNew,avatar:link.dataset.userAvatar||'assets/img/default-avatar.svg'});
  });
  poll(); setInterval(poll,2200);
  window.RunakiMiniChat={open:openMiniChat};
})();
