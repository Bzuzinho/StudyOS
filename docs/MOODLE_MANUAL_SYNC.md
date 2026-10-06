# Sincronização manual do Moodle

O botão em Materiais abre `/moodle/sync`. No Chrome/Edge de computador, o utilizador regista o protocolo `web+studyos` para receber a resposta do fluxo mobile SSO do Moodle. O browser pode pedir confirmação do registo. Não se deve considerar que o registo foi aceite apenas porque a chamada JavaScript retornou.

Cada pedido cria um passport de 15 minutos na sessão. O Moodle abre a autenticação Microsoft/ULO e devolve o token pelo protocolo. O callback recebe-o no fragmento da URL, remove esse fragmento imediatamente e envia-o por POST com CSRF. O servidor verifica o site e o passport, consome a ligação uma única vez e coloca a recolha numa fila cifrada. O token privado é descartado; não se guarda a palavra-passe Microsoft nem um token permanente na ligação. O token emitido pelo Moodle continua sujeito à validade/revogação do próprio Moodle.

O job verifica o username `MOODLE_EXPECTED_USERNAME` antes de alterar materiais. Configurar este valor apenas no ambiente de execução, sem o publicar no repositório; sem esta configuração a recolha é recusada. Também admite o sufixo `@ulo.pt`. As 6 UCs são as correspondências já configuradas em `source_courses`.

## Worker e publicação

- Serviço web e worker Moodle devem usar o mesmo `APP_KEY`, Postgres e configuração de ficheiros.
- Converter o serviço Railway `sync-moodle`, sem cron, para `php artisan queue:work moodle --queue=moodle --timeout=1800 --tries=1 --sleep=3`.
- Restart policy `ALWAYS`. O worker fica à espera de pedidos; não inicia sincronizações periódicas.
- Manter o worker de extração na fila `default`. A conexão `moodle` tem `retry_after=1860`, superior ao timeout, para evitar repetir uma recolha longa enquanto ainda está em execução.
- Não reativar o cron nem configurar `MOODLE_TOKEN` para este fluxo manual.

## Limitações e validação real pendente

A API de protocolos não está disponível em todos os browsers, sobretudo nos telemóveis. A interface indica o requisito antes de iniciar; a recolha no computador alimenta os materiais consultáveis no telemóvel.

O serviço mobile do Moodle precisa estar ativo e permitir `core_webservice_get_site_info` e `core_course_get_contents`. Se o Moodle força outro protocolo, recusa o serviço ou não mantém o wantsurl no SSO, a ligação não se completa. Não há fallback para recolher a palavra-passe ou copiar tokens em chat.

A primeira recolha real só está validada quando o retorno do Moodle chega ao callback e o resultado mostra as UCs e ficheiros encontrados. Ter sessão aberta no browser não demonstra esse resultado.
