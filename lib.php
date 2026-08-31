<?php

/**
 * Ganchos de componente do local_wstcc.
 *
 * @package local_wstcc
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Verificações exibidas em Administração do site → Relatórios → Verificações de estado.
 *
 * @return array
 */
function local_wstcc_status_checks(): array {
    return [
        new \local_wstcc\check\papeis(),
    ];
}
