(() => {
  const form=document.querySelector('.typing-area'); if(!form) return;
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const input=form.querySelector('.input-field'), file=form.querySelector('.attachment-input'), fileName=form.querySelector('.selected-file-name'), chatBox=document.querySelector('.chat-box'), incomingId=form.querySelector('.incoming_id').value;
  let userIsReading=false;
  file?.addEventListener('change',()=>{ fileName.textContent=file.files[0]?.name||''; });
  form.addEventListener('submit',async e=>{
    e.preventDefault(); if(!input.value.trim() && !file?.files[0]) return;
    const res=await fetch('api/insert-chat.php',{method:'POST',body:new FormData(form),credentials:'same-origin'});
    if(res.ok){ form.reset(); fileName.textContent=''; loadChat(true); }
    else alert(await res.text());
  });
  async function loadChat(force=false){
    const fd=new FormData(); fd.append('csrf_token',csrf); fd.append('incoming_id',incomingId); fd.append('mark_read',document.visibilityState==='visible'?'1':'0');
    try{ const res=await fetch('api/get-chat.php',{method:'POST',body:fd,credentials:'same-origin'}); if(!res.ok)return; chatBox.innerHTML=await res.text(); if(force||!userIsReading) chatBox.scrollTop=chatBox.scrollHeight; }catch(e){}
  }
  chatBox.addEventListener('mouseenter',()=>userIsReading=true); chatBox.addEventListener('mouseleave',()=>userIsReading=false);
  loadChat(true); setInterval(()=>loadChat(false),1500);
})();
