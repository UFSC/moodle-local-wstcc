<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_wstcc\check;

use core\check\check;
use core\check\result;

/**
 * Mostra o mapa de papéis de TCC: qual shortname responde por cada papel semântico,
 * se veio da configuração ou do default, e se o papel existe de fato.
 *
 * Existe porque o estado precisava ser **consultável**, não anunciado. A primeira
 * versão avisava por debugging(), que só produz saída com depuração de
 * desenvolvedor ligada — em produção era no-op, e um aviso que só existe em
 * desenvolvimento é pior que nenhum: cria a sensação de rede de proteção onde não
 * há. Anunciar por log a cada chamada seria pior ainda: uma instalação que use só
 * o coordenador dispararia a linha em toda requisição, e linha que sempre aparece
 * deixa de ser lida (é o defeito que a #63 registrou no relatório do sync).
 *
 * Aqui quem investiga abre a tela e vê o estado, sem depender de estar com
 * depuração ligada na hora certa.
 *
 * @package    local_wstcc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class papeis extends check {

    /**
     * Nome exibido na lista de verificações.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('checkpapeis', 'local_wstcc');
    }

    /**
     * Leva à tela onde os papéis são configurados.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
                new \moodle_url('/admin/settings.php', array('section' => 'papeis_tcc_settings')),
                get_string('papeis_tcc_settings', 'local_wstcc'));
    }

    /**
     * @return result
     */
    public function get_result(): result {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/wstcc/locallib.php');

        $fontes = array(
            'coordenador_tcc' => array('local_wstcc_coordtcc_roles', 'coordtcc'),
            'suporte_orientacao' => array('local_wstcc_suporte_roles', 'suporteorientacao'),
            'orientador' => array('local_tutores_orientador_roles', 'orientador'),
        );

        $linhas = array();
        $usandodefault = array();
        $semkpapel = array();
        $shortnamesporsemantico = [];

        foreach ($fontes as $semantico => $fonte) {
            list($nomeconfig, $default) = $fonte;

            $valor = get_config('moodle', $nomeconfig);
            $default_em_uso = ($valor === false || trim($valor) === '');
            if ($default_em_uso) {
                $usandodefault[] = $semantico;
            }

            // Le pela MESMA rotina que o web service usa: se as duas divergirem,
            // o check passa a descrever um mapa que nao e' o aplicado.
            $shortnames = local_wstcc_papeis_configurados($nomeconfig, $default);
            $shortnamesporsemantico[$semantico] = $shortnames;

            list($in, $params) = $DB->get_in_or_equal($shortnames);
            $existentes = $DB->get_fieldset_select('role', 'shortname', "shortname {$in}", $params);
            $ausentes = array_diff($shortnames, $existentes);

            if (empty($existentes)) {
                $semkpapel[] = $semantico;
            }

            $linhas[] = \html_writer::tag('li', s($semantico) . ': ' .
                    \html_writer::tag('code', s(implode(', ', $shortnames))) .
                    ($default_em_uso ? ' — ' . get_string('checkpapeis_default', 'local_wstcc') : '') .
                    (!empty($ausentes) ? ' — ' . get_string('checkpapeis_ausente', 'local_wstcc',
                            s(implode(', ', $ausentes))) : ''));
        }

        $detalhe = \html_writer::tag('ul', implode('', $linhas));

        if (!empty($semkpapel)) {
            // Nenhum papel existente para um semantico: ninguem sera reconhecido
            // como ele, e a pessoa cai na tela de estudante sem erro nenhum.
            return new result(result::WARNING,
                    get_string('checkpapeis_semrole', 'local_wstcc', s(implode(', ', $semkpapel))),
                    $detalhe);
        }

        // Support roles whose definition lacks the capability of the TCC reports (report_unasus#20).
        // Only the system-level role definition is read; a category override is not considered.
        $suportesemcap = [];
        if (get_capability_info('report/unasus:view_orientacao')) {
            [$in, $params] = $DB->get_in_or_equal($shortnamesporsemantico['suporte_orientacao']);
            $syscontext = \context_system::instance();
            foreach ($DB->get_records_select('role', "shortname {$in}", $params) as $role) {
                $caps = role_context_capabilities($role->id, $syscontext, 'report/unasus:view_orientacao');
                if (($caps['report/unasus:view_orientacao'] ?? null) != CAP_ALLOW) {
                    $suportesemcap[] = $role->shortname;
                }
            }
        }

        if (!empty($suportesemcap)) {
            return new result(
                result::WARNING,
                get_string('checkpapeis_suportesemcap', 'local_wstcc', s(implode(', ', $suportesemcap))),
                $detalhe
            );
        }

        if (!empty($usandodefault)) {
            return new result(result::INFO,
                    get_string('checkpapeis_usandodefault', 'local_wstcc',
                            s(implode(', ', $usandodefault))),
                    $detalhe);
        }

        return new result(result::OK, get_string('checkpapeis_ok', 'local_wstcc'), $detalhe);
    }
}
