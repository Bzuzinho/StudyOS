<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sincronizar Moodle · StudyOS</title>
    @include('partials.app-identity')
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
    <style>[hidden] { display: none !important; }</style>
</head>
<body><main class="shell">
    <a href="{{ route('materials.index', [], false) }}">← Materiais</a>
    <p class="eyebrow">Ligação à conta ULO</p>
    <h1>Sincronizar Moodle</h1>
    <p>Autentica-te no Moodle numa nova aba e cola aqui a ligação de regresso para recolher os documentos das 6 UCs.</p>
    <p>A sincronização só acontece quando a pedes. A palavra-passe Microsoft fica no site da instituição.</p>
    <section class="material-card">
        <h2>1. Autenticar no Moodle</h2>
        <form id="sync-form" method="POST" target="_blank" rel="noopener" action="{{ route('moodle.start', [], false) }}">
            @csrf
            <button class="button primary" id="sync" type="submit" @disabled($running)>Autenticar e sincronizar</button>
        </form>
        <p>Mantém esta página aberta. Na nova aba, entra com a conta ULO. Depois, clica com o botão direito em «Clique aqui se a aplicação não abrir automaticamente» e escolhe Copiar ligação. Não precisas de a abrir.</p>
        <p>Se o login terminar noutra página do Moodle, volta aqui e usa o mesmo botão para retomar. A ligação é válida durante 15 minutos desde o início.</p>
    </section>
    <section class="material-card">
        <h2>2. Concluir a ligação</h2>
        <form id="return-form" autocomplete="off">
            <label for="return-link">Ligação copiada do Moodle</label>
            <input id="return-link" type="password" autocomplete="off" spellcheck="false" required placeholder="Cola aqui a ligação copiada" style="width:100%">
            <button class="button primary" id="complete" type="submit" @disabled($running)>Importar documentos</button>
        </form>
        <p>Cola a ligação apenas neste campo. É enviada por ligação segura para validar esta sessão e iniciar a recolha; não fica guardada neste formulário.</p>
        <p id="message" role="status" aria-live="polite"></p>
    </section>
    <section class="material-card">
        <h2>Resultado desta sessão</h2>
        <p id="result" role="status" aria-live="polite">{{ $run ? 'A consultar a sincronização…' : 'Ainda não foi iniciada uma recolha nesta sessão.' }}</p>
        <a class="button" href="{{ route('materials.index', [], false) }}">Ver materiais</a>
    </section>
</main>
<script>
(() => {
    const sync = document.getElementById('sync');
    const complete = document.getElementById('complete');
    const input = document.getElementById('return-link');
    const message = document.getElementById('message');
    let running = @json($running);
    let submitting = false;
    window.addEventListener('pageshow', () => { input.value = ''; });
    window.addEventListener('pagehide', () => { input.value = ''; });
    document.getElementById('sync-form').addEventListener('submit', () => {
        message.textContent = 'Conclui a autenticação na aba do Moodle e cola aqui a ligação de regresso.';
    });
    document.getElementById('return-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitting || running) return;
        const payload = input.value.trim();
        input.value = '';
        if (!/^(?:moodlemobile|web\+studyos):\/\/token=[A-Za-z0-9+/=]+$/.test(payload) || payload.length > 2048) {
            message.textContent = 'Copia a ligação completa usando o botão direito na ligação azul do Moodle.';
            return;
        }
        submitting = true;
        sync.disabled = complete.disabled = true;
        message.textContent = 'A validar a ligação e a iniciar a recolha…';
        try {
            const response = await fetch({{ Illuminate\Support\Js::from(route('moodle.complete', [], false)) }}, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({payload}),
                cache: 'no-store',
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Não foi possível concluir a ligação. Volta a autenticar.');
            location.replace(data.url);
        } catch (_) {
            message.textContent = 'Não foi possível validar a ligação. Atualiza a página, autentica-te e copia a nova ligação do Moodle.';
            submitting = false;
            sync.disabled = complete.disabled = running;
        }
    });
    async function poll() {
        try {
            const response = await fetch({{ Illuminate\Support\Js::from(route('moodle.status', [], false)) }}, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error();
            const data = await response.json();
            running = ['queued', 'running'].includes(data.status);
            sync.disabled = complete.disabled = running || submitting;
            const stats = data.stats || {};
            const labels = {idle: 'Ainda não foi iniciada uma recolha nesta sessão.', queued: 'Pedido na fila de recolha.', running: 'A recolher os documentos do Moodle…', failed: 'A recolha falhou. Confirma a conta ULO e volta a autenticar.'};
            document.getElementById('result').textContent = labels[data.status] ||
                `${data.status === 'success_with_warnings' ? 'Concluída com avisos' : 'Concluída'}: ${stats.courses_scanned || 0} UCs, ${stats.files_seen || 0} ficheiros encontrados, ${stats.versions_created || 0} versões novas/atualizadas, ${stats.unchanged || 0} sem alterações, ${stats.unsupported || 0} formatos não suportados e ${stats.file_errors || 0} erros.`;
        } catch (_) {
            document.getElementById('result').textContent = 'Não foi possível consultar o estado. Se iniciaste uma recolha, ela continua no servidor.';
        }
        if (running) setTimeout(poll, 5000);
    }
    if (@json((bool) $run)) poll();
})();
</script></body></html>
