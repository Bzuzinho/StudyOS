<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sincronizar Moodle · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
    <style>#launch[hidden] { display: none; }</style>
</head>
<body><main class="shell">
    <a href="{{ route('materials.index', [], false) }}">← Materiais</a>
    <p class="eyebrow">Ligação à conta ULO</p>
    <h1>Sincronizar Moodle</h1>
    <p>Inicia sessão na tua conta ULO. Após a autenticação, regressas ao StudyOS e começa a recolha das 6 UCs.</p>
    <p>A sincronização só acontece quando a pedes. A palavra-passe Microsoft fica no site da instituição.</p>
    <section class="material-card">
        <h2>Primeira ligação neste navegador</h2>
        <p>Usa Chrome ou Edge no computador. Permite que o StudyOS receba o regresso da autenticação quando o navegador perguntar.</p>
        <button class="button" id="register" type="button">Permitir regresso ao StudyOS</button>
        <button class="button primary" id="sync" type="button" disabled>Autenticar e sincronizar</button>
        <p id="message" role="status" aria-live="polite"></p>
        <a class="button primary" id="launch" hidden>Abrir Moodle e continuar</a>
        <p>Se o navegador do telemóvel não suportar esta ligação, inicia a recolha no computador. Os documentos recolhidos ficam disponíveis no telemóvel.</p>
    </section>
    <section class="material-card">
        <h2>Resultado desta sessão</h2>
        <p id="result" role="status" aria-live="polite">{{ $run ? 'A consultar a sincronização…' : 'Ainda não foi iniciada uma recolha nesta sessão.' }}</p>
        <a class="button" href="{{ route('materials.index', [], false) }}">Ver materiais</a>
    </section>
</main>
<script>
(() => {
    const register = document.getElementById('register');
    const sync = document.getElementById('sync');
    const launch = document.getElementById('launch');
    let preparing = false;
    const message = document.getElementById('message');
    let running = @json($running);
    let registered = false;
    if (!window.isSecureContext || !navigator.registerProtocolHandler) {
        register.disabled = true;
        message.textContent = 'Este navegador não permite concluir a ligação. Usa Chrome ou Edge no computador.';
    }
    register.addEventListener('click', () => {
        try {
            navigator.registerProtocolHandler('web+studyos', location.origin + '/moodle/callback#%s');
            registered = true;
            sync.disabled = running;
            message.textContent = 'Aceita o pedido do navegador, se aparecer, e inicia a autenticação. Se recusares, o Moodle não conseguirá regressar ao StudyOS.';
        } catch (_) {
            message.textContent = 'O navegador bloqueou o regresso ao StudyOS. Verifica as permissões ou usa Chrome/Edge no computador.';
        }
    });
    sync.addEventListener('click', async () => {
        if (preparing || running) return;
        preparing = true;
        sync.disabled = true;
        launch.hidden = true;
        launch.removeAttribute('href');
        message.textContent = 'A preparar a ligação…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch({{ Illuminate\Support\Js::from(route('moodle.start', [], false)) }}, {
                signal: controller.signal, method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível iniciar a ligação.');
            const url = new URL(data.url);
            if (url.protocol !== 'https:') throw new Error('Ligação ao Moodle inválida.');
            launch.href = url.href;
            launch.hidden = false;
            message.textContent = 'Ligação pronta. Clica em «Abrir Moodle e continuar». Se não regressares ao StudyOS, confirma que aceitaste o regresso nas permissões do navegador.';
        } catch (error) {
            message.textContent = error.name === 'AbortError' ? 'O servidor demorou demasiado. Volta a tentar preparar a ligação.' : error.message;
        } finally {
            clearTimeout(timeout);
            preparing = false;
            sync.disabled = !registered || running || preparing;
        }
    });
    async function poll() {
        try {
            const response = await fetch({{ Illuminate\Support\Js::from(route('moodle.status', [], false)) }}, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error();
            const data = await response.json();
            running = ['queued', 'running'].includes(data.status);
            sync.disabled = !registered || running || preparing;
            const stats = data.stats || {};
            const labels = {idle: 'Ainda não foi iniciada uma recolha nesta sessão.', queued: 'Pedido na fila de recolha.', running: 'A recolher os documentos do Moodle…', failed: 'A recolha falhou. Confirma a conta ULO e volta a autenticar.'};
            document.getElementById('result').textContent = labels[data.status] ||
                `${data.status === 'success_with_warnings' ? 'Concluída com avisos' : 'Concluída'}: ${stats.courses_scanned || 0} UCs, ${stats.files_seen || 0} ficheiros encontrados, ${stats.versions_created || 0} versões novas/atualizadas, ${stats.unchanged || 0} sem alterações, ${stats.unsupported || 0} formatos não suportados e ${stats.file_errors || 0} erros.`;
        } catch (_) {
            document.getElementById('result').textContent = 'Não foi possível consultar o estado. Se iniciaste uma recolha, ela continua no servidor.';
        }
        setTimeout(poll, 5000);
    }
    poll();
})();
</script></body></html>
