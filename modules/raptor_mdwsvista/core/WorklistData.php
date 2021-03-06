<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Joel Mewton, et al
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

namespace raptor_mdwsvista;

/**
 * This class provides methods to return filtered lists of rows for the worklist
 * and also a single worklist row when given a matching ID.
 *
 * @author SAN
 */
class WorklistData 
{
    private $m_oContext;
    
    //Worklist Vista Field Order
    const WLVFO_TrackingID            = 0;    
    const WLVFO_PatientID             = 1;
    const WLVFO_PatientName           = 2;
    const WLVFO_ExamCategory          = 3;
    const WLVFO_RequestingPhysician   = 4;
    const WLVFO_OrderedDate           = 5;
    const WLVFO_Procedure             = 6;
    const WLVFO_ImageType             = 7;
    const WLVFO_ExamLocation          = 8;
    const WLVFO_Urgency               = 9;
    const WLVFO_Nature                = 10;
    const WLVFO_Transport             = 11;
    const WLVFO_DesiredDate           = 12;
    const WLVFO_OrderFileIen          = 13; //Not the tracking ID we use
    const WLVFO_RadOrderStatus        = 14;
    
    //Values of Order Status http://code.osehra.org/dox/Global_XlJBTyg3NS4x.html
    const WLVOS_DISCONTINUED = 1;
    const WLVOS_COMPLETE = 2;
    const WLVOS_HOLD = 3;
    const WLVOS_PENDING = 5;
    const WLVOS_ACTIVE = 6;
    const WLVOS_SCHEDULED = 8;
    const WLVOS_UNRELEASED = 11;
    
    function __construct($oContext)
    {
        if($oContext == NULL)
        {
            throw new \Exception("Cannot get instance of Worklist without context!");
        }
        
        module_load_include('php', 'raptor_datalayer', 'core/WorklistColumnMap');
        module_load_include('php', 'raptor_glue', 'core/config');
        module_load_include('php', 'raptor_formulas', 'core/MatchOrderToUser');
        module_load_include('php', 'raptor_formulas', 'core/LanguageInference');

        $this->m_oContext = $oContext;
    }

