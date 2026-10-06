<!doctype html>
<html lang="pt"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sincronizar Moodle · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
    <style>[hidden]{display:none!important} #auth-screen{display:block;width:100%;height:auto;background:#fff;outline:2px solid #4a6b7a;border-radius:8px;touch-action:none} #auth-screen:focus{outline-color:#b8a7ff} .auth-origin{overflow-wrap:anywhere;font-family:monospace}</style>
</head><body><main class="shell">
    <a href="{{ route('materials.index', [], false) }}">← Materiais</a>
    <p class="eyebrow">Ligação à conta ULO</p>
    <h1>Sincronizar Moodle</h1>
    <p>Abre a janela abaixo, entra com a conta ULO e confirma o MFA se for pedido. A recolha dos documentos das 6 UCs começa automaticamente depois da autenticação.</p>
    <section class="material-card">
        <button class="button primary" id="sync" type="button" @disabled($running)>Autenticar e sincronizar</button>
        <button class="button" id="cancel" type="button" hidden>Cancelar ligação</button>
        <p id="message" role="status" aria-live="polite">{{ $running ? 'Há uma recolha em curso.' : 'A sincronização só acontece quando a pedes.' }}</p>
        <div id="auth-window" hidden>
            <p>Estás a operar um navegador isolado do StudyOS. Introduz as credenciais apenas na página da instituição ou Microsoft apresentada nesta janela. A sessão é encerrada ao concluir, cancelar ou expirar.</p>
            <p class="auth-origin" id="auth-origin"></p>
            <canvas id="auth-screen" width="1280" height="800" tabindex="0" aria-label="Janela de autenticação ULO. Clica num campo e usa o teclado."></canvas>
            <p>Clica no campo que queres preencher e escreve normalmente. Podes colar texto e usar Tab para avançar entre campos.</p>
        </div>
    </section>
    <section class="material-card">
        <h2>Resultado desta sessão</h2>
        <p id="result" role="status" aria-live="polite">{{ $run ? 'A consultar a sincronização…' : 'Ainda não foi iniciada uma recolha nesta sessão.' }}</p>
        <a class="button" href="{{ route('materials.index', [], false) }}">Ver materiais</a>
    </section>
