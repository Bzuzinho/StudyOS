# Sincronização Moodle com navegador gerido

O botão em `/moodle/sync` inicia uma sessão isolada no serviço privado `moodle-browser`. O utilizador opera a página real ULO/Microsoft através da janela apresentada no StudyOS e confirma o MFA quando necessário. O serviço deteta a ligação de regresso na página `launch.php`, envia o payload apenas ao servidor Laravel, e a recolha entra automaticamente na fila Moodle. Não requer copiar ligações, extensões ou registo de protocolos.

## Sessões e credenciais

- Contextos Chromium não persistentes, separados por tentativa, com duração máxima de 15 minutos e limite de duas sessões simultâneas.
- O ID do contexto fica na sessão Laravel; nunca é enviado ao cliente. Todas as operações passam pela mesma origem do StudyOS, com CSRF nos pedidos de escrita e bloqueio de sessão para evitar corridas.
- O serviço é privado na rede Railway e exige um segredo Bearer de pelo menos 32 caracteres; não expõe CDP nem endpoints genéricos de navegação/execução.
- O utilizador introduz as credenciais na página institucional renderizada. As teclas atravessam o StudyOS e o serviço de navegador para operar essa página; não são guardadas em formulários, ficheiros, base de dados ou logs. Isto é diferente de autenticar no browser local: a sessão Microsoft existe no navegador gerido.
- Frames e inputs usam no-store. Não se guardam screenshots, gravações, cookies ou storageState. Concluir/cancelar fecha o contexto; expirar também fecha o contexto pelo timer do serviço.
- O payload é validado pelo site/passport com prazo de 15 minutos e consumo único. O token privado é descartado; o token API só entra no job cifrado e não é guardado como segredo permanente da ligação.
- Navegação limitada a HTTPS na instituição e Microsoft; recursos externos adicionais limitados a domínios conhecidos. Entradas permitem apenas rato, roda, texto e teclas de edição.
- A conta continua a ser verificada por MOODLE_EXPECTED_USERNAME antes de importar materiais.

## Publicação

Serviço `moodle-browser` a partir deste repositório, root `services/moodle-browser`, Dockerfile incluído, porta 3000, uma réplica, sem domínio público e sem cron. Variáveis: PORT=3000, MOODLE_BASE_URL, MOODLE_BROWSER_SECRET. O serviço web recebe o mesmo segredo e MOODLE_BROWSER_URL=http://moodle-browser.railway.internal:3000. Configurar apenas em produção, não em ambientes de PR públicos. Não adicionar segredos ao Git.

O serviço sync-moodle mantém `php artisan queue:work moodle --queue=moodle --timeout=1800 --tries=1 --sleep=3`, restart ALWAYS e sem cron. O worker de extração mantém a fila default. Web e workers partilham APP_KEY, Postgres e armazenamento.

## Validação e limites

CI verifica parsing/autorização, restrições de navegação/entrada, isolamento de contextos, screenshot/interação, fecho, entrega automática à fila, expiração/replay e cancelamento. A importação real exige login na conta ULO. Condições de acesso Microsoft que exijam um dispositivo gerido, passkey local ou bloqueiem o navegador remoto podem impedir o login: não se contornam essas condições. O serviço mobile tem de permitir core_webservice_get_site_info e core_course_get_contents.
