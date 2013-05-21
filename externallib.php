<?php
/**
 * External Web Service TCC
 *
 * @package    localwstcc
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
                  FROM {assignsubmission_onlinetext} AS ot
                  JOIN {assign_submission} AS assub
                    ON (ot.submission = assub.id)
                  JOIN {user} u
                    ON (assub.userid = u.id)
                 WHERE (u.id = :userid  AND ot.assignment = :assignid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid'], 'assignid' => $params['assignid']));

        return array('onlinetext' => $result->onlinetext, 'status' => $result->status);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_online_text_submission_returns() {
        $keys = array(
            'onlinetext' => new external_value(PARAM_CLEANHTML, 'texto online'),
            'status' => new external_value(PARAM_TEXT, 'status')
        );

        return new external_single_structure($keys, 'Texto online de determinado usuário em determinada tarefa.');
    }


}
