# Plugin "Lock Ticket Fields (Titulo, Descricao, Acompanhamento)" — Guia de Instalação (GLPI 11)

## O que este plugin faz

Bloqueia, de forma **configurável**, apenas a **edição** de quatro coisas do chamado:

- **Título** (`name`) e **Descrição** (`content`) de chamados **já criados**;
- **"Por"** do chamado — o campo `users_id_recipient` (criador/solicitante
  primário do chamado);
- **Acompanhamento** já registrado (`ITILFollowup`): a **edição** é bloqueada —
  tanto manualmente (interface) quanto por processos automáticos (cron/CLI).

**Não é bloqueado** (importante):

- O **preenchimento** de Título/Descrição/"Por" na **criação** de um novo chamado;
- A **adição de novos acompanhamentos** — pela interface ou vinda de **e-mail**
  (a resposta do requerente que o `mailcollector` transforma em acompanhamento
  continua funcionando). Ou seja: *o problema não é receber acompanhamento
  novo, é editar o que já está registrado*;
- Os **atores** do chamado — em especial o **requerente/solicitante** (quem
  solicitou), que **pode ser diferente do criador** e, portanto, continua
  **editável**; observadores, responsáveis e fornecedores também.

A diferença crucial para o mecanismo nativo do GLPI (desmarcar o direito
"update" em Assistência → Chamados): aqui o botão **"Editar" continua
disponível** e os demais campos (**categoria, localização, responsáveis,
urgência, observadores, requerente, etc.**) continuam sendo editáveis. Ao
salvar, apenas os campos protegidos são revertidos — exatamente o comportamento
que a equipe de infra obtinha antes com o bloqueio manual no banco de dados, mas
feito de forma limpa, dentro do GLPI e sem mexer no banco.

> Requisito: GLPI **11.0.0 ou superior** (validado hoje contra a imagem oficial
> `glpi/glpi:11.0.8` em Docker).

---

## Nota sobre os nomes das funções no GLPI 11

No GLPI 11, as funções de manutenção do plugin seguem a convenção
**`plugin_<chave>_<ação>`** (a chave vem ANTES da ação):

| Presente neste plugin | O que o GLPI 11 chama |
|---|---|
| `plugin_fieldlock_install()` | ao clicar em **Instalar** |
| `plugin_fieldlock_uninstall()` | ao clicar em **Desinstalar** |
| `plugin_fieldlock_check_prerequisites()` | para validar pré-requisitos |
| `plugin_init_fieldlock()` | em toda requisição |
| `plugin_version_fieldlock()` | para exibir nome/versão |

> ⚠️ A convenção antiga (`plugin_install_<chave>`) **não funciona** no GLPI 11:
> a instalação falha silenciosamente com "Plug-in ... não foi instalado!" e o
> log mostra `Função "..._install" não encontrada`.

---

## Impacto no banco de dados (para a equipe de infra)

- **Nenhuma tabela é criada** e nenhum schema é alterado (`SHOW TABLES LIKE
  'glpi_plugin%'` retorna vazio).
- Na **instalação**, o plugin grava apenas **3 linhas em `glpi_configs`**
  (contexto `fieldlock`: `protect_title`, `protect_description`,
  `protect_followup_edit`) — o mesmo mecanismo usado por configurações do
  núcleo. A **desinstalação** apaga essas 3 linhas.
- Em **operação normal** (toda edição de chamado/acompanhamento) o plugin
  apenas **lê** (`SELECT` em `glpi_configs`) e **reverte** os campos protegidos.
  Ele não insere, altera nem apaga dados de chamados por conta própria.

---

## Passo 1 — Backup preventivo

```bash
mysqldump -u root -p glpi > /tmp/backup_antes_plugin_fieldlock.sql
```

---

## Passo 2 — Colocar o plugin na pasta de plugins do GLPI

O GLPI só reconhece plugins dentro de `plugins/<chave>/` (o nome da pasta DEVE
ser igual à chave do plugin, minúsculo: `fieldlock`), dentro do **webroot do
GLPI**.

⚠️ **Webroot varia conforme a instalação.** Em servidores tradicionais
(Apache/nginx manuais) normalmente é `/var/www/html/glpi`. Na **imagem Docker
oficial (`glpi/glpi`)** o webroot é `/var/www/glpi` — e **não** `/var/www/html`
(que existe, mas é ignorado). Confirme com `docker inspect <container>` ou o
compose.