</main><script>
(() => {
    const urls = {
        start: {{ Illuminate\Support\Js::from(route('moodle.browser.start', [], false)) }},
        status: {{ Illuminate\Support\Js::from(route('moodle.browser.status', [], false)) }},
        frame: {{ Illuminate\Support\Js::from(route('moodle.browser.frame', [], false)) }},
        input: {{ Illuminate\Support\Js::from(route('moodle.browser.input', [], false)) }},
        cancel: {{ Illuminate\Support\Js::from(route('moodle.browser.cancel', [], false)) }},
        run: {{ Illuminate\Support\Js::from(route('moodle.status', [], false)) }}
    };
    const sync = document.getElementById('sync');
    const cancel = document.getElementById('cancel');
    const windowBox = document.getElementById('auth-window');
    const canvas = document.getElementById('auth-screen');
    const context = canvas.getContext('2d');
    const message = document.getElementById('message');
    let authenticating = @json($authenticating);
    let running = @json($running);
    let csrf = document.querySelector('meta[name="csrf-token"]').content;
    let inputChain = Promise.resolve();
    let epoch = 0;
    let frameFailures = 0;
    async function post(url, body = {}) {
        const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body), cache:'no-store'});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Não foi possível concluir a operação.');
        if (data.csrf_token) csrf = data.csrf_token;
        return data;
    }
    function showAuth(show) {
        authenticating = show;
        windowBox.hidden = cancel.hidden = !show;
        sync.disabled = show || running;
        if (!show) context.clearRect(0,0,1280,800);
    }
    function sendInput(data) {
        if (!authenticating) return;
        const generation = epoch;
        inputChain = inputChain.then(async () => {
            if (authenticating && generation === epoch) await post(urls.input,data);
        }).catch(() => { message.textContent = 'A ação não chegou à janela. Volta a clicar no campo ou reinicia a ligação.'; });
    }
    canvas.addEventListener('pointerdown', event => {
        event.preventDefault(); canvas.focus();
        const rect = canvas.getBoundingClientRect();
        sendInput({type:'click',x:(event.clientX-rect.left)*1280/rect.width,y:(event.clientY-rect.top)*800/rect.height});
    });
    canvas.addEventListener('wheel', event => { event.preventDefault(); sendInput({type:'wheel',deltaY:event.deltaY}); },{passive:false});
    canvas.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'v') return;
        event.preventDefault();
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') return sendInput({type:'key',key:'Control+A'});
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        if (event.key.length === 1) return sendInput({type:'text',text:event.key});
        if (['Backspace','Delete','Tab','Enter','Escape','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(event.key)) sendInput({type:'key',key:event.shiftKey && event.key === 'Tab' ? 'Shift+Tab' : event.key});
    });
    canvas.addEventListener('paste', event => { event.preventDefault(); const text = event.clipboardData.getData('text/plain'); if (text) sendInput({type:'text',text}); });
    async function frames(generation) {
        if (!authenticating || generation !== epoch) return;
        try {
            const response = await fetch(urls.frame,{cache:'no-store'});
            if (!response.ok) throw new Error();
            const blob = await response.blob();
            const bitmap = await createImageBitmap(blob);
            if (authenticating && generation === epoch) context.drawImage(bitmap,0,0,1280,800);
            bitmap.close(); frameFailures = 0;
        } catch (_) {
            if (++frameFailures >= 3) message.textContent = 'A aguardar a janela de autenticação…';
        }
        if (authenticating && generation === epoch) setTimeout(() => frames(generation),800);
    }
    async function authStatus(generation) {
        if (!authenticating || generation !== epoch) return;
        try {
            const response = await fetch(urls.status,{headers:{'Accept':'application/json'},cache:'no-store'});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'A ligação não foi concluída.');
            if (data.url) { showAuth(false); return location.replace(data.url); }
            if (data.status !== 'authenticating') throw new Error('A sessão terminou. Volta a iniciar a ligação.');
            document.getElementById('auth-origin').textContent = data.origin && data.origin !== 'null' ? 'Página apresentada: '+data.origin : 'A abrir a página da instituição…';
        } catch (error) { epoch++; showAuth(false); message.textContent=error.message; return; }
        if (authenticating && generation === epoch) setTimeout(() => authStatus(generation),2000);
    }
    function resume() { showAuth(true); const generation=++epoch; frames(generation); authStatus(generation); }
    sync.addEventListener('click',async () => {
        sync.disabled=true; message.textContent='A abrir a janela de autenticação…';
        try { await post(urls.start); message.textContent='Entra na conta ULO nesta janela. Depois, a recolha continua automaticamente.'; resume(); }
        catch(error) { sync.disabled=running; message.textContent=error.message; }
    });
    cancel.addEventListener('click',async () => {
        cancel.disabled=true;
        try { await post(urls.cancel); epoch++; showAuth(false); message.textContent='Ligação cancelada.'; }
        catch(error) { message.textContent=error.message; }
        finally { cancel.disabled=false; }
    });
    async function pollRun() {
        try {
            const response=await fetch(urls.run,{headers:{'Accept':'application/json'},cache:'no-store'});
            if (!response.ok) throw new Error();
            const data=await response.json(); running=['queued','running'].includes(data.status); sync.disabled=running||authenticating;
            if (!authenticating) message.textContent = running ? 'Há uma recolha em curso.' : ({success:'Recolha concluída.',success_with_warnings:'Recolha concluída com avisos.',failed:'A recolha terminou com erro.'}[data.status] || 'A sincronização só acontece quando a pedes.');
            const stats=data.stats||{};
            const labels={idle:'Ainda não foi iniciada uma recolha nesta sessão.',queued:'Pedido na fila de recolha.',running:'A recolher os documentos do Moodle…',failed:'A recolha falhou. Confirma a conta ULO e volta a autenticar.'};
            document.getElementById('result').textContent=labels[data.status]||`${data.status==='success_with_warnings'?'Concluída com avisos':'Concluída'}: ${stats.courses_scanned||0} UCs, ${stats.files_seen||0} ficheiros encontrados, ${stats.versions_created||0} versões novas/atualizadas, ${stats.unchanged||0} sem alterações, ${stats.unsupported||0} ignorados por formato e ${stats.file_errors||0} erros.`;
        } catch (_) { document.getElementById('result').textContent='Não foi possível consultar o estado. A recolha iniciada continua no servidor.'; }
        if (running) setTimeout(pollRun,5000);
    }
    if (authenticating) resume();
    if (@json((bool) $run)) pollRun();
})();
</script></body></html>
