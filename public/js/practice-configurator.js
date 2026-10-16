(() => {
    const form = document.getElementById('training-form');
    const label = document.getElementById('training-availability');
    const accept = document.getElementById('accept-available');
    const button = document.getElementById('training-submit');
    let controller;
    const update = async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        const data = new FormData(form);
        if (!data.get('course_id') || !data.getAll('topic_ids[]').length) {
            label.textContent = 'Seleciona a UC e pelo menos um subtema.'; button.disabled = true; accept.hidden = true; return;
        }
        if (!data.getAll('types[]').length) {
            label.textContent = 'Seleciona pelo menos um tipo de pergunta.'; button.disabled = true; return;
        }
        button.disabled = true;
        label.textContent = 'A consultar perguntas disponíveis…';
        try {
            const params = new URLSearchParams();
            for (const [key, value] of data) if (key !== '_token') params.append(key, value);
            const response = await fetch(`${form.dataset.availability}?${params}`, {signal: controller.signal, headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error();
            const result = await response.json();
            const count = Number(result.count);
            const requested = Number(data.get('count'));
            accept.hidden = count === 0 || count >= requested;
            if (accept.hidden) accept.querySelector('input').checked = false;
            if (count === 0 && Number(result.course_total) === 0) {
                label.innerHTML = '';
                label.append(document.createTextNode('Esta UC ainda não tem exercícios importados. '));
                const link = document.createElement('a');
                link.href = result.import_url;
                link.textContent = 'Importar banco de exercícios';
                label.append(link);
            } else if (count === 0 && Number(result.selected_total) === 0) {
                label.textContent = 'Não existem exercícios associados aos subtemas escolhidos. Seleciona outra matéria ou importa exercícios para esta UC.';
            } else if (count === 0) {
                label.textContent = 'Há exercícios para estes subtemas, mas nenhum corresponde aos filtros. Experimenta Todas as dificuldades e todos os tipos de perguntas.';
            } else {
                label.textContent = `${count} perguntas elegíveis${count < requested ? ` — pediste ${requested}; confirma abaixo para usar as disponíveis.` : '.'}`;
            }
            button.disabled = count === 0;
        } catch (error) {
            if (error.name === 'AbortError') return;
            label.textContent = 'Não foi possível consultar a disponibilidade. Ao iniciar, a seleção será validada novamente.';
            button.disabled = false;
        }
    };
    form.querySelector('[name="mode"]').addEventListener('change', event => {
        if (event.target.value === 'quick') form.querySelector('[name="count"]').value = '5';
    });
    form.addEventListener('curriculum-change', update);
    form.addEventListener('change', event => {
        if (!event.target.closest('[data-curriculum-picker]')) update();
    });
    update();
})();