Estrutura esperada ao final:

```text
<WEBROOT>/plugins/fieldlock/
├── setup.php
├── hook.php
├── plugin.xml
├── front/
│   ├── config.php        (tela de configuração)
│   └── config.form.php   (salva a configuração)
└── locales/
    └── pt_BR.php
```

### Instalação em servidor tradicional (SCP)

```bash
scp -r fieldlock/ usuario@servidor:<WEBROOT>/plugins/
```

Isso cria `<WEBROOT>/plugins/fieldlock/`. ⚠️ **Não** copie o conteúdo com
`fieldlock/.` para dentro de `plugins/` — isso deixa `setup.php`, `front/`, etc.
**soltos** na raiz de `plugins/` e o plugin (e todos os outros) deixam de ser
reconhecidos.

### Instalação em Docker (compose/`docker cp`)

```bash
# copia a PASTA (cria plugins/fieldlock/)
docker cp ~/fieldlock glpi_web:/var/www/glpi/plugins/
```

⚠️ `docker cp ~/fieldlock/. glpi_web:/var/www/glpi/plugins/` esvazia o conteúdo
na raiz de `plugins/` — **errado** (mesmo problema do SCP acima). Use o comando
SEM `/.` no final.

---

## Passo 3 — Ajustar permissões de arquivo

O usuário do servidor web (geralmente `www-data`) precisa LER tudo e EXECUTAR
as pastas. Se os arquivos foram copiados como `root`/`uid 1000` (ex.: via
`docker cp`), corrija:

```bash
cd <WEBROOT>/plugins
chown -R www-data:www-data fieldlock/        # usuário do seu web server
chmod -R u+rwX,go+rX fieldlock/
```

No Docker use `docker exec`:

```bash
docker exec -u 0 glpi_web sh -c 'chown -R www-data:www-data /var/www/glpi/plugins/fieldlock && chmod -R u+rwX,go+rX /var/www/glpi/plugins/fieldlock'
```

> Sem isso, o GLPI não consegue **ler** a pasta e o plugin pode nem aparecer na
> lista (pastas `drwx------` são ilegíveis pelo web server).

---

## Passo 4 — Instalar e ativar o plugin

### Pela interface web

1. Acesse o GLPI como administrador (Super-Admin).
2. **Configuração → Plugins**.
3. Procure **"Fields Lock"**.
4. Clique em **"Instalar"** e depois em **"Ativar"**.

A tela de Plugins executa um *scan* automático das pastas de plugins
(`checkStates()`); se acabou de copiar os arquivos e o plugin não apareceu,
reabra a página (ou rode o scan via CLI, abaixo).

### Via CLI (consola do GLPI)

```bash
cd <WEBROOT>
php bin/console glpi:plugin:install fieldlock
php bin/console glpi:plugin:activate fieldlock
```

No Docker:

```bash
docker exec glpi_web php /var/www/glpi/bin/console glpi:plugin:install fieldlock
docker exec glpi_web php /var/www/glpi/bin/console glpi:plugin:activate fieldlock
```

> `glpi:plugin:list` mostra apenas plugins **já instalados** no banco — não o
> use para "testar se o GLPI enxerga o plugin" (o scan do web é quem faz isso).

---

## Passo 5 — Configurar o que é protegido

1. **Configuração → Plugins** → na linha do plugin, clique no botão
   **"Configurar"** (ou acesse diretamente
   `http://SEU_GLPI/plugins/fieldlock/front/config.php`).
2. **Proteger Título do chamado** — Sim/Não (padrão **Sim**).
3. **Proteger Descrição do chamado** — Sim/Não (padrão **Sim**).
4. **Bloquear edição de Acompanhamento** — Sim/Não (padrão **Sim**). Bloqueia
   apenas a **edição** de acompanhamentos já registrados (manual ou
   automática). **Novos acompanhamentos continuam permitidos** (interface ou
   e-mail).

> O campo **"Por"** (o `users_id_recipient` — criador/solicitante primário) é
> sempre protegido (não tem opção): ele não pode ser alterado na edição. Por
> outro lado, o **ator requerente/solicitante** é **editável** — o requerente
> pode ser diferente do criador do chamado. Observadores, responsáveis e
> fornecedores também continuam livres.

Desmarcar uma opção libera aquele campo. As alterações valem na hora, sem
reiniciar nada. Elas precisam de permissão de configurar o GLPI.

