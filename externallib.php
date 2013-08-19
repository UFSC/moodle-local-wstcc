<?php
/**
 * External Web Service TCC
 *
 * @package    localwstcc
 * @author     Bruno Silveira
 */
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . "/gradelib.php");

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
                  JOIN {user} u
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

    /**
     * Cria ou atualiza o grade item do curso especificado
     *
     * @return array
     */
    public static function create_grade_item($courseid, $itemname, $grademin, $grademax) {
        global $DB;
        $course_category = grade_category::fetch_course_category($courseid);
        $grade_item = $DB->get_record('grade_items', array('courseid' => $courseid, 'itemname' => $itemname));

        if ($grade_item) {
            $g = new grade_item($grade_item);
        } else {
            $g = new grade_item();
        }

        $g->courseid = $courseid;
        $g->categoryid = $course_category->id;
        $g->itemname = $itemname;
        $g->grademin = $grademin;
        $g->grademax = $grademax;
        $g->itemtype = 'local';
        $g->itemmodule = 'wstcc';
        if ($grade_item) {
            $result = $DB->update_record('grade_items', $g);
        } else {
            $result = $DB->insert_record('grade_items', $g);
        }
        if($result) {
            return array('result' => 'update successful');
        } else {
            return array('result' => 'error saving');
        }
    }

    public static function create_grade_item_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'itemname' => new external_value(PARAM_RAW, 'Item Name', VALUE_REQUIRED),
                'grademin' => new external_value(PARAM_INT, 'Grade min', VALUE_REQUIRED),
                'grademax' => new external_value(PARAM_INT, 'Grade max', VALUE_REQUIRED)
            )
        );
    }

    public static function create_grade_item_returns() {
        $keys = array(
            'result' => new external_value(PARAM_RAW, 'result'),
        );

        return new external_single_structure($keys, 'Result.');
    }

    /**
     * Insere a nota do usuário no item especificado
     *
     * @return array
     */
    public static function set_grade($courseid, $itemname, $grademin, $grademax, $userid, $grade) {
        global $DB;
        $grade_item = $DB->get_record('grade_items', array('courseid' => $courseid, 'itemname' => $itemname));
        $grade_grade =$DB->get_record('grade_grades', array('itemid' => $grade_item->id, 'userid' => $userid));
        if ($grade_grade) {
            $g = new grade_grade($grade_grade);
            $g->finalgrade = $grade;
            $g->rawgrade = $grade;
            $g->rawgrademin = $grademin;
            $g->rawgrademax = $grademax;
            $result = $DB->update_record('grade_grades', $g);
        } else {
            $g = new grade_grade();
            $g->itemid = $grade_item->id;
            $g->userid = $userid;
            $g->finalgrade = $grade;
            $g->rawgrade = $grade;
            $g->rawgrademin = $grademin;
            $g->rawgrademax = $grademax;
            $result = $DB->insert_record('grade_grades', $g, true);
        }
        if($result) {
            return array('result' => 'set grade successful');
        } else {
            return array('result' => 'set grade failed');
        }
    }

    public static function set_grade_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'itemname' => new external_value(PARAM_RAW, 'Item Name', VALUE_REQUIRED),
                'grademin' => new external_value(PARAM_INT, 'Grade min', VALUE_REQUIRED),
                'grademax' => new external_value(PARAM_INT, 'Grade max', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                'grade' => new external_value(PARAM_INT, 'Grade', VALUE_REQUIRED)
            )
        );
    }

    public static function set_grade_returns() {
        $keys = array(
            'result' => new external_value(PARAM_RAW, 'result'),
        );

        return new external_single_structure($keys, 'Result.');
    }
}
