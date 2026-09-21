<?php

/**
 * Lock Ticket Fields - hook functions (GLPI 11)
 *
 * Protege a EDICAO de:
 *   - Titulo (name) de chamados ja criados;
 *   - Descricao (content) de chamados ja criados;
 *   - "Por" do chamado - campo users_id_recipient (creador/solicitante primario).
 *
 * NAO bloqueia:
 *   - preenchimento de Titulo/Descricao/"Por" na CRIACAO de um novo chamado;
 *   - adicao de NOVOS acompanhamentos (manual ou por e-mail);
 *   - alteracao de atores (requerente/solicitante, observadores, responsaveis,
 *     fornecedores) - o requerente pode ser diferente do criador do chamado.
 *
 * O campo "Por" da tela de edicao corresponde a coluna users_id_recipient do
 * Ticket (e NAO ao ator requester/CommonITILActor). Antes do prepareInputForUpdate,
 * o input do hook ja contem o valor enviado pelo formulario; revertemos qualquer
 * tentativa de alterar users_id_recipient para o valor atual salvo no banco.
 */

/**
 * Hook executado ANTES de um chamado (Ticket) ser atualizado.
 *
 * @param Ticket $item Objeto Ticket em vias de ser atualizado.
 */
function plugin_fieldlock_pre_item_update_ticket(Ticket $item): void
{
    $config   = Config::getConfigurationValues('fieldlock');
    $protected = false;

    // 1) Protege TITULO (name)
    if (
        !empty($config['protect_title'])
        && isset($item->input['name'])
        && (string) $item->input['name'] !== (string) ($item->fields['name'] ?? '')
    ) {
        $item->input['name'] = (string) ($item->fields['name'] ?? '');
        $protected = true;
    }

    // 2) Protege DESCRICAO (content)
    if (
        !empty($config['protect_description'])
        && isset($item->input['content'])
        && (string) $item->input['content'] !== (string) ($item->fields['content'] ?? '')
    ) {
        $item->input['content'] = (string) ($item->fields['content'] ?? '');
        $protected = true;
    }

    // 3) Protege "Por" (users_id_recipient) do chamado
    // O campo "Por" da tela de edicao corresponde a coluna users_id_recipient
    // (criador/solicitante primario / "Recipient") do Ticket.
    if (
        isset($item->input['users_id_recipient'])
        && (string) $item->input['users_id_recipient'] !== (string) ($item->fields['users_id_recipient'] ?? '')
    ) {
        $item->input['users_id_recipient'] = (string) ($item->fields['users_id_recipient'] ?? '');
        $protected = true;
    }

    if ($protected) {
        Session::addMessageAfterRedirect(
            __('Campos protegidos: Titulo, Descricao e Solicitante (Por) nao podem ser alterados.', 'fieldlock'),
            true,
            INFO
        );
    }
}

/**
 * Hook executado ANTES de atualizar um ACOMPANHAMENTO existente
 * (ITILFollowup::update).
 *
 * Reverte todos os campos editados (content, is_private, etc.) para o valor
 * atual, impedindo qualquer edicao de acompanhamentos ja registrados.
 * Novas adicoes de acompanhamento nao sao afetadas (nao ha hook de add).
 *
 * @param ITILFollowup $item Objeto ITILFollowup em vias de ser atualizado.
 */
function plugin_fieldlock_pre_item_update_itilfollowup(ITILFollowup $item): void
{
    $config = Config::getConfigurationValues('fieldlock');
    if (empty($config['protect_followup_edit'])) {
        return;
    }

    $changed = false;
    foreach ($item->input as $key => $value) {
        // Reverte apenas chaves que correspondem a colunas reais (em fields).
        // Flags de controle (com prefixo "_") nao estao em fields e sao mantidas.
        if (
            array_key_exists($key, $item->fields)
            && (string) $value !== (string) ($item->fields[$key] ?? '')
        ) {
            $item->input[$key] = $item->fields[$key];
            $changed = true;
        }
    }

    if ($changed) {
        Session::addMessageAfterRedirect(
            __('Acompanhamento bloqueado: nao e possivel editar acompanhamentos ja registrados.', 'fieldlock'),
            true,
            INFO
        );
    }
}
