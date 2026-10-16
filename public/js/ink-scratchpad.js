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
    const localButton = root.querySelector('[data-ink-native-recognize]');
    const localMessage = root.querySelector('[data-ink-native-message]');
    const localResult = root.querySelector('[data-ink-native-result]');
    const localText = root.querySelector('[data-ink-native-text]');
    if (localButton && localMessage && localResult && localText) {
      const available = typeof navigator.createHandwritingRecognizer === 'function' && typeof window.HandwritingStroke === 'function';
      localButton.hidden = !available;
      localMessage.textContent = available
        ? 'Reconhecimento experimental no dispositivo; confirma sempre o resultado.'
        : 'O navegador não disponibiliza reconhecimento manuscrito local. A folha funciona sem serviços externos.';
      localButton.addEventListener('click', async () => {
        localButton.disabled = true;
        try {
          const recognizer = await navigator.createHandwritingRecognizer({ languages: ['en'] });
          const drawing = await recognizer.startDrawing();
          const written = strokes.filter(s => s.color !== '#ffffff');
          if (!written.length) throw new Error('Folha vazia');
          for (const stroke of written) {
            const fragment = new window.HandwritingStroke();
            stroke.points.forEach((p, i) => fragment.addPoint({ x: p.x * 1000, y: p.y * 1000, t: i * 10 }));
            drawing.addStroke(fragment);
          }
          const results = await drawing.getPrediction();
          const text = results?.[0]?.text || '';
          if (!text) throw new Error('Texto não reconhecido');
          localText.value = text;
          localResult.hidden = false;
          localMessage.textContent = 'Confirma a transcrição. Não é uma classificação matemática.';
        } catch (error) {
          localMessage.textContent = 'Reconhecimento local não concluído: ' + error.message;
        } finally {
          localButton.disabled = false;
        }
      });
    }
    const mathButton=root.querySelector('[data-ink-math-recognize]');
    const mathResult=root.querySelector('[data-ink-math-result]');
    const mathText=root.querySelector('[data-ink-math-text]');
    const mathMessage=root.querySelector('[data-ink-math-message]');
    if(mathButton && mathResult && mathText && mathMessage){
      mathButton.addEventListener('click',async()=>{
        if(!strokes.some(s=>s.points.length>1)){
          mathMessage.textContent='Escreve primeiro a expressão na folha.';return;
        }
        mathButton.disabled=true;
        mathMessage.textContent='A reconhecer localmente: no primeiro uso são carregados os modelos para este navegador...';
        try{
          const module=await import('/vendor/ink-math.js');
          const latex=await module.recognizeMath(strokes,root.dataset.inkAnswerType==='numeric');
          if(!latex)throw new Error('Nenhuma expressão foi reconhecida.');
          mathText.value=latex;
          mathResult.hidden=false;
          mathMessage.textContent='Fórmula reconhecida no dispositivo. Confirma os símbolos e introduz o resultado final no campo de resposta.';
          if(expanded)toggle(false);
        }catch(error){
          mathMessage.textContent='Reconhecimento local indisponível: '+error.message+'; a folha continua guardada e pode ser usada normalmente.';
        }finally{mathButton.disabled=false;}
      });
    }
    requestAnimationFrame(paint);
  });
})();