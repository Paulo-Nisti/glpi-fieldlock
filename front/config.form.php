<?php

/**
 * Lock Ticket Fields - salvamento da CONFIGURACAO (POST).
 *
 * Le os valores enviados pelo formulario (front/config.php), normaliza e grava
 * na tabela glpi_configs (contexto "fieldlock"). Depois volta para a tela.
 */

require_once __DIR__ . '/../../../inc/includes.php';

// Somente quem pode configurar o GLPI acessa esta tela.
Session::checkRight('config', UPDATE);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Normaliza os valores binarios (0/1) para evitar lixo via POST.
    $protect_title         = (int) (isset($_POST['protect_title']) && $_POST['protect_title']);
    $protect_description   = (int) (isset($_POST['protect_description']) && $_POST['protect_description']);
    $protect_followup_edit = (int) (isset($_POST['protect_followup_edit']) && $_POST['protect_followup_edit']);

    // Grava no banco (glpi_configs, contexto = 'fieldlock')
    Config::setConfigurationValues('fieldlock', [
        'protect_title'         => $protect_title,
        'protect_description'   => $protect_description,
        'protect_followup_edit' => $protect_followup_edit,
    ]);

    Session::addMessageAfterRedirect(
        __('Configuracao salva com sucesso.', 'fieldlock'),
        true,
        INFO
    );
}

// Volta para a pagina de configuracao
Html::redirect('config.php');