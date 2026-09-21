# Plugin GLPI — Fields Lock (Título, Descrição, Por, Acompanhamento)

Plugin para o **GLPI 11** que bloqueia, de forma **configurável**, apenas a **edição** de campos específicos do chamado, sem mexer no banco de dados e sem desabilitar o botão "Editar".

> Criado para suprir as necessidades da equipe: os campos **Título, Descrição, "Por" e Acompanhamento** de um chamado não podem ser alterados após a criação.

- **Versão:** 1.0.0

- **Requisito:** GLPI **11.0.0+** (testado na imagem oficial `glpi/glpi:11.0.8`)

- **Licença:** GPLv2+

- **Idiomas:** pt-BR

## O que este plugin faz

Bloqueia apenas a **edição** (em chamados **já criados**) de:

- **Título** (`name`);

- **Descrição** (`content`);

- **"Por"** (`users_id_recipient` — criador/solicitante primário do chamado), **sempre** protegido (não tem opção de configuração);

- **Acompanhamento já registrado** (`ITILFollowup`) — edição manual (interface) ou automática (cron/CLI).

**Não é bloqueado** (importante):

- O **preenchimento** de Título/Descrição/"Por" na **criação** de um novo chamado;

- A **adição de novos acompanhamentos** — pela interface ou vindos de **e-mail** (a resposta do requerente que o `mailcollector` transforma em acompanhamento continua funcionando). Em resumo: *o problema não é receber acompanhamento novo, é editar o que já está registrado*;

- Os **atores** do chamado — em especial o **requerente/solicitante**, que pode ser **diferente do criador** e, portanto, continua editável; observadores, responsáveis e fornecedores também.

## Diferença para permissões nativas

Ao contrário de desmarcar o direito "update" (o que bloqueia **todo** o chamado), aqui o botão **"Editar" continua disponível** e os demais campos (**categoria, localização, responsáveis, urgência, observadores, requerente,** etc.) continuam editáveis. Ao salvar, **apenas os campos protegidos são revertidos** ao valor original.

## Requisitos e impacto no banco de dados

- **GLPI 11.0.0 ou superior** (valores configurados via `Config` do GLPI, na tabela `glpi_configs`).

- **Nenhuma tabela é criada** e nenhum schema é alterado.

- Na **instalação**, o plugin grava apenas **3 linhas em `glpi_configs`** (contexto `fieldlock`: `protect_title`, `protect_description`, `protect_followup_edit`). A **desinstalação** apaga essas 3 linhas.

- Em **operação normal**, o plugin apenas **lê** a configuração e **reverte** os campos protegidos — não insere, altera nem apaga dados de chamados por conta própria.

## Estrutura do repositório

```
├── setup.php        # Instalação, desinstalação e metadados (GLPI 11)  
├── hook.php         # Hooks pre_item_update (Ticket e ITILFollowup)  
├── plugin.xml       # Metadados para o instalador / Marketplace  
├── INSTALL.md       # Guia completo de instalação do plugin  
├── front/  
│   ├── config.php       # Tela de configuração (GET)  
│   └── config.form.php  # Salva a configuração (POST)  
└── locales/  
    └── pt_BR.php    # Tradução / mensagens
```

> Nota sobre nomes de funções no GLPI 11: as funções de manutenção seguem a convenção `plugin_<chave>_<ação>` (ex.: `plugin_fieldlock_install()`). O padrão antigo `plugin_install_<chave>` **não funciona** no GLPI 11. Detalhes no [INSTALL.md](INSTALL.md).

## Instalação (resumo)

O guia completo está em [INSTALL.md](INSTALL.md). Resumo:

1. **Backup preventivo:**

```
mysqldump -u root -p glpi > backup.sql

docker exec glpi_db mysqldump -u root -p glpi > backup.sql
```

1. **Copie a pasta inteira** para `<webroot>/plugins/` (na imagem Docker oficial o webroot é `/var/www/glpi`). Use a **pasta inteira** — nunca `fieldlock/.` solta na raiz de `plugins/`:

```
scp -r fieldlock/ usuario@servidor:<WEBROOT>/plugins/

docker cp ~/fieldlock glpi_web:/var/www/glpi/plugins/
```

1. **Permissões:**

