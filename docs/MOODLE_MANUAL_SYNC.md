# Sincronização manual do Moodle

O botão em Materiais abre `/moodle/sync`. A autenticação abre numa nova aba, mantendo o StudyOS aberto. Depois de entrar com a conta ULO, o utilizador copia a ligação «Clique aqui se a aplicação não abrir automaticamente» com o botão direito e cola-a no campo privado do StudyOS. Não é necessário abrir `moodlemobile://`, instalar extensões ou registar protocolos no navegador.

Este passo manual é necessário porque o Moodle observado devolve `moodlemobile://` em vez do protocolo web solicitado anteriormente. Não se apresenta o fluxo como um regresso automático.

Cada pedido cria um passport de 15 minutos na sessão. Retomar preserva o passport e a expiração originais. A migração do identificador de sessão mantém o CSRF da página aberta. O servidor aceita apenas os esquemas moodlemobile e web+studyos, verifica o site e passport no payload, consome a ligação uma única vez e coloca a recolha numa fila cifrada. A ligação é enviada por POST com CSRF, nunca pela query string. O campo é limpo antes do pedido e ao sair/entrar na página; não há persistência no browser nem logs do payload. O token privado é descartado. Não se guarda a palavra-passe Microsoft nem um token permanente na ligação. O token emitido continua sujeito à validade/revogação do Moodle.

O job verifica `MOODLE_EXPECTED_USERNAME` antes de alterar materiais. Configurar apenas no ambiente de execução; sem este valor a recolha é recusada. Também admite o sufixo @ulo.pt. As 6 UCs são as correspondências configuradas em source_courses.

## Worker

- Web e worker usam o mesmo APP_KEY, Postgres e armazenamento.
- Serviço sync-moodle, sem cron: `php artisan queue:work moodle --queue=moodle --timeout=1800 --tries=1 --sleep=3`, restart ALWAYS.
- Manter o worker de extração na fila default e retry_after=1860 na conexão moodle.
- Não reativar recolhas periódicas nem configurar MOODLE_TOKEN para este fluxo.

## Validação

O serviço mobile precisa permitir core_webservice_get_site_info e core_course_get_contents. A primeira recolha real só fica validada quando o resultado indicar as UCs e ficheiros encontrados. Os testes cobrem parsing, isolamento de sessão, expiração, consumo único, CSRF preservado e fila cifrada; não substituem essa recolha.
