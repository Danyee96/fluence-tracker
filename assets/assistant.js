(function () {
  var form=document.getElementById('assistant-form');if(!form)return;
  var question=document.getElementById('assistant-question'),messages=document.getElementById('assistant-messages'),status=document.getElementById('assistant-status'),button=document.getElementById('assistant-send'),clear=document.getElementById('assistant-clear'),toggle=document.getElementById('assistant-toggle'),panel=document.getElementById('assistant-window'),close=document.getElementById('assistant-close'),history=[];
  // Session storage keeps the chat when navigating; it is scoped to this signed-in identity.
  var storageKey='fl_assistant_'+document.body.dataset.assistantUser;
  function persist(){try{sessionStorage.setItem(storageKey,JSON.stringify({history:history.slice(-12)}));}catch(e){}}
  function welcome(){if(!messages.children.length)messages.append(document.getElementById('assistant-welcome').content.cloneNode(true));}
  function resize(){question.style.height='auto';question.style.height=Math.min(100,question.scrollHeight)+'px';}
  question.addEventListener('input',resize);
  question.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();if(!button.disabled)form.requestSubmit();}});
  messages.addEventListener('click',function(e){var prompt=e.target.closest('[data-prompt]');if(prompt){question.value=prompt.dataset.prompt;resize();question.focus();}});
  function append(role,text){var welcomeState=messages.querySelector('.assistant-welcome');if(welcomeState)welcomeState.remove();var item=document.createElement('div'),label=document.createElement('b'),body=document.createElement('p');item.className='assistant-message '+role;label.textContent=role==='user'?'You':'Grok';body.textContent=text;item.append(label,body);messages.append(item);messages.scrollTop=messages.scrollHeight;return item;}
  function reset(){history=[];messages.replaceChildren();status.textContent='';question.value='';resize();welcome();persist();}
  try{var saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');if(saved&&Array.isArray(saved.history)){messages.replaceChildren();history=saved.history.filter(function(m){return m&&['user','assistant'].includes(m.role)&&typeof m.content==='string';}).slice(-12);history.forEach(function(m){append(m.role,m.content);});}}catch(e){}
  welcome();
  function openChat(open){panel.hidden=!open;toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close Grok assistant':'Open Grok assistant');if(open)question.focus();else toggle.focus();}
  toggle.addEventListener('click',function(){openChat(panel.hidden);});close.addEventListener('click',function(){openChat(false);});panel.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();e.stopPropagation();openChat(false);}});
  if(location.hash==='#assistant')openChat(true);
  clear.addEventListener('click',reset);
  form.addEventListener('submit',async function(event){event.preventDefault();var text=question.value.trim();if(!text)return;button.disabled=clear.disabled=true;panel.setAttribute('aria-busy','true');status.textContent='Reading project data…';append('user',text);
    try{var response=await fetch('assistant-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':document.querySelector('meta[name=csrf]').content},body:JSON.stringify({question:text,history:history.slice(-6)})});var data=await response.json();if(!data.ok)throw Error(data.error||'Unable to answer.');append('assistant',data.answer);history.push({role:'user',content:text},{role:'assistant',content:data.answer});persist();question.value='';resize();status.textContent='Updated '+new Date(data.captured_at).toLocaleTimeString();}catch(error){status.textContent=error.message;}finally{button.disabled=clear.disabled=false;panel.setAttribute('aria-busy','false');if(!panel.hidden)question.focus();}
  });
})();
