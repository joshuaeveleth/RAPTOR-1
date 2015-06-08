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

require_once 'EditListsBasePage.php';

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class EditListRadioisotopePage extends EditListsBasePage
{
    function __construct()
    {
        parent::__construct('raptor_list_radioisotope'
            ,array('type_nm','option_tx','ct_yn','mr_yn','nm_yn')
            ,array(true,       true,   true,   true,    true)
            ,array('t',        't',    'b',    'b',     'b')
            ,array(8,          100,    1,      1,       1)
            ,array('Category','Radionuclide Text','Applies to CT?','Applies to MR?','Applies to NM?')
            ,array('type_nm','option_tx'));
        $this->setListName('Radionuclide');
    }
}