```
chown -R www-data:www-data fieldlock/  
chmod -R u+rwX,go+rX fieldlock/
```

1. **Instale e ative** em **Configuração → Plugins** (ou via CLI):

```
php bin/console glpi:plugin:install fieldlock  
php bin/console glpi:plugin:activate fieldlock
```

## Configuração

1. **Configuração → Plugins** → na linha do plugin, clique em **"Configurar"** (ou acesse `http://SEU_GLPI/plugins/fieldlock/front/config.php`).

2. **Proteger Título do chamado** — Sim/Não (padrão **Sim**).

3. **Proteger Descrição do chamado** — Sim/Não (padrão **Sim**).

4. **Bloquear edição de Acompanhamento** — Sim/Não (padrão **Sim**). Bloqueia apenas a **edição** de acompanhamentos já registrados (manual ou automática). **Novos acompanhamentos continuam permitidos** (interface ou e-mail).

> O campo **"Por"** (`users_id_recipient` — criador/solicitante primário) é sempre protegido, sem opção. Já o **ator requerente/solicitante** é editável — o requerente pode ser diferente do criador do chamado. Observadores, responsáveis e fornecedores também continuam livres.

Desmarcar uma opção libera aquele campo. As alterações valem na hora, sem reiniciar nada. Precisam de permissão de configurar o GLPI.

## Como testar

1. Em um chamado, clique em **Editar**: o botão continua disponível.

2. Altere **Título** e **Descrição** e salve junto com uma **categoria** e um **técnico atribuído**:

   - Título e Descrição **voltam ao valor anterior** (mensagem informa);

   - **Categoria, localização e responsáveis são salvos** normalmente.

3. Altere o **"Por"** e salve → o "Por" **permanece o mesmo**.

4. Altere o **ator requerente/solicitante** e salve → o requerente **é salvo normalmente** (ele pode ser diferente do criador do chamado).

5. **Adicione um acompanhamento manualmente** → **permitido**.

6. Tente **editar o texto** de um acompanhamento existente → o texto volta ao original (mensagem informa).

7. **E-mail → acompanhamento** (se você usa): envie a resposta do requerente → o acompanhamento **é criado normalmente**.

8. **Crie um chamado novo** → Título, Descrição e "Por" são preenchidos normalmente (a proteção vale só para edição).

9. Na configuração, desative as proteções uma a uma e repita os testes.

## Comportamentos e limites conhecidos

- **Ao criar** o chamado, Título, Descrição e "Por" continuam livres — o bloqueio vale para **edições posteriores**.

- **Novos acompanhamentos** (interface ou e-mail) não sofrem bloqueio; apenas a **edição** dos já registrados é bloqueada.

- Fluxos automáticos que alteram **título/descrição/"Por"** ou que **editam acompanhamentos** também são revertidos.

- Este plugin cobre **Título, Descrição, "Por" e Acompanhamento**, no escopo de **chamados** (Tickets). **Não cobre Soluções nem Tarefas.**

## Troubleshooting (resumo)

| Sintoma | Causa provável | Correção |
| - | - | - |
| Plugin **não aparece** em Configuração → Plugins | Pasta errada (webroot incorreto) ou arquivos soltos na raiz de `plugins/` | Copie `plugins/fieldlock/` para o webroot certo |
| Plugin não aparece (mesmo no lugar certo) | Permissão ilegível pelo web server | Refaça `chown` + `chmod` |
| Categoria/localização também não salvam | O direito "update" está **desmarcado** para o perfil | Reative "update" — este plugin é quem protege só os campos protegidos |
| Acompanhamento novo (interface) não é criado | Não é efeito deste plugin | Verifique permissões de "acompanhamento" do perfil/entidade |
| Edição de acompanhamento ainda funciona | "Bloquear edição de Acompanhamento" está desligado | Ative na tela de configuração |


Mais cenários no [INSTALL.md](INSTALL.md).

## Desinstalar o plugin

1. **Configuração → Plugins** → botão "Desinstalar".

2. Exclua a pasta `fieldlock/` do diretório `plugins/`.

A desinstalação também remove os valores gravados em `glpi_configs`. O plugin não altera dados existentes (chamados, acompanhamentos anteriores).

## Licença

Distribuído sob **GPLv2+** (mesma licença dos plugins oficiais do GLPI).

