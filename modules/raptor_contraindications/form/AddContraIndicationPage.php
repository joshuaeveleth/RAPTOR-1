<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 *  
 */

namespace raptor;

module_load_include('php','simplerulesengine_ui','form/AddRulePage');
module_load_include('inc','raptor_contraindications','core/ContraIndEngine');

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class AddContraIndicationPage extends \simplerulesengine\AddRulePage
{
    public function __construct()
    {
        parent::__construct(new \raptor\ContraIndEngine(NULL)
                ,    array('return'=>NULL)
                );
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues, $html_classname_overrides=NULL)
    {
        global $base_url;
        $form = parent::getForm($form, $form_state, $disabled, $myvalues, $html_classname_overrides);
        $form['data_entry_area1']['action_buttons']['cancel'] = array(
                '#markup' => '<input class="admin-cancel-button" type="button" '
                . ' value="Cancel" '
                . ' data-redirect="'.$base_url.'/raptor/managecontraindications">');
        return $form;
    }
    
}

