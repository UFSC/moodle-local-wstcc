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
                'coursemoduleid' => new external_value(PARAM_INT, 'Course Module id', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Retorna texto online de determinado usuário em determinada tarefa
     *
     * @param $userid
     * @param $coursemoduleid
     * @return array()
     */
    public static function get_user_online_text_submission($userid, $coursemoduleid) {
        global $DB;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_user_online_text_submission_parameters(),
            array('userid' => $userid,
                'coursemoduleid' => $coursemoduleid));


        $sql = "SELECT ot.onlinetext, status
                  FROM {assignsubmission_onlinetext} AS ot
                  JOIN {assign_submission} AS assub
                    ON (ot.submission = assub.id)
                  JOIN {USER} u
                    ON (assub.userid = u.id)
                  JOIN {course_modules} cm
                    ON (cm.instance = ot.assignment)
                  JOIN {modules} m
                    ON (m.id = cm.module AND m.name LIKE 'assign')
                 WHERE (u.id = :userid  AND cm.id = :coursemoduleid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid'], 'coursemoduleid' => $params['coursemoduleid']));

        return array('onlinetext' => $result->onlinetext, 'status' => $result->status);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_online_text_submission_returns() {
        $keys = array(
            'onlinetext' => new external_value(PARAM_RAW, 'texto online'),
            'status' => new external_value(PARAM_TEXT, 'status')
        );

        return new external_single_structure($keys, 'Texto online de determinado usuário em determinada tarefa.');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_username_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Retorna username de determinado usuário pelo seu ID
     *
     * @param $userid
     * @return array()
     */
    public static function get_username($userid) {
        global $DB;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_username_parameters(),
            array('userid' => $userid));


        $sql = "SELECT username
                  FROM {user}
                 WHERE (id = :userid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid']));

        return array('username' => $result->username);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_username_returns() {
        $keys = array(
            'username' => new external_value(PARAM_RAW, 'username')
        );

        return new external_single_structure($keys, 'Username.');
    }


}
