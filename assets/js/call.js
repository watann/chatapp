(() => {
  const incomingCard=document.getElementById('incoming-call-card');
  const overlay=document.getElementById('active-call-overlay');
  if(!incomingCard || !overlay) return;
  const i18n=window.RUNAKI_CALL_I18N||{};
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const state={callId:0,type:'voice',other:null,peer:null,local:null,lastSignal:0,pollTimer:null,incomingTimer:null,pendingCandidates:[],accepted:false};
  const remoteVideo=document.getElementById('remote-video');
  const localVideo=document.getElementById('local-video');
  const remoteAudio=document.getElementById('remote-audio');

  async function post(data){ const fd=new FormData(); Object.entries(data).forEach(([k,v])=>fd.append(k,v)); fd.append('csrf_token',csrf); const r=await fetch('api/call-action.php',{method:'POST',body:fd,credentials:'same-origin'}); const j=await r.json().catch(()=>({ok:false,error:i18n.call_failed||'Call failed'})); if(!r.ok||!j.ok) throw new Error(j.error||i18n.call_failed||'Call failed'); return j; }
  function showStatus(text){ document.getElementById('active-call-status').textContent=text||''; }
  function showOverlay(person,type){ overlay.hidden=false; document.getElementById('active-call-name').textContent=person.name||''; document.getElementById('active-call-avatar').src=person.avatar||'assets/img/default-avatar.svg'; state.type=type; overlay.classList.toggle('video-mode',type==='video'); localVideo.hidden=type!=='video'; remoteVideo.hidden=type!=='video'; remoteAudio.hidden=type==='video'; document.getElementById('toggle-camera').hidden=type!=='video'; }
  async function getMedia(type){
    if(!navigator.mediaDevices?.getUserMedia || (!window.isSecureContext && location.hostname!=='localhost')) throw new Error(i18n.secure_context_required||'HTTPS is required for calls.');
    return navigator.mediaDevices.getUserMedia({audio:true,video:type==='video'});
  }
  function cleanup(show=''){
    clearInterval(state.pollTimer); state.pollTimer=null;
    if(state.peer){ try{state.peer.close();}catch(e){} state.peer=null; }
    if(state.local){ state.local.getTracks().forEach(t=>t.stop()); state.local=null; }
    remoteVideo.srcObject=null; localVideo.srcObject=null; remoteAudio.srcObject=null;
    if(show){ showStatus(show); setTimeout(()=>{overlay.hidden=true;},900); } else overlay.hidden=true;
    state.callId=0; state.lastSignal=0; state.pendingCandidates=[]; state.accepted=false;
  }
  function makePeer(){
    const pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
    state.local.getTracks().forEach(t=>pc.addTrack(t,state.local));
    localVideo.srcObject=state.local;
    pc.ontrack=e=>{ const stream=e.streams[0]; if(state.type==='video') remoteVideo.srcObject=stream; else remoteAudio.srcObject=stream; };
    pc.onicecandidate=e=>{ if(e.candidate && state.callId) post({action:'signal',call_id:state.callId,signal_type:'candidate',payload:JSON.stringify(e.candidate)}).catch(()=>{}); };
    pc.onconnectionstatechange=()=>{ if(pc.connectionState==='connected') showStatus(''); if(['failed','closed'].includes(pc.connectionState)) cleanup(i18n.call_ended||'Call ended'); };
    state.peer=pc; return pc;
  }
  async function flushCandidates(){ if(!state.peer?.remoteDescription) return; while(state.pendingCandidates.length){ try{ await state.peer.addIceCandidate(state.pendingCandidates.shift()); }catch(e){} } }
  async function processSignals(signals){
    for(const s of signals||[]){
      state.lastSignal=Math.max(state.lastSignal,Number(s.id||0));
      let payload; try{payload=JSON.parse(s.payload);}catch(e){continue;}
      if(s.type==='offer'){
        if(!state.peer) makePeer(); await state.peer.setRemoteDescription(payload); await flushCandidates(); const ans=await state.peer.createAnswer(); await state.peer.setLocalDescription(ans); await post({action:'signal',call_id:state.callId,signal_type:'answer',payload:JSON.stringify(ans)});
      }else if(s.type==='answer'){
        if(state.peer && !state.peer.remoteDescription){ await state.peer.setRemoteDescription(payload); await flushCandidates(); }
      }else if(s.type==='candidate'){
        const c=new RTCIceCandidate(payload); if(state.peer?.remoteDescription) { try{await state.peer.addIceCandidate(c);}catch(e){} } else state.pendingCandidates.push(c);
      }
    }
  }
  async function pollCall(){
    if(!state.callId) return; try{ const j=await post({action:'poll',call_id:state.callId,last_signal_id:state.lastSignal}); await processSignals(j.signals); if(j.status==='accepted'){ state.accepted=true; showStatus(i18n.connecting||'Connecting…'); } if(['declined','ended','missed'].includes(j.status)){ cleanup(j.status==='declined'?(i18n.call_declined||'Call declined'):j.status==='missed'?(i18n.call_missed||'Missed call'):(i18n.call_ended||'Call ended')); } }catch(e){ cleanup(i18n.call_failed||'Call failed'); }
  }
  async function startCall(btn){
    if(state.callId) return; const type=btn.dataset.callType||'voice'; const person={id:Number(btn.dataset.userId),name:btn.dataset.userName||'',avatar:btn.dataset.userAvatar||'assets/img/default-avatar.svg'};
    try{ state.local=await getMedia(type); const j=await post({action:'start',target_id:person.id,call_type:type}); state.callId=j.call_id; state.other=person; showOverlay(person,type); showStatus(i18n.calling||'Calling…'); const pc=makePeer(); const offer=await pc.createOffer(); await pc.setLocalDescription(offer); await post({action:'signal',call_id:state.callId,signal_type:'offer',payload:JSON.stringify(offer)}); state.pollTimer=setInterval(pollCall,900); pollCall(); }catch(e){ cleanup(); alert(e.message||i18n.call_failed||'Call failed'); }
  }
  async function pollIncoming(){ if(state.callId) return; try{ const j=await post({action:'poll'}); if(j.incoming){ incomingCard.hidden=false; incomingCard.dataset.callId=j.incoming.call_id; incomingCard.dataset.callType=j.incoming.call_type; incomingCard.dataset.callerId=j.incoming.caller_id; document.getElementById('incoming-call-name').textContent=j.incoming.name; document.getElementById('incoming-call-avatar').src=j.incoming.avatar; document.getElementById('incoming-call-kind').textContent=j.incoming.call_type==='video'?(i18n.incoming_video_call||'Incoming video call'):(i18n.incoming_voice_call||'Incoming voice call'); } else incomingCard.hidden=true; }catch(e){} }
  async function acceptIncoming(){
    const callId=Number(incomingCard.dataset.callId||0); if(!callId) return; const type=incomingCard.dataset.callType||'voice'; const person={id:Number(incomingCard.dataset.callerId||0),name:document.getElementById('incoming-call-name').textContent,avatar:document.getElementById('incoming-call-avatar').src};
    try{ state.local=await getMedia(type); await post({action:'accept',call_id:callId}); state.callId=callId; state.other=person; incomingCard.hidden=true; showOverlay(person,type); showStatus(i18n.connecting||'Connecting…'); makePeer(); state.pollTimer=setInterval(pollCall,700); await pollCall(); }catch(e){ try{await post({action:'decline',call_id:callId});}catch(_){} cleanup(); alert(e.message||i18n.call_failed||'Call failed'); }
  }
  async function declineIncoming(){ const id=Number(incomingCard.dataset.callId||0); if(id) try{await post({action:'decline',call_id:id});}catch(e){} incomingCard.hidden=true; }
  async function endCurrent(){ const id=state.callId; if(id) try{await post({action:'end',call_id:id});}catch(e){} cleanup(i18n.call_ended||'Call ended'); }

  document.addEventListener('click',e=>{ const b=e.target.closest('.js-call-button'); if(b){ e.preventDefault(); startCall(b); } });
  document.getElementById('accept-call').addEventListener('click',acceptIncoming);
  document.getElementById('decline-call').addEventListener('click',declineIncoming);
  document.getElementById('end-call').addEventListener('click',endCurrent);
  document.getElementById('toggle-mic').addEventListener('click',e=>{ const t=state.local?.getAudioTracks()[0]; if(!t)return; t.enabled=!t.enabled; e.currentTarget.classList.toggle('off',!t.enabled); });
  document.getElementById('toggle-camera').addEventListener('click',e=>{ const t=state.local?.getVideoTracks()[0]; if(!t)return; t.enabled=!t.enabled; e.currentTarget.classList.toggle('off',!t.enabled); });
  state.incomingTimer=setInterval(pollIncoming,1800); pollIncoming();
  window.addEventListener('beforeunload',()=>{ if(state.callId) navigator.sendBeacon?.('api/call-action.php',new URLSearchParams({action:'end',call_id:String(state.callId),csrf_token:csrf})); });
})();
