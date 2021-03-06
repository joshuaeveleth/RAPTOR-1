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
 * Knows what transitions are allowed.
 *
 * @author Frank Font of SAN Business Consultants
 */
class Transitions 
{
    private $m_aBaselineTransitions = array(
        'AC'=>array('AC','CO','RV','IA','AP')
        ,'CO'=>array('AC','CO','RV','IA','AP')
        ,'RV'=>array('AC','CO','RV','IA','AP')
        ,'AP'=>array('PA','IA')
        ,'PA'=>array('EC','IA')
        ,'EC'=>array('QA','IA')
        ,'QA'=>array('QA','IA')
        ,'IA'=>array('AC')
        );
 
    function __construct()
    {
        $loaded = module_load_include('php','raptor_glue','core/config');
        if(!$loaded)
        {
            $msg = 'Failed to load the core/config values';
            throw new \Exception($msg);      //This is fatal, so stop everything now.
        }
    }
    
    public function getAllPossibleTicketStates()
    {
        $map = array();
        foreach($this->m_aBaselineTransitions as $key=>$values)
        {
            if($key != 'CO')
            {
                $phrase = self::getTicketPhraseFromWorflowState($key);
                $map[$key] = $phrase;
            }
        }
        return $map;
    }
    
    /**
     * Return TRUE if a ticket is available for protocoling.
     */
    public function isReadyForProtocolState($sTicketState)
    {
        return $sTicketState == 'AC' || $sTicketState == 'CO' || $sTicketState == 'RV';
    }

    /**
     * Return TRUE if a ticket is ready for technician.
     */
    public function isReadyForExamState($sTicketState)
    {
        return $sTicketState == 'AP' || $sTicketState == 'PA';
    }
    
    /**
     * Return TRUE if a ticket has completed its patient care workflow.
     */
    public function isExamCompleteState($sTicketState)
    {
        return $sTicketState == 'EC' || $sTicketState == 'QA';
    }
    
    /**
     * Return TRUE if a ticket has been canceled.
     */
    public function isCancelledState($sTicketState)
    {
        return $sTicketState == 'IA';
    }

    /**
     * Return array of allowed transitions from the provided workflow state.
     * WARNING: These are allowed transitions BEFORE considering customizations!
     */
    private function getAllowedBaselineTransitions($sFrom)
    {
        if(!isset($this->m_aBaselineTransitions[$sFrom]))
        {
            throw new \Exception('Invalid from state value "'.$sFrom.'"!');
        }
        $allowedtransitions = $this->m_aBaselineTransitions[$sFrom];
        return $allowedtransitions;
    }
    
    /**
     * Return true if state transition is allowed.
     */
    public function isAllowedTransition($sFrom, $sTo)
    {
        //Check all the custom FALSE result overrides first.
        if(BLOCK_TICKET_STATE_PA && $sTo == 'PA')
        {
            return FALSE;
        }
        if(BLOCK_TICKET_STATE_EC && $sTo == 'EC')
        {
            return FALSE;
        }
        //Check all the custom TRUE result overrides next.
        if(ALLOW_TICKET_STATE_SHORTCUT_TO_QA_FROM_AP && $sFrom == 'AP' && $sTo == 'QA')
        {
            return TRUE;
        }
        if(ALLOW_TICKET_STATE_SHORTCUT_TO_QA_FROM_PA && $sFrom == 'PA' && $sTo == 'QA')
        {
            return TRUE;
        }
        //If we are here, simply use the baseline map as the guide.
        $aAllowed = $this->getAllowedBaselineTransitions($sFrom);
        return in_array($sTo, $aAllowed);
    }
    
    /**
     * One or more workflow states map to single processing state codes.
     * P = Protocol mode
     * E = Examination mode
     * I = Interpretation mode
     * Q = QA mode
     * C = Cancel/replace requested mode
     */
    public function getTicketProcessingModeCodeFromWFS($sCWFS)
    {
        if($sCWFS == 'AC' || $sCWFS == 'CO' || $sCWFS == 'RV')
        {
            $sProcessingMode = 'P';
        } else
        if($sCWFS == 'AP' || $sCWFS == 'PA')
        {
            $sProcessingMode = 'E';
        } else
        if($sCWFS == 'EC')
        {
            $sProcessingMode = 'I';
        } else
        if($sCWFS == 'QA')
        {
            $sProcessingMode = 'Q';
        } else
        if($sCWFS == 'IA')
        {
            $sProcessingMode = 'C';
        } else {
            throw new \Exception('Did NOT find a valid ProcessingMode for workflow state code=[' . $sCWFS . ']');
        }
        return $sProcessingMode;
    }
    
    /**
     * Expand the code into a phrase.
     */
    public static function getTicketPhraseFromWorflowState($sCWFS)
    {
        if($sCWFS == 'AC')
        {
            $sPhrase = 'Active';
        } else
        if($sCWFS == 'CO')
        {
            //DEPRECATED ticket state as of 20150702
            $sPhrase = 'Collaboration(DEPR)';
        } else
        if($sCWFS == 'RV')
        {
            $sPhrase = 'Review';
        } else
        if($sCWFS == 'AP')
        {
            $sPhrase = 'Approved';
        } else
        if($sCWFS == 'PA')
        {
            $sPhrase = 'Protocol Acknowledged';
        } else
        if($sCWFS == 'EC')
        {
            $sPhrase = 'Exam Completed';
        } else
        if($sCWFS == 'QA')
        {
            $sPhrase = 'Quality Assurance';
        } else
        if($sCWFS == 'IA')
        {
            $sPhrase = 'Needs Cancel/Replace';
        } else {
            throw new \Exception('Did NOT find a valid Phrase for workflow state code=[' . $sCWFS . ']');
        }
        return $sPhrase;
    }
    
}
