"Você é o Engenheiro de Software Sênior líder deste projeto.
Leia atentamente os 3 arquivos de especificação contidos no repositório antes de realizar qualquer ação:
1. `docs/ARCHITECTURE.md` (Visão do produto, convenções de código PSR-12, padrões arquiteturais, stack e estrutura do monorepo).
2. `docs/DOMAIN_SPEC.md` (Modelagem relacional PostgreSQL, isolamento de schemas, migrations, enums e regras de domínio).
3. `docs/WORKFLOWS.md` (Filas, WebSockets, integrações de mensageria, webhooks, auth Sanctum e portais).


**Diretrizes obrigatórias de execução:**
* Todos os códigos PHP devem seguir rigorosamente o PSR-12, ter `declare(strict_types=1);`, tipagem estrita total, lógica sem `else` (*Early Return*) e classes finais.
* Nenhum código ou comentário deve ser escrito em português; todo o código e anotações técnicas devem ser estritamente em **inglês**.
* Datas e horários transacionais devem ser armazenados exclusivamente em **UTC**.
* Isole estritamente as responsabilidades entre schema central (`public`) e schema do tenant (`tenant_{uuid}`).


Confirme que leu e compreendeu integralmente os documentos resumindo a stack e a ordem de execução do primeiro módulo (Setup do Monorepo e Schemas). Não gere código ainda até que eu aprove seu plano de início."


### Resumo dos 3 Documentos:
1. `docs/ARCHITECTURE.md`: Stack, monorepo, convenções PSR-12, padrões de código e invariantes.
2. `docs/DOMAIN_SPEC.md`: Modelagem relacional completa, schemas central e tenant, migrations, enums e locks.
3. `docs/WORKFLOWS.md`: Filas, mensageria assíncrona, webhooks, WebSockets, fila de espera e autenticação passwordless.

-----

### Organixação em tarefas
Pode organizar as tarefas ou planos em arquivos conforme descrito no arquivo `tasks/README.md`.

Planos são planejamento macro não desmembradas ou com controle de tarefas, enquanto que as tarefas são as definições diretas em si das ações a serem executadas.

---
### Code Guidelines and Code Style Rules
Em toda a execução respeite code guidelines descritas em `UNIVERSAL-CODE-STYLE-RULES.md`