    /**
     * Gets each row of the worklist.
     */
    private function parseWorklistTextRows($all_worklist_rows_raw_text_ar, $ticketTrackingDict, $match_IEN=NULL)
    {
        try
        {
            $aPatientPendingOrderCount = array();
            $aPatientPendingOrderMap = array();
            $nOffsetMatchIEN = NULL;

            $oUserInfo = $this->m_oContext->getUserInfo();

            $match_order_to_user = new \raptor_formulas\MatchOrderToUser($oUserInfo);
            $language_infer = new \raptor_formulas\LanguageInference();

            $worklist = array();

            $exploded = array();
            $t = array();
            $nFound=0;

            $ticketTrackingRslt = $ticketTrackingDict['raptor_ticket_tracking'];
            $ticketCollabRslt = $ticketTrackingDict['raptor_ticket_collaboration'];
            $scheduleTrackRslt = $ticketTrackingDict['raptor_schedule_track'];

            $skipped_because_status = 0;
            $skipped_because_other = 0;
            $status_tracking_info1 = array();
            $status_tracking_info2 = array();
            $numOrders = count($all_worklist_rows_raw_text_ar);
            for ($i=0; $i<$numOrders; $i++)
            {
                $exploded = explode("^", $all_worklist_rows_raw_text_ar[$i]);
                $vista_order_status_code = $exploded[self::WLVFO_RadOrderStatus];

                $ienKey = $exploded[0];
                $sqlTicketTrackRow = array_key_exists($ienKey, $ticketTrackingRslt) ? $ticketTrackingRslt[$ienKey] : NULL; // use IEN from MDWS results as key
                $sqlTicketCollaborationRow = array_key_exists($ienKey, $ticketCollabRslt) ? $ticketCollabRslt[$ienKey] : NULL; // use IEN from MDWS results as key
                $sqlScheduleTrackRow = array_key_exists($ienKey, $scheduleTrackRslt) ? $scheduleTrackRslt[$ienKey] : NULL; // use IEN from MDWS results as key

                $raptor_order_status  = (isset($sqlTicketTrackRow) ? $sqlTicketTrackRow->workflow_state : NULL);
                if(
                    ($raptor_order_status == NULL 
                        && ($vista_order_status_code != self::WLVOS_ACTIVE && $vista_order_status_code != self::WLVOS_PENDING))
                    || $vista_order_status_code == self::WLVOS_DISCONTINUED)
                {
                    //We will NOT show this ticket in the worklist
                    $skipped_because_status++;
                    continue;
                }
                if(isset($status_tracking_info1[$vista_order_status_code]))
                {
                    $status_tracking_info1[$vista_order_status_code] = $status_tracking_info1[$vista_order_status_code] + 1;
                    if(isset($status_tracking_info2[$vista_order_status_code][$raptor_order_status]))
                    {
                        $status_tracking_info2[$vista_order_status_code][$raptor_order_status] = $status_tracking_info2[$vista_order_status_code][$raptor_order_status] + 1;
                    } else {
                        $status_tracking_info2[$vista_order_status_code][$raptor_order_status] = 1;
                    }
                } else {
                    $status_tracking_info1[$vista_order_status_code] = 1;
                    $status_tracking_info2[$vista_order_status_code][$raptor_order_status] = 1;
                }
                
                $t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] = ($raptor_order_status != NULL ? $raptor_order_status : 'AC'); // default to "AC"
                $patientID = $exploded[self::WLVFO_PatientID];
                $t[\raptor\WorklistColumnMap::WLIDX_TRACKINGID]  = $exploded[0];
                $t[\raptor\WorklistColumnMap::WLIDX_PATIENTID]   = $patientID; 
                $t[\raptor\WorklistColumnMap::WLIDX_PATIENTNAME] = $this->formatPatientName($exploded[self::WLVFO_PatientName]);
                $desired_date_raw = $exploded[self::WLVFO_DesiredDate];
                $t[\raptor\WorklistColumnMap::WLIDX_DATETIMEDESIRED] = $desired_date_raw;
                if(strpos($desired_date_raw, '@') !== FALSE)
                {
                    $desired_date_parts=explode('@',$desired_date_raw);
                    $desired_date_justdate=$desired_date_parts[0];
                    $desired_date_justtime=$desired_date_parts[1];
                    $desired_date_timestamp = strtotime($desired_date_justdate);  
                    $desired_date_iso8601 = date('Y-m-d ',$desired_date_timestamp) . $desired_date_justtime;
                } else {
                    $desired_date_justdate=$desired_date_raw;
                    $desired_date_timestamp = strtotime($desired_date_justdate);  
                    $desired_date_iso8601 = date('Y-m-d',$desired_date_timestamp);
                }
                $t[\raptor\WorklistColumnMap::WLIDX_ISO8601_DATETIMEDESIRED] = $desired_date_iso8601;

                if($exploded[self::WLVFO_OrderedDate] !== '' && ($last = strrpos($exploded[self::WLVFO_OrderedDate], ':')) !== FALSE)
                {
                    //Remove the seconds from the time.
                    $dateordered_raw = substr($exploded[self::WLVFO_OrderedDate], 0, $last);
                } else {
                    //Assume there is no time portion.
                    $dateordered_raw = $exploded[self::WLVFO_OrderedDate];
                }
                $t[\raptor\WorklistColumnMap::WLIDX_DATEORDERED]     = $dateordered_raw;
                if(strpos($dateordered_raw, '@') !== FALSE)
                {
                    $dateordered_parts=explode('@',$dateordered_raw);
                    $dateordered_justdate=$dateordered_parts[0];
                    $dateordered_justtime=$dateordered_parts[1];
                    $dateordered_timestamp = strtotime($dateordered_justdate);  
                    $dateordered_iso8601 = date('Y-m-d ',$dateordered_timestamp) . ' ' . $dateordered_justtime;
                } else {
                    $dateordered_justdate=$dateordered_raw;
                    $dateordered_timestamp = strtotime($dateordered_justdate);  
                    $dateordered_iso8601 = date('Y-m-d',$dateordered_timestamp);
                }
                $t[\raptor\WorklistColumnMap::WLIDX_ISO8601_DATEORDERED] = $dateordered_iso8601;

                $t[\raptor\WorklistColumnMap::WLIDX_STUDY]       = $exploded[self::WLVFO_Procedure];
                $t[\raptor\WorklistColumnMap::WLIDX_URGENCY]     = $exploded[self::WLVFO_Urgency]; 
                switch(trim($exploded[self::WLVFO_Transport]))
                {
                    case "a":
                        $t[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = "AMBULATORY";
                        break;
                    case "p":
                        $t[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = "PORTABLE";
                        break;
                    case "s":
                        $t[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = "STRETCHER";
                        break;
                    case "w":
                        $t[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = "WHEEL CHAIR";
                        break;
                    default:
                        $t[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = $exploded[self::WLVFO_Transport];
                        break;
                }
                $t[\raptor\WorklistColumnMap::WLIDX_PATIENTCATEGORYLOCATION]     = $exploded[self::WLVFO_ExamCategory];
                $t[\raptor\WorklistColumnMap::WLIDX_ANATOMYIMAGESUBSPEC]         = NULL;    //Fill if VistA order tells us


                //Only show an assignment if ticket has not yet moved downstream in the workflow.
                if($t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'AC' 
                        || $t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'CO' || $t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'RV')
                {
                    $t[\raptor\WorklistColumnMap::WLIDX_ASSIGNEDUSER] = (isset($sqlTicketCollaborationRow) ? array(
                                                              'uid'=>$sqlTicketCollaborationRow->collaborator_uid
                                                            , 'requester_notes_tx'=>$sqlTicketCollaborationRow->requester_notes_tx
                                                            , 'requested_dt'=>$sqlTicketCollaborationRow->requested_dt
                                                            , 'username'=>$sqlTicketCollaborationRow->username
                                                            , 'fullname'=>trim($sqlTicketCollaborationRow->usernametitle . ' ' .$sqlTicketCollaborationRow->firstname
                                                                    . ' ' .$sqlTicketCollaborationRow->lastname. ' ' .$sqlTicketCollaborationRow->suffix )
                                                        ) : NULL);    
                } else {
                    $t[\raptor\WorklistColumnMap::WLIDX_ASSIGNEDUSER] = '';
                }

                $t[\raptor\WorklistColumnMap::WLIDX_EHR_ORDERSTATUS]  = '';   //Placeholder for Order Status (fill later)

                $t[\raptor\WorklistColumnMap::WLIDX_EDITINGUSER]      = '';   //Placeholder for UID of user that is currently editing the record, if any. (check local database)

                $t[\raptor\WorklistColumnMap::WLIDX_EXAMLOCATION] = $exploded[self::WLVFO_ExamLocation];
                $t[\raptor\WorklistColumnMap::WLIDX_REQUESTINGPHYSICIAN] = $exploded[self::WLVFO_RequestingPhysician];
                $rfs = trim($exploded[self::WLVFO_Nature]);
                switch ($rfs)
                {
                    case 'w' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "WRITTEN";
                        break;
                    case 'v' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "VERBAL";
                        break;
                    case 'p' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "TELEPHONED";
                        break;
                    case 's' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "SERVICE CORRECTION";
                        break;
                    case 'i' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "POLICY";
                        break;
                    case 'e' :
                        $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "PHYSICIAN ENTERED";
                        break;
                    default :
                        if(strlen($rfs)==0)
                        {
                            $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "NOT ENTERED";
                        } else {
                            $t[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = $rfs;
                        }
                        break;
                }

                // Pull schedule from raptor_schedule_track
                if($sqlScheduleTrackRow != null)
                {
                    //If a record exists, then there is something to see.
                    $showText = '';
                    if(isset($sqlScheduleTrackRow->scheduled_dt))
                    {
                        $phpdate = strtotime( $sqlScheduleTrackRow->scheduled_dt );
                        $sdt = date( 'Y-m-d H:i', $phpdate ); //Remove the seconds
                        if(isset($sqlScheduleTrackRow->confirmed_by_patient_dt))
                        {
                            if($showText > '')
                            {
                               $showText .= '<br>'; 
                            }
                            $showText .= 'Confirmed '.$sqlScheduleTrackRow->confirmed_by_patient_dt; 
                        }
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'For '. $sdt ;//$sqlScheduleTrackRow->scheduled_dt; 
                        if(isset($sqlScheduleTrackRow->location_tx))
                        {
                            if($showText > '')
                            {
                               $showText .= '<br>'; 
                            }
                            $showText .= 'In ' . $sqlScheduleTrackRow->location_tx; 
                        }
                    }
                    if(isset($sqlScheduleTrackRow->canceled_dt))
                    {
                        //If we are here, clear everything before.
                        $showText = 'Cancel requested '.$sqlScheduleTrackRow->canceled_dt; 
                    }
                    if(trim($sqlScheduleTrackRow->notes_tx) > '')
                    {
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'See Notes...'; 
                    }
                    if($showText == '')
                    {
                        //Indicate there is someting to see in the form.
                        $showText = 'See details...';
                    }
                    $t[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO] = array(
                        'EventDT' => $sqlScheduleTrackRow->scheduled_dt,
                        'LocationTx' => $sqlScheduleTrackRow->location_tx,
                        'ConfirmedDT' => $sqlScheduleTrackRow->confirmed_by_patient_dt,
                        'CanceledDT' => $sqlScheduleTrackRow->canceled_dt,
                        'ShowTx' => $showText
                    );
                    //print_r($sqlScheduleTrackRow, TRUE);
                } else {
                    //No record exists yet.
                    $t[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO] = array(
                        'EventDT' => NULL,
                        'LocationTx' => NULL,
                        'ConfirmedDT' => NULL,
                        'CanceledDT' => NULL,
                        'ShowTx' => 'Unknown'
                    );
                }

                $t[\raptor\WorklistColumnMap::WLIDX_CPRSCODE]    = '';   //Placeholder for the CPRS code associated with this ticket
                $t[\raptor\WorklistColumnMap::WLIDX_IMAGETYPE]   = $exploded[self::WLVFO_ImageType];   //Placeholder for Imaging Type - file 75.1, field 3
                $modality = $language_infer->inferModalityFromPhrase($t[\raptor\WorklistColumnMap::WLIDX_IMAGETYPE]);
                if($modality == NULL)
                {
                    $modality = $language_infer->inferModalityFromPhrase($t[\raptor\WorklistColumnMap::WLIDX_STUDY]);
                }

                $t[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = -1;  //Important that we allocate something here, will replace later.

                $t[\raptor\WorklistColumnMap::WLIDX_MODALITY] = 'Unknown';

                if($modality == '')
                {
                    //Do not return the row if we cannot determine the modality.
                    $skipped_because_other++;
                 } else {
                    //Count this order as pending for the patient?
                    if($t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'AC'
                            || $t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'CO'
                            || $t[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] == 'RV')
                    {
                        $aPatientPendingOrderMap[$patientID][$ienKey] 
                                = array($ienKey
                                    ,$modality
                                    ,$t[\raptor\WorklistColumnMap::WLIDX_STUDY]);
                        if(isset($aPatientPendingOrderCount[$patientID]))
                        {
                            $aPatientPendingOrderCount[$patientID] +=  1; 
                        } else {
                            $aPatientPendingOrderCount[$patientID] =  1; 
                        }
                    }

                    $nFound++;
                    $offset = $nFound;
                    $t[\raptor\WorklistColumnMap::WLIDX_MODALITY] = $modality;

                    //Compute the score for this row.
                    $t[\raptor\WorklistColumnMap::WLIDX_RANKSCORE] = $match_order_to_user->getTicketRelevance($t);

                    $t[\raptor\WorklistColumnMap::WLIDX_ORDERFILEIEN] = $exploded[self::WLVFO_OrderFileIen]; 
                    $t[\raptor\WorklistColumnMap::WLIDX_EHR_ORDERSTATUS] = $vista_order_status_code; //Status from VistA
                    $t[\raptor\WorklistColumnMap::WLIDX_EHR_RADIOLOGYORDERSTATUS] = $vista_order_status_code; //Status from VistA

                    //Add this row to the worklist because modality not blank.
                    $worklist[$offset] = $t;    
                    if($match_IEN != NULL && $ienKey == $match_IEN)
                    {
                        $nOffsetMatchIEN = $offset;
                    }
                    //error_log("LOOK MDWS worklist ticket $ienKey has vista status=" . $vista_order_status_code);
                }
            }
            for($i=0;$i<count($worklist);$i++)
            {
                $t = &$worklist[$i];
                if(is_array($t))
                {
                    //Yes, this is a real row.
                    $patientID = $t[\raptor\WorklistColumnMap::WLIDX_PATIENTID];
                    if(isset($aPatientPendingOrderMap[$patientID]))
                    {
                        $t[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = $aPatientPendingOrderMap[$patientID];
                        $t[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = $aPatientPendingOrderCount[$patientID];
                    } else {
                        //Found no pending orders for this IEN
                        $t[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = array();;
                        $t[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = 0;
                    }
                }
            }
            
            //Populate the array of results
            $mymetadata = array('skipped_because_status' => $skipped_because_status
                    , 'skipped_because_other' => $skipped_because_other
                    , 'vista_status_count'=>$status_tracking_info1
                    , 'vista_raptor_status_count_map'=>$status_tracking_info2);
            if(LOG_WORKLIST_METADATA)
            {
                //Useful for diagnostics of a site
                error_log("LOG_WORKLIST_METADATA for site " . VISTA_SITE. " >>> " . print_r($mymetadata,TRUE));            
            }
            $result = array('all_rows'=>&$worklist
                            ,'pending_orders_map'=>&$aPatientPendingOrderMap
                            ,'matching_offset'=>$nOffsetMatchIEN
                            ,'metadata'=>$mymetadata);
            //error_log("LOOK MDWS WORKLIST skipped orders bc_status=$skipped_because_status bc_other=$skipped_because_other");
            return $result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * @return string of fields to get from VISTA
     */
    private function getWorklistVistaFieldArgumentString()
    {
        $requestedFields = array();
        $requestedFields[0] = null;
        $requestedFields[WorklistData::WLVFO_PatientID] = ".01I";       
        $requestedFields[WorklistData::WLVFO_PatientName] = ".01";
        $requestedFields[WorklistData::WLVFO_ExamCategory] = "4";
        $requestedFields[WorklistData::WLVFO_RequestingPhysician] = "14";
        $requestedFields[WorklistData::WLVFO_OrderedDate] = "16";
        $requestedFields[WorklistData::WLVFO_Procedure] = "2";
        $requestedFields[WorklistData::WLVFO_ImageType] = "3";
        $requestedFields[WorklistData::WLVFO_ExamLocation] = "20";
        $requestedFields[WorklistData::WLVFO_Urgency] = "6";
        $requestedFields[WorklistData::WLVFO_Nature] = "26";
        $requestedFields[WorklistData::WLVFO_Transport] = "19";
        $requestedFields[WorklistData::WLVFO_DesiredDate] = "21";
        $requestedFields[WorklistData::WLVFO_OrderFileIen] = "7I";
        $requestedFields[WorklistData::WLVFO_RadOrderStatus] = "5I";    //http://code.osehra.org/dox/Global_XlJBTyg3NS4x.html
        
        // Compose argument string by concatenating field codes into semicolon-delimited list
        $argument = "";
        foreach($requestedFields as $rf)
        {
            if ($argument != "") 
            {
                $argument .= ";";
            }
            $argument .= $rf;
        }
        
        return $argument;
    }

    /**
     * @description Return result of web service call to MDWS, web method QueryService.ddrLister
     */
    private function getWorklistFromMDWS($startIEN=NULL, $MAXRECS_PER_QUERY = WORKLIST_MAXROWS_PER_QUERY)
    {
        try
        {
            $filterDiscontinued = TRUE;
            $sThisResultName = 'getWorklistFromMDWS';
            if(!isset($this->m_oContext))
            {
                throw new \Exception('getWorklistFromMDWS failed because Context object is not set!');
            }
            if($startIEN === NULL)
            {
                $startIEN = ''; //Must be an empty string
            }
            $mdwsDao = $this->m_oContext->getEhrDao()->getImplementationInstance();
            $fieldargument_string = $this->getWorklistVistaFieldArgumentString();
            $ddrListerArguments = array(
                'file'=>'75.1', 
                'iens'=>'',     //Only for sub files
                'fields'=>$fieldargument_string, 
                'flags'=>'PB',      //P=PACKED format, B=Back
                'maxrex'=>$MAXRECS_PER_QUERY,    //1780',   //20140926 Known issue with RPC if this number is too big need to look into fix
                'from'=>$startIEN,     //For pagination provide smallest IEN as startign point for new query
                'part'=>'',         //ignore
                'xref'=>'#',        //Leave as #
                'screen'=> ($filterDiscontinued ? "I (\$P(^(0),U,5)'=1)" : ''), //I ($P(^(0),U,5)=5)|($P(^(0),U,5)=6)',   //Server side filtering but APPLIED TO EACH RECORD ONE BY ONE VERY SLOW NO FILTERING BEFOREHAND
                'identifier'=>''    //Mumps code for filtering etc
                );
            $result = $mdwsDao->makeQuery('ddrLister', $ddrListerArguments);
            return $result;
        } catch (\Exception $ex) {
            $msg = 'Failed MDWS getting worklist because ' . $ex;
            error_log($msg);
            throw $ex;
        }
    }
    
    /**
     * Return ONE item
     */
    private function getWorklistItemFromMDWS($sTrackingID=NULL)
    {
        try
        {
            if($sTrackingID == NULL)
            {
                throw new \Exception('Missing required TrackingID!');
            }
            //Get the IEN from the tracking ID
            $aParts = (explode('-',$sTrackingID));
            if(count($aParts) == 2)
            {
                $nIEN = $aParts[1]; //siteid-IEN
            } else 
            if(count($aParts) == 1)     
            {
                $nIEN = $aParts[0]; //Just IEN
            } else {
                $sMsg = 'Worklist did NOT recognize format of tracking id ['.$sTrackingID.'] expected SiteID-IEN format!';
                error_log($sMsg);
                throw new \Exception($sMsg);
            }

            $mdwsDao = $this->m_oContext->getEhrDao()->getImplementationInstance();
            $aResult = \raptor_mdwsvista\MdwsUtils::parseDdrGetsEntryInternalAndExternal($mdwsDao->makeQuery("ddrGetsEntry", array(
                'file'=>'75.1', 
                'iens'=>($nIEN.','),
                'flds'=>'*', 
                'flags'=>'IEN'
                )));
            return $aResult;
        } catch (\Exception $ex) {
            error_log("Failed getWorklistItemFromMDWS because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    public static function formatSSN($digits)
    {
        if($digits != NULL && strlen($digits) == 9)
        {
            return $digits[0] . $digits[1] . $digits[2] 
                    . '-' . $digits[3] . $digits[4] 
                    . '-' . $digits[5] . $digits[6] . $digits[7] . $digits[8];
        }
        return $digits;
    }
    
    /**
     * @description return standard-formatted patient's name (lAST_NAME, FIRST_NAME), based on the specified full name
     */
    private function formatPatientName($fullName)
    {
        $nameArray = explode(',', $fullName);
        return $nameArray[0].", ".$nameArray[1];
    }
    
    /**
     * Get all the worklist rows for the provided context
     * @return type array of rows for the worklist page
     */
    public function getWorklistRows($start_with_IEN=NULL, $match_this_IEN=NULL
            , $maxrows_per_query = WORKLIST_MAXROWS_PER_QUERY
            , $enough_rows_count=WORKLIST_ENOUGH_ROWS_COUNT)
    {
        try
        {
            module_load_include('php', 'raptor_datalayer', 'core/TicketTrackingData');

            if($start_with_IEN === NULL)    //Force STRICT check here!!!!
            {
                $start_from_IEN = '';
            } else {
                if(!is_numeric($start_with_IEN))
                {
                    throw new \Exception("The starting IEN declaration must be numeric but instead we got ".print_r($start_with_IEN,TRUE));
                }
                $start_from_IEN = intval($start_with_IEN) + 1; //So we really start there
            }
            
            //Query several times
            $iterations = 0;
            $max_loops = WORKLIST_MAX_QUERY_LOOPS;
            $all_worklist_rows_raw_text_ar = array();
            $mdwsResponse = $this->getWorklistFromMDWS($start_from_IEN, $maxrows_per_query);
//error_log("LOOK worklist testing first query result >>>" . print_r($mdwsResponse,TRUE));
            while($iterations < $max_loops && count($all_worklist_rows_raw_text_ar) < $enough_rows_count)
            {
                $iterations++;
                $row_count = $numOrders = isset($mdwsResponse->ddrListerResult) ? $mdwsResponse->ddrListerResult->count : 0;
                if($row_count == 0)
                {
                    //No need to continue
                    break;
                }
                $allrows = $mdwsResponse->ddrListerResult->text->string;
                $myrow = $allrows[0];   //First row is oldest order so far
                $row_ar = explode('^', $myrow);
                $tracking_id = $row_ar[self::WLVFO_TrackingID];
                $all_worklist_rows_raw_text_ar = array_merge($all_worklist_rows_raw_text_ar, $allrows);
//error_log("LOOK worklist testing iter $iterations myrow >>>" . print_r($myrow,TRUE));
                
                //Now query the next chunk
                if(OLDEST_WORKLIST_TICKET_ID && OLDEST_WORKLIST_TICKET_ID > $tracking_id)
                {
                    //We are done because we do not care about tickets older than OLDEST_WORKLIST_TICKET_ID
                    break;
                }
                $mdwsResponse = $this->getWorklistFromMDWS($tracking_id, $maxrows_per_query);
//error_log("LOOK worklist testing iter $iterations new result started at '$tracking_id' >>>" . print_r($mdwsResponse,TRUE));
            }
    //error_log("LOOK worklist testing all rows (looped $iterations) >>>" . print_r($all_worklist_rows_raw_text_ar,TRUE));
            $oTT = new \raptor\TicketTrackingData();
            $sqlResponse = $oTT->getConsolidatedWorklistTracking();
            if($match_this_IEN == NULL)
            {
                $match_this_IEN = $this->m_oContext->getSelectedTrackingID();
            }
            $parsedWorklist = $this->parseWorklistTextRows($all_worklist_rows_raw_text_ar, $sqlResponse, $match_this_IEN);

            
            $dataRows = $parsedWorklist['all_rows'];
            $pending_orders_map = $parsedWorklist['pending_orders_map'];
            $matching_offset = $parsedWorklist['matching_offset'];

            $aResult = array('Pages'=>1
                            ,'Page'=>1
                            ,'RowsPerPage'=>count($dataRows)
                            ,'started_with'=>$start_with_IEN
                            ,'DataRows'=>$dataRows
                            ,'matching_offset' => $matching_offset
                            ,'pending_orders_map' => $pending_orders_map
                );	
            return $aResult;
        } catch (\Exception $ex) {
            error_log("Failed getWorklistRows because $ex");
            throw $ex;
        }
    }
  
    public function getPendingOrdersMap()
    {
        $aResult = $this->getWorklistRows();
        return $aResult['pending_orders_map'];
    }
    
    /**
     * Gets dashboard details for the one ticket
     * Defaults to current session ticket if none is specified
     */
    public function getDashboardMap($override_match_this_ticket_IEN=NULL)
    {
//error_log("LOOK in getDashboardMap($override_match_this_IEN)...");
        try
        {
            if($override_match_this_ticket_IEN != NULL)
            {
                $match_this_IEN = $override_match_this_ticket_IEN;
            } else {
                $match_this_IEN = $this->m_oContext->getSelectedTrackingID();
            }
            if($match_this_IEN == '')
            {
                throw new \Exception("Cannot get a dashboard without specifying ticket id!");
            }

            $startIEN = NULL;
            $aResult = $this->getWorklistRows($startIEN,$match_this_IEN);
            $offset = $aResult['matching_offset'];
            $all_rows = $aResult['DataRows'];
            if($offset == '')
            {
                throw new \Exception("Did NOT find IEN=[$match_this_IEN] in worklist result!");
            }
            if(!array_key_exists($offset, $all_rows))
            {
                throw new \Exception("Did NOT find IEN=[$match_this_IEN] at offset=[$offset] in worklist result!");
            }
            $row = $all_rows[$offset];

            if($match_this_IEN == '')
            {
                throw new \Exception("Cannot get dashboard($match_this_IEN) data when "
                        . 'current tracking id is not set!  '
                        . 'Your session may have timed out.');
            }
            if(!isset($row[\raptor\WorklistColumnMap::WLIDX_TRACKINGID]))
            {
                $msg = 'Expected to get value in WLIDX_TRACKINGID of dashboard for trackingID=['
                        .$match_this_IEN.'] but did not!';
                error_log($msg.">>>row details=".print_r($row, TRUE));
                throw new \Exception($msg);
            }
            if($match_this_IEN != $row[\raptor\WorklistColumnMap::WLIDX_TRACKINGID])
            {
                $msg = 'Expected to get dashboard for trackingID=['
                        .$match_this_IEN.'] but got data for ['
                        .$row[\raptor\WorklistColumnMap::WLIDX_TRACKINGID].'] instead!';
                error_log($msg.">>>row details=".print_r($row, TRUE));
                throw new \Exception($msg);
            }

            $siteid = $this->m_oContext->getSiteID();
            $tid = $row[\raptor\WorklistColumnMap::WLIDX_TRACKINGID];
            $pid = $row[\raptor\WorklistColumnMap::WLIDX_PATIENTID];
            $oPatientData = $this->getPatient($pid);
            if($oPatientData == NULL)
            {
                $msg = 'Did not get patient data of pid='.$pid
                        .' for trackingID=['.$currentTrackingID.']';
                error_log($msg.">>>instance details=".print_r($this, TRUE));
                throw new \Exception($msg);
            }

            // use DDR GETS ENTRY to fetch CLINICAL Hx WP field
            $worklistItemDict = $this->getWorklistItemFromMDWS($tid);
            $orderFileIen = $worklistItemDict['7']['I'];
            $mdwsDao = $this->m_oContext->getEhrDao()->getImplementationInstance();
            /*
            $orderFileRec = \raptor_mdwsvista\MdwsUtils::parseDdrGetsEntryInternalAndExternal
               ($this->m_oContext->getMdwsClient()->makeQuery('ddrGetsEntry', array(
                   'file'=>'100', 
                   'iens'=>($orderFileIen.','),
                   'flds'=>'*', 
                   'flags'=>'IEN'
               )));
             */
            $orderFileRec = \raptor_mdwsvista\MdwsUtils::parseDdrGetsEntryInternalAndExternal
               ($mdwsDao->makeQuery('ddrGetsEntry', array(
                   'file'=>'100', 
                   'iens'=>($orderFileIen.','),
                   'flds'=>'*', 
                   'flags'=>'IEN'
               )));
            //error_log("LOOK result on ticket=$currentTrackingID of file 100 search for $orderFileIen >>>>" . print_r($orderFileRec,TRUE));
            $t['orderingPhysicianDuz'] = $worklistItemDict['14']['I']; // get internal value of ordering provider field
            $t['orderFileStatus'] = isset($orderFileRec['5']['E']) ? $orderFileRec['5']['E'] : NULL;
            // 3/11/15 JAM - helper boolean for cancelng order GUI
            // 5 => PENDING, 11 => UNRELEASED
            $t['canOrderBeDCd'] = $worklistItemDict['5']['I'] == '5' || $worklistItemDict['5']['I'] == '11';
            // end 3/11/15 JAM
            $t['orderActive'] = !key_exists('63', $orderFileRec); // field 63 in file 100 is discontinue date/time
            // may be more to return here in the future

            $t['Tracking ID']       = $siteid.'-'.$tid;
            $t['Procedure']         = $row[\raptor\WorklistColumnMap::WLIDX_STUDY];
            $t['Modality']          = $row[\raptor\WorklistColumnMap::WLIDX_MODALITY];

            $t['ExamCategory']      = $row[\raptor\WorklistColumnMap::WLIDX_PATIENTCATEGORYLOCATION];
            $t['PatientLocation']   = $row[\raptor\WorklistColumnMap::WLIDX_EXAMLOCATION]; //DEPRECATED 1/29/2015      
            $t['RequestedBy']       = $row[\raptor\WorklistColumnMap::WLIDX_REQUESTINGPHYSICIAN];

            $t['RequestingLocation']= trim((isset($worklistItemDict['22']['I']) ? $worklistItemDict['22']['I'] : '') );
            $t['SubmitToLocation']  = trim((isset($worklistItemDict['20']['I']) ? $worklistItemDict['20']['I'] : '') );

            $aSchedInfo = $row[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO];
            $t['SchedInfo']         = $aSchedInfo;
            $t['RequestedDate']     = $row[\raptor\WorklistColumnMap::WLIDX_DATEORDERED]; 
            $t['DesiredDate']       = $row[\raptor\WorklistColumnMap::WLIDX_DATETIMEDESIRED]; 
            $t['ScheduledDate']     = $aSchedInfo['EventDT'];

            $t['PatientCategory']   = $row[\raptor\WorklistColumnMap::WLIDX_PATIENTCATEGORYLOCATION];
            
            // changed reason for study to real RFS, added 'NatureOfOrderActivity' key 
            $t['ReasonForStudy']    = trim((isset($worklistItemDict['1.1']['I']) ? $worklistItemDict['1.1']['I'] : '') );
            $t['NatureOfOrderActivity'] = $row[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY];

            $t['RequestingLocation'] = trim((isset($worklistItemDict['22']['E']) ? $worklistItemDict['22']['E'] : '') );
            $t['RequestingLocationIen'] = trim((isset($worklistItemDict['22']['I']) ? $worklistItemDict['22']['I'] : '') );

            $t['ClinicalHistory']   = trim((isset($worklistItemDict['400']) ? $worklistItemDict['400'] : '') );
            $t['PatientID']         = $pid;
            $t['PatientSSN']        = WorklistData::formatSSN($oPatientData['ssn']);
            $t['Urgency']           = $row[\raptor\WorklistColumnMap::WLIDX_URGENCY];
            $t['Transport']         = $row[\raptor\WorklistColumnMap::WLIDX_TRANSPORT];
            $t['PatientName']       = $row[\raptor\WorklistColumnMap::WLIDX_PATIENTNAME];
            $t['PatientAge']        = $oPatientData['age'];
            $t['PatientDOB']        = $oPatientData['dob'];
            $t['PatientEthnicity']  = $oPatientData['ethnicity'];
            $t['PatientGender']     = $oPatientData['gender'];
            $t['ImageType']         = $row[\raptor\WorklistColumnMap::WLIDX_IMAGETYPE];
            $t['mpiPid']            = $oPatientData['mpiPid'];
            $t['mpiChecksum']       = $oPatientData['mpiChecksum'];
            $t['CountPendingOrders']   = $row[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT];
            $t['DEPRECATE_MapPendingOrders']     = $row[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT];
            $t['OrderFileIen']          = $row[\raptor\WorklistColumnMap::WLIDX_ORDERFILEIEN];
            $t['RadiologyOrderStatus']  = $row[\raptor\WorklistColumnMap::WLIDX_EHR_RADIOLOGYORDERSTATUS];

            return $t;
            
        } catch (\Exception $ex) {
            error_log("Failed getDashboardMap because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * @description Call EMR web service method "select" passing as an argument PatientID stored in context
     * @return Array containing patient information 
     * This is an old function but appears to be in use; moved out of Context class on 5/21/2014
     */
    public function getPatient($pid)
    {
        if(!isset($pid) || $pid == NULL || $pid == '')
        {
            //Changed to throw exception on 20150605
            throw new \Exception('Missing required parameter value in call to getPatient!');
        }
        
        try
        {
            //$serviceResponse = $this->getEMRService()->select(array('DFN'=>$pid == null ? $this->getPatientID() : $pid));
            $mdwsDao = $this->m_oContext->getEhrDao()->getImplementationInstance();
            $serviceResponse = $mdwsDao->makeQuery("select", array('DFN'=>$pid));
            //$serviceResponse = $this->m_oContext->getMdwsClient()->makeQuery("select", array('DFN'=>$pid));
            //drupal_set_message('LOOK DFN RESULT>>>>' . print_r($serviceResponse, TRUE));

            $result = array();
            if(!isset($serviceResponse->selectResult))
                    return $result;

            $RptTO = $serviceResponse->selectResult;
            if(isset($RptTO->fault))
            { 
                return $result;
            }
            $result['patientName'] = isset($RptTO->name) ? $RptTO->name : " ";
            $result['ssn'] = isset($RptTO->ssn) ? $RptTO->ssn : " ";
            $result['gender'] = isset($RptTO->gender) ? $RptTO->gender : " ";
            $result['dob'] = isset($RptTO->dob) ? date("m/d/Y", strtotime($RptTO->dob)) : " ";
            $result['ethnicity'] = isset($RptTO->ethnicity) ? $RptTO->ethnicity : " ";
            $result['age'] = isset($RptTO->age) ? $RptTO->age : " ";
            $result['maritalStatus'] = isset($RptTO->maritalStatus) ? $RptTO->maritalStatus : " ";
            $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : " ";
            $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : " ";
            //deprecated 20150911 $result['localPid'] = isset($RptTO->localPid) ? $RptTO->localPid : " ";
            $result['sitePids'] = isset($RptTO->sitePids) ? $RptTO->sitePids : " ";
            //deprecated 20150911 $result['vendorPid'] = isset($RptTO->vendorPid) ? $RptTO->vendorPid : " ";
            if(isset($RptTO->location))
            {
                $aLocation = $RptTO->location;
                $room = "Room: ";
                $room .=isset($aLocation->room)? $aLocation->room : " ";
                $bed =  "Bed: ";
                $bed .= (isset($aLocation->bed) ? $aLocation->bed : " " );
                $result['location'] = $room." / ".$bed;
            }
            else
            {
                $result['location'] = "Room:? / Bed:? ";
            }
            $result['cwad'] = isset($RptTO->cwad) ? $RptTO->cwad : " ";
            $result['restricted'] = isset($RptTO->restricted) ? $RptTO->restricted : " ";

            $result['admitTimestamp'] = isset($RptTO->admitTimestamp) ? date("m/d/Y h:i a", strtotime($RptTO->admitTimestamp)) : " ";

            $result['serviceConnected'] = isset($RptTO->serviceConnected) ? $RptTO->serviceConnected : " ";
            $result['scPercent'] = isset($RptTO->scPercent) ? $RptTO->scPercent : " ";
            $result['inpatient'] = isset($RptTO->inpatient) ? $RptTO->inpatient : " ";
            $result['deceasedDate'] = isset($RptTO->deceasedDate) ? $RptTO->deceasedDate : " ";
            $result['confidentiality'] = isset($RptTO->confidentiality) ? $RptTO->confidentiality : " ";
            $result['needsMeansTest'] = isset($RptTO->needsMeansTest) ? $RptTO->needsMeansTest : " ";
            $result['patientFlags'] = isset($RptTO->patientFlags) ? $RptTO->patientFlags : " ";
            $result['cmorSiteId'] = isset($RptTO->cmorSiteId) ? $RptTO->cmorSiteId : " ";
            //deprecated 20150911 $result['activeInsurance'] = isset($RptTO->activeInsurance) ? $RptTO->activeInsurance : " ";
            $result['isTestPatient'] = isset($RptTO->isTestPatient) ? $RptTO->isTestPatient : " ";
            $result['currentMeansStatus'] = isset($RptTO->currentMeansStatus) ? $RptTO->currentMeansStatus : " ";
            $result['hasInsurance'] = isset($RptTO->hasInsurance) ? $RptTO->hasInsurance : " ";
            //deprecated 20150911 $result['preferredFacility'] = isset($RptTO->preferredFacility) ? $RptTO->preferredFacility : " ";
            $result['patientType'] = isset($RptTO->patientType) ? $RptTO->patientType : " ";
            $result['isVeteran'] = isset($RptTO->isVeteran) ? $RptTO->isVeteran : " ";
            $result['isLocallyAssignedMpiPid'] = isset($RptTO->isLocallyAssignedMpiPid) ? $RptTO->isLocallyAssignedMpiPid : " ";
            $result['sites'] = isset($RptTO->sites) ? $RptTO->sites : " ";
            //deprecated 20150911 $result['teamID'] = isset($RptTO->teamID) ? $RptTO->teamID : " ";
            $result['teamName'] = isset($RptTO->name) ? $RptTO->name : "Unknown";
            $result['teamPcpName'] = isset($RptTO->pcpName) ? $RptTO->pcpName : "Unknown";
            $result['teamAttendingName'] = isset($RptTO->attendingName) ? $RptTO->attendingName : "Unknown";
            $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : "Unknown";
            $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : "Unknown";

            return $result;
        } catch (\Exception $ex) {
            error_log("Failed getPatient because ".$ex->getMessage());
            throw $ex;
        }
    }
}

