<?php

/**
 * Rotinas de apoio do local_wstcc.
 *
 * @package local_wstcc
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Le uma config de papeis, caindo no default quando ela vier vazia.
 *
 * ⚠️ O default vive AQUI, e nao so' na definicao do settings.php. No Moodle o
 * default de um admin_setting so' e' gravado no install/upgrade: uma restauracao
 * de dump que apague a linha da config NAO o repoe. Neste projeto restaurar dump
 * ja' apagou o token do web service, o campo de CPF e a configuracao LTI -- e o
 * sintoma de uma config de papel vazia e' a pessoa entrar como estudante, sem
 * erro nenhum.
 *
 * @param string $nomeconfig nome da config em {config}
 * @param string $default shortnames separados por virgula
 * @return array lista de shortnames
 */
function local_wstcc_papeis_configurados($nomeconfig, $default) {
    $valor = get_config('moodle', $nomeconfig);

    if ($valor === false || trim($valor) === '') {
        debugging("local_wstcc: a config {$nomeconfig} esta vazia; usando o default '{$default}'.",
                DEBUG_DEVELOPER);
        $valor = $default;
    }

    return array_values(array_filter(array_map('trim', explode(',', $valor)), 'strlen'));
}

/**
 * Mapa shortname => nome semantico de papel de TCC.
 *
 * O papel de orientador reusa a config do local_tutores de proposito: uma config
 * propria criaria duas fontes divergentes para o mesmo fato.
 *
 * @return array
 */
function local_wstcc_mapa_papeis() {
    $mapa = array();

    $grupos = array(
        'coordenador_tcc' => local_wstcc_papeis_configurados('local_wstcc_coordtcc_roles', 'coordtcc'),
        'suporte_orientacao' => local_wstcc_papeis_configurados('local_wstcc_suporte_roles', 'suporteorientacao'),
        'orientador' => local_wstcc_papeis_configurados('local_tutores_orientador_roles', 'orientador'),
    );

    foreach ($grupos as $semantico => $shortnames) {
        foreach ($shortnames as $shortname) {
            $mapa[$shortname] = $semantico;
        }
    }

    return $mapa;
}