---

## Passo 6 — Testar

1. Em um chamado, clique em **Editar**: o botão continua disponível.
2. Altere **Título** e **Descrição** e salve, junto com uma **categoria** e um
   **técnico atribuído**:
   - Título e Descrição **voltam ao valor anterior** (mensagem informa);
   - **Categoria, localização e responsáveis são salvos** normalmente.
3. Altere o **"Por"** (campo criador/solicitante primário) e salve → o "Por"
   **permanece o mesmo** (mensagem informa).
4. Altere o **ator requerente/solicitante** e salve → o requerente **é salvo
   normalmente** (ele pode ser diferente do criador do chamado).
5. **Adicione um acompanhamento manualmente** → **permitido**.
6. Tente **editar o texto** de um acompanhamento existente → o texto volta ao
   original (mensagem informa).
7. **E-mail → acompanhamento** (se você usa): envie a resposta do requerente
   no mesmo assunto/chamado → o acompanhamento **é criado normalmente**.
8. **Crie um chamado novo** → Título, Descrição e "Por" são preenchidos
   normalmente (a proteção vale só para edição).
9. Na configuração, desative as proteções uma a uma e repita os testes.

---

## Fluxo de e-mail (novo chamado / resposta vira acompanhamento)

O `mailcollector` cria o acompanhamento da resposta chamando
`ITILFollowup::add()`.
**Este plugin não intercepta a adição de acompanhamentos** — não há hook de
add registrado para o `ITILFollowup`. Portanto:

- Resposta de e-mail → **novo acompanhamento**: permitido normalmente;
- Se algum processo automático tentar **editar** um acompanhamento já
  existente (cron/CLI), essa edição também é **revertida** pelo plugin
  (a proteção de edição vale para manual e automático).

---

## Comportamentos e limites conhecidos

- **Ao criar** o chamado, Título, Descrição e "Por" continuam livres (valor
  obrigatório no caso de título/descrição) — o bloqueio vale para **edições
  posteriores**.
- **Novos acompanhamentos** (interface ou e-mail) não sofrem bloqueio;
  apenas a **edição** dos já registrados é bloqueada.
- O campo **"Por"** (`users_id_recipient` — criador/solicitante primário) não
  pode ser alterado na edição; já o **ator requerente/solicitante** e os demais
  atores (observadores, responsáveis, fornecedores) seguem editáveis.
- Fluxos automáticos que alteram **título/descrição/"Por"** ou que **editam
  acompanhamentos** também são revertidos.
- Este plugin cobre **Título, Descrição, "Por" e Acompanhamento**, no escopo
  de **chamados** (Tickets). Não cobre **Soluções** nem **Tarefas**.

---

## Troubleshooting

| Sintoma | Causa provável | Correção |
|---|---|---|
| Plugin **não aparece** em Configuração → Plugins | Pasta errada (webroot incorreto) ou arquivos soltos na raiz de `plugins/` | Refaça o Passo 2 colocando `plugins/fieldlock/` no webroot certo |
| Plugin não aparece (mesmo no lugar certo) | Permissão ilegível pelo web server | Refaça o Passo 3 (`chown` + `chmod`) |
| "Plug-in ... **não foi instalado!**" / log: `Função "..._install" não encontrada` | Nomes no padrão antigo `plugin_install_<chave>` | Use `plugin_<chave>_install` (ver Nota sobre os nomes acima) |
| Categoria/localização também não salvam | O direito "update" está **desmarcado** para o perfil (isso bloqueia tudo, inclusive os campos que NÃO são deste plugin) | Reative "update" para o perfil — este plugin é quem protege só os campos protegidos (Título, Descrição, "Por" e edição de Acompanhamento) |
| Acompanhamento novo (interface) não é criado | "Bloquear edição de Acompanhamento" está ativo, mas isso NÃO afeta adição — o problema é outro | Verifique permissões de "acompanhamento" do perfil/entidade |
| Edição de acompanhamento ainda funciona | "Bloquear edição de Acompanhamento" está desligado | Ative na tela de configuração |

---

## Desinstalar o plugin

1. **Configuração → Plugins** → botão "Desinstalar".
2. Exclua a pasta `fieldlock/` do diretório `plugins/`.

A desinstalação também remove os valores gravados em `glpi_configs`. O plugin
não altera dados existentes (chamados, acompanhamentos anteriores).