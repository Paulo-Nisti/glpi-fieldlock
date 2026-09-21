<?php

/**
 * Lock Ticket Fields - pagina de CONFIGURACAO (GET).
 *
 * Mostra o formulario com os tres campos protegidos (Titulo, Descricao,
 * Edicao de Acompanhamento). O salvamento e feito por config.form.php (POST).
 */

require_once __DIR__ . '/../../../inc/includes.php';

// Somente quem pode configurar o GLPI acessa esta tela.
Session::checkRight('config', UPDATE);

// Valores atuais (padrao ligado se ainda nao configurado)
$config = Config::getConfigurationValues('fieldlock');
$protect_title         = !empty($config['protect_title']);
$protect_description   = !empty($config['protect_description']);
$protect_followup_edit = !empty($config['protect_followup_edit']);

// Opcoes binarias dos dropdowns
$yes_no = [
    0 => __('No', 'fieldlock'),
    1 => __('Yes', 'fieldlock'),
];

Html::header(
    __('Protecao de campos do chamado', 'fieldlock'),
    $_SERVER['PHP_SELF'],
    'admin',
    'PluginFieldLockConfig'
);

echo '<div class="card m-3">';
echo '<div class="card-header py-1">';
echo '<h1 class="h3 mb-0">' . __('Protecao de campos do chamado', 'fieldlock') . '</h1>';
echo '</div>';

echo '<div class="card-body">';
echo '<p class="text-muted">' .
    __('Bloqueia apenas a EDICAO dos campos Titulo, Descricao e Acompanhamento ja registrado. O campo "Por" (users_id_recipient - criador/solicitante primario) e sempre protegido. Novos acompanhamentos (pela interface ou por e-mail) continuam permitidos, os atores (requerente, observadores, responsaveis, fornecedores) e os demais campos (categoria, localizacao, etc.) continuam editaveis. Diferente de desmarcar "update": o botao "Editar" continua aparecendo.', 'fieldlock') .
    '</p>';

echo '<form method="post" action="config.form.php">';

echo '<div class="mb-3">';
echo '<label class="form-label" for="protect_title">' .
    __('Proteger Titulo do chamado', 'fieldlock') .
    '</label>';
Dropdown::showFromArray('protect_title', $yes_no, [
    'value' => (int) $protect_title,
    'aria_label' => __('Proteger Titulo do chamado', 'fieldlock'),
]);
echo '</div>';

echo '<div class="mb-3">';
echo '<label class="form-label" for="protect_description">' .
    __('Proteger Descricao do chamado', 'fieldlock') .
    '</label>';
Dropdown::showFromArray('protect_description', $yes_no, [
    'value' => (int) $protect_description,
    'aria_label' => __('Proteger Descricao do chamado', 'fieldlock'),
]);
echo '</div>';

echo '<div class="mb-3">';
echo '<label class="form-label" for="protect_followup_edit">' .
    __('Bloquear edicao de Acompanhamento', 'fieldlock') .
    ' <span class="badge bg-secondary">' . __('apenas edicao; novos e-mails/acompanhamentos continuam passando', 'fieldlock') . '</span></label>';
Dropdown::showFromArray('protect_followup_edit', $yes_no, [
    'value' => (int) $protect_followup_edit,
    'aria_label' => __('Bloquear edicao de Acompanhamento', 'fieldlock'),
]);
echo '</div>';

echo '<button type="submit" name="save" value="1" class="btn btn-primary">' .
    __('Save') .
    '</button>';

Html::closeForm();
echo '</div>'; // card-body
echo '</div>'; // card

Html::footer();