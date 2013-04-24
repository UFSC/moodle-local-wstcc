<?php

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

/**
 * External Web Service TCC
 *
 * @package    localwstemplate
 * @author     Bruno Silveira
 */
require_once($CFG->libdir . "/externallib.php");

class local_wstcc_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_user_online_text_submission_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                'assignid' => new external_value(PARAM_INT, 'Assign id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Retorna texto online de determinado usuário em determinada tarefa
     *
     * @param $userid
     * @param $assignid
     * @return array()
     */
    public static function get_user_online_text_submission($userid, $assignid) {
        global $DB;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_user_online_text_submission_parameters(),
            array('userid' => $userid,
                'assignid' => $assignid));


        $sql = "SELECT ot.onlinetext, status
                FROM assignsubmission_onlinetext ot
                JOIN assign_submission assub ON (ot.submission = assub.id)
                JOIN user u ON (assub.userid = u.id)
                WHERE (u.id = :userid  AND ot.assignment = :assignid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid'], 'assignid' => $params['assignid']));

        return array('onlinetext'=>$result->onlinetext, 'status' => $result->status);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_online_text_submission_returns() {
        return new external_single_structure(
            array('onlinetext' => new external_value(PARAM_CLEANHTML, 'texto online'),
                  'status' => new external_value(PARAM_TEXT, 'status')
            ), 'Texto online de determinado usuário em determinada tarefa.');
    }


}
