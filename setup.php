<?php

/**
 * Lock Ticket Fields - plugin setup file (GLPI 11)
 *
 * Bloqueia, de forma CONFIGURAVEL, a EDICAO apenas dos campos:
 *   - TITULO (name) de chamados ja criados;
 *   - DESCRICAO (content) de chamados ja criados;
 *   - ACOMPANHAMENTO (ITILFollowup) ja registrado: sua EDICAO e sempre
 *     bloqueada (manual ou automatica/cron).
 *
 * O campo "POR" (users_id_recipient - criador/solicitante primario) e sempre
 * protegido (nao tem opcao de configuracao).
 *
 * NAO bloqueia:
 *   - o preenchimento de Titulo/Descricao/"Por" na CRIACAO de um novo chamado;
 *   - a adicao de NOVOS acompanhamentos - sejam via interface (usuario logado)
 *     ou automaticos (ex.: resposta de e-mail do requerente que o mailcollector
 *     transforma em acompanhamento);
 *   - a alteracao de ATORES (o requerente/solicitante pode ser diferente do
 *     criador do chamado, entao o ator requerente e livre; observadores,
 *     responsaveis e fornecedores tambem).
 *
 * Em resumo: "o problema nao e receber acompanhamento novo, e sim editar o que
 * ja esta registrado".
 *
 * Diferente de desmarcar a permissao "update" (que bloqueia TODO o chamado),
 * aqui o botao "Editar" continua disponivel e os demais campos (categoria,
 * localizacao, responsaveis, etc.) continuam editaveis. Os campos protegidos
 * sao revertidos na gravacao.
 *
 * NAMES DE FUNCOES NO GLPI 11 (IMPORTANTE):
 *   - init/version: plugin_init_fieldlock() / plugin_version_fieldlock();
 *   - manutencao:   plugin_fieldlock_install(), plugin_fieldlock_uninstall(),
 *                   plugin_fieldlock_check_prerequisites() (chave ANTES da acao).
 *   O padrao antigo "plugin_install_<chave>" NAO funciona no GLPI 11
 *   (instalacao falha com "Funcao ..._install nao encontrada").
 */

/**
 * Inicializa o plugin (chamado pelo GLPI em toda requisicao).
 */
function plugin_init_fieldlock(): void
{
    global $PLUGIN_HOOKS;

    // Respeita a protecao CSRF do GLPI (necessario para enviar formularios).
    $PLUGIN_HOOKS['csrf_compliant']['fieldlock'] = true;

    // Registra a pagina de CONFIGURACAO do plugin.
    // Cria o botao "Configurar" na lista (Setup > Plugins) que abre front/config.php.
    $PLUGIN_HOOKS['config_page']['fieldlock'] = 'front/config.php';

    // Registra os hooks:
    //   - PRE_ITEM_UPDATE em Ticket: ao editar um chamado, reverte os campos
    //     protegidos (name/content/users_id_recipient "Por") para o valor
    //     original, mantendo a edicao dos demais campos e atores.
    //     (A criacao do chamado NAO passa por aqui.)
    //   - PRE_ITEM_UPDATE em ITILFollowup: bloqueia a EDICAO de acompanhamentos
    //     ja registrados, revertendo todos os campos editados.
    //   Nao ha hook de ADICAO de acompanhamento: novos acompanhamentos (manual
    //   ou por e-mail) sao sempre permitidos.
    $PLUGIN_HOOKS['pre_item_update']['fieldlock'] = [
        'Ticket'       => 'plugin_fieldlock_pre_item_update_ticket',
        'ITILFollowup' => 'plugin_fieldlock_pre_item_update_itilfollowup',
    ];
}

/**
 * Metadados do plugin (nome, versao, autor, requisitos).
 */
function plugin_version_fieldlock(): array
{
    return [
        'name'                  => 'Fields Lock',
        'version'               => '1.0.0',
        'author'                => 'Robson Coe',
        'license'               => 'GPLv2+',
        'homepage'              => '',
        'requirements'          => [
            'glpi' => [
                'min' => '11.0.0', // versao minima do GLPI aceita
            ],
        ],
        'possible_translations' => ['pt_BR'],
    ];
}

/**
 * Verifica os pre-requisitos antes da instalacao.
 */
function plugin_fieldlock_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
        echo 'Este plugin requer o GLPI 11.0.0 ou superior.';
        return false;
    }
    return true;
}

/**
 * Instalacao: grava os valores padrao de configuracao (glpi_configs).
 */
function plugin_fieldlock_install(): bool
{
    Config::setConfigurationValues('fieldlock', [
        'protect_title'           => 1, // 1 = proteger o Titulo (edicao de chamados criados)
        'protect_description'     => 1, // 1 = proteger a Descricao (edicao de chamados criados)
        'protect_followup_edit'   => 1, // 1 = bloquear EDICAO de acompanhamentos ja registrados
    ]);
    return true;
}

/**
 * Desinstalacao: remove os valores de configurecoes salvos pelo plugin.
 */
function plugin_fieldlock_uninstall(): bool
{
    Config::deleteConfigurationValues('fieldlock');
    return true;
}