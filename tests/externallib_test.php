<?php

/**
 * WSTcc External functions unit tests
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class local_wstcc_external_testcase extends externallib_advanced_testcase {

    protected function setUp() {
        global $CFG;
        require_once($CFG->dirroot . '/local/wstcc/externallib.php');
    }

    /**
     * Get Username Test
     */
    public function test_get_username() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $params = $user->id;

        $returnvalue = local_wstcc_external::get_username($params);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_username_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($user->username, $returnvalue['username']);
    }
}