<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $available_roles = $DB->get_records('role');
    if ($available_roles) {
        $available_roles = role_fix_names($available_roles, null, ROLENAME_ORIGINAL);
        $assignable_roles = get_roles_for_contextlevels(CONTEXT_COURSE);

        $roles = array();
        foreach ($assignable_roles as $assignable) {
            $role = $available_roles[$assignable];
            $roles[$role->shortname] = $role->localname;
        }

        // Fica em "Usuarios", junto das telas de Grupos do local_tutores: quem
        // cadastra papel de TCC acha as tres configuracoes no mesmo lugar --
        // inclusive a do orientador, que continua morando no local_tutores.
        $ADMIN->add('users', new admin_category('papeistcc',
                get_string('papeis_tcc', 'local_wstcc')));

        $settings = new admin_settingpage('papeis_tcc_settings',
                get_string('papeis_tcc_settings', 'local_wstcc'));

        $settings->add(new admin_setting_configmultiselect('local_wstcc_coordtcc_roles',
                get_string('settings_coordtcc_roles', 'local_wstcc'),
                get_string('description_coordtcc_roles', 'local_wstcc'),
                array('coordtcc'), $roles));

        $settings->add(new admin_setting_configmultiselect('local_wstcc_suporte_roles',
                get_string('settings_suporte_roles', 'local_wstcc'),
                get_string('description_suporte_roles', 'local_wstcc'),
                array('suporteorientacao'), $roles));

        $ADMIN->add('papeistcc', $settings);
    }
}
