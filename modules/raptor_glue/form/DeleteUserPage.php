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
 * Copyright 2015 SAN Business Consultants, a Maryland USA company (sanbusinessconsultants.com)
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ------------------------------------------------------------------------------------
 */ 


namespace raptor;

module_load_include('php', 'raptor_datalayer', 'config/Choices');
require_once 'FormHelper.php';
require_once 'UserPageHelper.php';
require_once 'ChildEditBasePage.php';

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class DeleteUserPage extends \raptor\ChildEditBasePage
{
    private $m_oPageHelper = null;
    private $m_nUID = null;
    private $m_oContext = NULL;
    
     //Call same function as in EditUserPage here!
    function __construct($nUID)
    {
        if(!isset($nUID) || !is_numeric($nUID))
        {
            die("Missing or invalid uid value = " . $nUID);
        }
        $this->m_nUID = $nUID;
        $this->m_oPageHelper = new \raptor\UserPageHelper();
        $this->m_oContext = \raptor\Context::getInstance();
        $this->m_oPageHelper->checkAllowedToDeleteUser($this->m_oContext, $this->m_nUID);
        
        //Set the default gobackto url now
        global $base_url;
        $this->setGobacktoURL($base_url.'/raptor/manageusers');
    }

    /**
     * Get the values to populate the form.
     * @return type result of the queries as an array
     */
    function getFieldValues()
    {
        $myvalues = $this->m_oPageHelper->getFieldValues($this->m_nUID);
        $myvalues['formmode'] = 'D';
        return $myvalues;
    }
    
    /**
     * Actually removes the record IF there are no records referencing this user.
     * If records do reference it, then only marks it inactive.
     */
    function updateDatabase($form, $myvalues)
    {
        //$bHasReferences = UserInfo::userIsReferenced($this->m_nUID);
        $userinfo = new \raptor\UserInfo($this->m_nUID);
        $username = $userinfo->getUserName();
        $rolename = $userinfo->getRoleName();
        $bHasReferences = $userinfo->isReferenced();
        
        $feedback = NULL;
        try
        {
            if($bHasReferences)
            {
                $updated_dt = date("Y-m-d H:i", time());
                $nUpdated = db_update('raptor_user_profile')
                        -> fields(array(
                            'accountactive_yn' => 0,
                            'updated_dt' => $updated_dt,
                        ))
                ->condition('uid',$this->m_nUID,'=')
                ->execute();
                if($nUpdated !== 1)
                {
                    error_log("Failed to edit user back to database!\n" . var_dump($myvalues));
                    die("Failed to edit user back to database!\n" . var_dump($myvalues));
                }
                $feedback = 'Marked user as inactive instead of deleted because referenced by other records.';
            } else {
                //Delete all the child records first.
                $num_deleted = db_delete('raptor_user_modality')
                ->condition('uid',$this->m_nUID,'=')
                ->execute();
                $num_deleted = db_delete('raptor_user_anatomy')
                ->condition('uid',$this->m_nUID,'=')
                ->execute();
                $num_deleted = db_delete('raptor_user_group_membership')
                ->condition('uid',$this->m_nUID,'=')
                ->execute();
                //Now delete the profile.
                $num_deleted = db_delete('raptor_user_profile')
                ->condition('uid',$this->m_nUID,'=')
                ->execute();
                if($this->m_nUID == 1)
                {
                    //Do NOT delete this drupal user or will be very unhappy!
                    error_log('Removed user username='.$username.' uid='.$this->m_nUID.' from RAPTOR but left it alone in Drupal users table.');
                    $feedback = 'Disabled '.$rolename.' user '.$username.' in RAPTOR system.';
                } else {
                    //Now delete the Drupal user.
                    $num_deleted = db_delete('users')
                    ->condition('uid',$this->m_nUID,'=')
                    ->execute();
                    $feedback = 'Removed user '.$username.' ('.$rolename.') from RAPTOR system.';
                    error_log($feedback . ' UID was '.$this->m_nUID);
                }
            }
            drupal_set_message($feedback);
            return 1;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * @return array of all option values for the form
     */
    function getAllOptions()
    {
        return $this->m_oPageHelper->getAllOptions();
    }

    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form = $this->m_oPageHelper->getForm($form, $form_state, TRUE, $myvalues);

        //Replace the intro blurb
        $form['data_entry_area1']['introblurb'] = NULL;
        
        //Replace the username input
        $form['data_entry_area1']['leftpart']['username'] = array(
          '#type' => 'textfield', 
          '#title' => t('Login Name'), 
          '#default_value' => $myvalues['username'], 
          '#size' => 40, 
          '#maxlength' => 128, 
          '#required' => TRUE,
          '#description' => t('The login name of the user.  This must match their VISTA login name.'),
          '#disabled' => TRUE,
        );        

        $form['data_entry_area1']['leftpart']['password']['#required'] = FALSE;
        
        //Replace the buttons
        $form["data_entry_area1"]['create'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'))
                , '#value' => t('Delete User From System')
                , '#disabled' => $disabled
            );
        
        global $base_url;
        $goback = $this->getGobacktoFullURL();
        /*
        $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                , '#markup' => '<input class="admin-cancel-button" id="user-cancel"'
                . ' type="button" value="Cancel"'
                . ' data-redirect="'.$goback.'">');
         */
        $form['data_entry_area1']['action_buttons']['cancel'] = FormHelper::getExitButtonMarkup($goback);
        return $form;
    }
}
