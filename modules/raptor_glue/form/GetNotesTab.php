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


/**
 * This class returns the VistA Notes tab content
 *
 * @author Frank Font of SAN Business Consultants
 */
class GetNotesTab
{
    private $m_oContext = NULL;
    
     //Call same function as in EditUserPage here!
    function __construct($oContext)
    {
        module_load_include('php', 'raptor_datalayer', 'core/Context');
        module_load_include('php', 'raptor_datalayer', 'core/TicketTrackingData');
        //module_load_include('php', 'raptor_datalayer', 'core/data_worklist');
        //module_load_include('php', 'raptor_datalayer', 'core/data_dashboard');
        //module_load_include('php', 'raptor_datalayer', 'core/data_protocolsupport');
        module_load_include('php', 'raptor_datalayer', 'core/ProtocolSettings');
        
        $this->m_oContext = $oContext;
        if(!$oContext->hasSelectedTrackingID())
        {
            throw new \Exception('Did NOT find a selected Tracking ID.  Go back to the worklist and select a ticket first.');
        }
    }

    private static function raptor_print_details($data)
    {
        //return print_r($text,TRUE);
        $result = "";

        $result .= "<div class=\"hide\"><dl>";

        if (is_array($data)) {

          foreach($data as $key => $value) {
            $result .= "<dt>".$key.":</dt>";
            $result .= "<dd>".nl2br($value)."</dd>";
          }

        } else {
          $result .= nl2br($data);
        }

        $result .= "</dl></div>";

        return $result;
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form["data_entry_area1"] = array(
            '#prefix' => "\n<section class='protocollib-admin raptor-dialog-table'>\n",
            '#suffix' => "\n</section>\n",
        );
        $form["data_entry_area1"]['table_container'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-dialog-table-container">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );
        
        //$oPSD = new \raptor\ProtocolSupportingData($this->m_oContext);
        //$radiology_reports_detail = $oPSD->getNotesDetail();
        $ehrDao = $this->m_oContext->getEhrDao();
        $radiology_reports_detail = $ehrDao->getNotesDetailMap();
        
        $rows = '';
        foreach($radiology_reports_detail as $data_row) 
        {
            $rows .= "\n".'<tr>'
                  . '<td>'.$data_row["Type"].'</td>'
                  . '<td>'.$data_row["Date"].'</td>'
                  . '<td><a href="#" class="raptor-details">'.$data_row['Snippet'].'</a>'.GetNotesTab::raptor_print_details($data_row["Details"]).'</td>'
                  . '</tr>';
        }
        
        $form["data_entry_area1"]['table_container']['reports'] = array('#type' => 'item',
                 '#markup' => '<table id="selected-notes" class="dataTable notes-tab-table">'
                            . '<thead>'
                            . '<tr>'
                            . '<th>Title</th>'
                            . '<th>Date</th>'
                            . '<th>Details</th>'
                            . '</tr>'
                            . '</thead>'
                            . '<tbody>'
                            . $rows
                            .  '</tbody>'
                            . '</table>');
        
        return $form;
    }
}
