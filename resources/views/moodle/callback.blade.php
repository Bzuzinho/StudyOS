<!doctype html>
<html lang="pt"><head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>A iniciar recolha · StudyOS</title>
    <script>
        // Capture and immediately remove the secret fragment from the address/history.
        const moodleCallbackFragment = location.hash.slice(1);
        history.replaceState(null, '', '/moodle/callback');
    </script>
    <link rel="stylesheet" href="/css/app.css?v=brand-1">
</head><body><main class="shell">
    <h1>Ligação ao Moodle</h1>
    <p id="message" role="status">A validar a ligação e a iniciar a recolha…</p>
    <a class="button" href="{{ route('moodle.sync', [], false) }}">Voltar à sincronização</a>
</main><script>
(async () => {
    try {
        if (!moodleCallbackFragment) throw new Error('A resposta do Moodle não chegou. Volta a iniciar a ligação.');
        const payload = decodeURIComponent(moodleCallbackFragment);
        const response = await fetch({{ Illuminate\Support\Js::from(route('moodle.complete', [], false)) }}, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({payload}),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Não foi possível concluir a ligação.');
        location.replace(data.url);
    } catch (error) {
        document.getElementById('message').textContent = error instanceof URIError ? 'Resposta de autenticação inválida.' : error.message;
    }
})();
</script></body></html>
