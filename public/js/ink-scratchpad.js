(() => {
  'use strict';
  document.querySelectorAll('[data-ink-pad]').forEach((root) => {
    const canvas = root.querySelector('canvas');
    const ctx = canvas.getContext('2d');
    const key = root.dataset.storageKey;
    const open = root.querySelector('[data-ink-expand]');
    const close = root.querySelector('[data-ink-close]');
    const clear = root.querySelector('[data-ink-clear]');
    const undo = root.querySelector('[data-ink-undo]');
    const status = root.querySelector('[data-ink-status]');
    let strokes = [], active = null, expanded = false;
    let restoring = false;
    const point = (e) => {
      const rect = canvas.getBoundingClientRect();
      return {x:(e.clientX-rect.left)/rect.width,y:(e.clientY-rect.top)/rect.height};
    };
    const paint = () => {
      const rect = canvas.getBoundingClientRect();
      const width = Math.max(1,Math.round(rect.width*devicePixelRatio));
      const height = Math.max(1,Math.round(rect.height*devicePixelRatio));
      if(canvas.width!==width || canvas.height!==height){canvas.width=width;canvas.height=height;}
      ctx.setTransform(width,0,0,height,0,0);
      ctx.clearRect(0,0,1,1); ctx.lineCap='round';ctx.lineJoin='round';
      for(const stroke of strokes.concat(active?[active]:[])){
        if(!stroke.points.length)continue;
        ctx.strokeStyle=stroke.color;ctx.lineWidth=stroke.width/Math.max(rect.width,1);
        ctx.beginPath();ctx.moveTo(stroke.points[0].x,stroke.points[0].y);
        if(stroke.points.length===1){const p=stroke.points[0];ctx.lineTo(p.x+.00001,p.y+.00001);}
        else stroke.points.slice(1).forEach(p=>ctx.lineTo(p.x,p.y));
        ctx.stroke();
      }
    };
    const save = () => {try {localStorage.setItem(key,JSON.stringify(strokes));status.textContent='Rascunho guardado neste dispositivo';} catch(e){status.textContent='Sem espaço para guardar; não feches esta página';}};
    try {const saved=JSON.parse(localStorage.getItem(key)||'[]');if(Array.isArray(saved))strokes=saved.filter(s=>Array.isArray(s.points));}catch(e){}
    canvas.addEventListener('pointerdown',e=>{
      if(e.pointerType==='touch' && e.width>25)return;
      e.preventDefault();canvas.setPointerCapture(e.pointerId);
      const eraser = e.pointerType==='pen' && (e.button===5 || (e.buttons&32)!==0);
      active={pointerId:e.pointerId,color:eraser?'#ffffff':'#15202b',width:eraser?20:2.8,points:[point(e)]};paint();
    });
    canvas.addEventListener('pointermove',e=>{
      if(!active||active.pointerId!==e.pointerId)return;
      e.preventDefault();active.points.push(point(e));paint();
    });
    const finish=e=>{
      if(!active||active.pointerId!==e.pointerId)return;
      strokes.push(active);active=null;paint();save();
    };
    canvas.addEventListener('pointerup',finish);
    canvas.addEventListener('pointercancel',finish);
    undo.addEventListener('click',()=>{strokes.pop();paint();save();});
    clear.addEventListener('click',()=>{if(strokes.length && !confirm('Apagar o rascunho deste exercício?'))return;strokes=[];paint();save();});
    const toggle=(value)=>{
      expanded=value;root.classList.toggle('ink-expanded',value);
      document.body.classList.toggle('ink-body-expanded',value);
      open.hidden=value;close.hidden=!value;
      close.setAttribute('aria-expanded',String(value));open.setAttribute('aria-expanded',String(value));
      requestAnimationFrame(paint);
    };
    open.addEventListener('click',()=>toggle(true));
    close.addEventListener('click',()=>toggle(false));
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&expanded)toggle(false);});
    if(window.ResizeObserver)new ResizeObserver(()=>{if(!restoring)requestAnimationFrame(paint);}).observe(canvas);
    const recognize = root.querySelector('[data-ink-recognize]');
    const review = root.querySelector('[data-ink-review]');
    const transcription = root.querySelector('[data-ink-transcription]');
    const notes = root.querySelector('[data-ink-notes]');
    const use = root.querySelector('[data-ink-use]');
    if (recognize && review && transcription && notes && use) {
      recognize.addEventListener('click', async () => {
        if(!strokes.length){status.textContent='Escreve primeiro a resolução na folha.';return;}
        recognize.disabled=true;status.textContent='A reconhecer a escrita; aguarda...';
        try {
          paint();
          const offscreen=document.createElement('canvas');
          offscreen.width=canvas.width;offscreen.height=canvas.height;
          const out=offscreen.getContext('2d');
          out.fillStyle='#ffffff';out.fillRect(0,0,offscreen.width,offscreen.height);
          out.drawImage(canvas,0,0);
          const form=document.querySelector('form[action*="/responder"]');
          const token=form?.querySelector('input[name="_token"]')?.value;
          const response=await fetch(root.dataset.recognizeUrl,{
            method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},
            credentials:'same-origin',
            body:JSON.stringify({question_id:Number(root.dataset.questionId),image:offscreen.toDataURL('image/png')})
          });
          const result=await response.json();
          if(!response.ok)throw new Error(result.message||'Falha no reconhecimento');
          transcription.value=result.transcription||'';
          notes.textContent=(result.uncertain?'Atenção: reconhecimento incerto. ':'')+(result.notes||'');
          review.hidden=false;
          status.textContent='Transcrição recebida. Confirma os símbolos antes de usar.';
          if(expanded)toggle(false);
        }catch(e){status.textContent='Não foi possível reconhecer: '+e.message;}
        finally{recognize.disabled=false;}
      });
      const evaluate=root.querySelector('[data-ink-evaluate]');
      const feedback=root.querySelector('[data-ink-feedback]');
      if(evaluate && feedback){
        evaluate.addEventListener('click',async()=>{
          if(!transcription.value.trim()){feedback.textContent='Confirma primeiro a transcrição do manuscrito.';return;}
          evaluate.disabled=true;feedback.textContent='A analisar os passos. Esta avaliação é provisória...';
          try{
            const form=document.querySelector('form[action*="/responder"]');
            const token=form?.querySelector('input[name="_token"]')?.value;
            const response=await fetch(root.dataset.reviewUrl,{
              method:'POST',credentials:'same-origin',
              headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},
              body:JSON.stringify({question_id:Number(root.dataset.questionId),transcription:transcription.value})
            });
            const result=await response.json();
            if(!response.ok)throw new Error(result.message||'Não foi possível analisar os passos');
            feedback.textContent='Avaliação provisória ('+result.provisional_percent+'%): '+result.feedback+' Esta análise não conta para o domínio.';
          }catch(e){feedback.textContent=e.message;}
          finally{evaluate.disabled=false;}
        });
      }
      use.addEventListener('click',()=>{
        const answer=document.querySelector('form[action*="/responder"] [name="answer"]');
        if(!answer){status.textContent='Campo de resposta não encontrado.';return;}
        if(root.dataset.inkAnswerType==='numeric'){
          status.textContent='Confirma o valor final no campo numérico; os passos reconhecidos não são pontuados automaticamente.';
          answer.focus();return;
        }
        if(answer.tagName==='SELECT'){
          status.textContent='Seleciona Verdadeiro ou Falso no campo de resposta.';answer.focus();return;
        }
        answer.value=transcription.value;answer.dispatchEvent(new Event('input',{bubbles:true}));
        status.textContent='Transcrição colocada na resposta. Revê antes de submeter.';
        answer.focus();
      });
    }
    requestAnimationFrame(paint);
  });
})();