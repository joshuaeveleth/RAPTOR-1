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

require_once 'AReport.php';

/**
 * This class returns the configuration details
 *
 * @author Frank Font of SAN Business Consultants
 */
class ViewEhrDaoPerformance extends AReport
{
    private static $reqprivs = array('special_privs'=>array(ENABLE_RAPTOR_PERFORMANCE_TUNING_SPECIAL_PRIV));
    private static $menukey = 'raptor/showehrdaoperformance';
    private static $reportname = 'Show EHR DAO Performance Details';

    private $m_oEDRM = NULL;
    
    function __construct()
    {
        parent::__construct(self::$reqprivs, self::$menukey, self::$reportname, TRUE);
        
        $loaded1 = module_load_include('php', 'raptor_datalayer', 'core/EhrDaoRuntimeMetrics');
        if(!$loaded1)
        {
            $msg = 'Failed to load EhrDaoRuntimeMetrics';
            throw new \Exception($msg);      //This is fatal, so stop everything now.
        }
        $this->m_oEDRM = new \raptor\EhrDaoRuntimeMetrics();
    }
    
    public function getDescription() 
    {
        return 'Shows detailed EHR DAO performance results by making calls at runtime as the logged in user.';
    }

    /**
     * Get the values to populate the form.
     * @return type result of the queries as an array
     */
    function getFieldValues($myvalues = NULL)
    {
        $getfieldvalues_starttime = microtime(TRUE);
        $myvalues['filename_insert'] = NULL;
        if($myvalues == NULL)
        {
            $myvalues = array();
        }
        try
        {
            $bundle = array();
            $bundle['DAO'] = array();
            $iterations = isset($myvalues['iterations']) ? trim($myvalues['iterations']) : '1';
            $tickets_for_test = isset($myvalues['tickets_for_test']) ? trim($myvalues['tickets_for_test']) : '';
            $patientlist = array();    //We will get these FROM the result
            if(strlen($tickets_for_test) > 3)
            {
                $checkcmd = strtoupper(trim($tickets_for_test));
                $cmdparts = explode(':',$checkcmd);
                $part_count = count($cmdparts);
                if($cmdparts[0] != 'GET')
                { 
                    //Create a meaningful insert for the download filename here
                    $ticketlist = explode(',',$tickets_for_test);
                    $filename_insert = "iters{$iterations}_x".count($ticketlist);
                } else {
                    //Second param is number of tickets to grab
                    if(!is_numeric($cmdparts[1]))
                    {
                        throw new \Exception("Expected INTEGER in offset 1 of ".print_r($cmdparts,TRUE));
                    }
                    $limit = intval($cmdparts[1]);
                    $startafter = NULL;
                    if($part_count > 2)
                    {
                        if($cmdparts[2] != 'AFTER')
                        {
                            throw new \Exception("Expected AFTER in offset 2 of ".print_r($cmdparts,TRUE));
                        }
                        if(!is_numeric($cmdparts[3]))
                        {
                            throw new \Exception("Expected INTEGER in offset 3 of ".print_r($cmdparts,TRUE));
                        }
                        $startafter = intval($cmdparts[3]);
                    } 
                    $realtickets = $this->m_oEDRM->getRealTickets($limit,$startafter);
                    $tickets_for_test = '';
                    foreach($realtickets as $tid=>$details)
                    {
                        if($tickets_for_test > '')
                        {
                            $tickets_for_test .= ',';
                        }
                        $tickets_for_test .= $tid;
                    }
                    $myvalues['tickets_for_test'] = $tickets_for_test;
                    
                    //Create a meaningful insert for the download filename
                    $filename_insert = "iters{$iterations}_get{$limit}";
                    if($startafter != NULL)
                    {
                        $filename_insert .= "_after{$startafter}";
                    }
                }
                $myvalues['filename_insert'] = $filename_insert;
            }
            $available_filter_options_ar = $this->m_oEDRM->getMetricFilterOptions();
            $bundle['available_filter_options'] = implode(',',$available_filter_options_ar);
            $selected_filters = isset($myvalues['selected_filters']) ? $myvalues['selected_filters'] : $bundle['available_filter_options'];
            $selected_filters_ar = explode(',',$selected_filters);
            $bundle['selected_filters'] = $selected_filters;
            if($tickets_for_test > '')
            {
                $rawticketlist = explode(',',$tickets_for_test);
                //Important that we CLEAN THIS UP now!
                $ticketlist = array();
                foreach($rawticketlist as $onetid)
                {
                    $ticketlist[] = trim($onetid);  //Whitespace kills!
                }
            } else {
                $ticketlist = array();
            }
            global $user;
            error_log("Started getting values for ViewEhrDaoPerformance from site ".VISTA_SITE." as user {$user->uid} " 
                    . "\n\ttickets = $tickets_for_test"
                    . "\n\tfilters = $selected_filters"
                    . "\n\titerations = $iterations");
            $bundle['ticketlist'] = $ticketlist;
            $biggestsize_item = array(); 
            $biggestsize_item['resultsize'] = -1;
            $slowest_item = array(); 
            $slowest_item['duration'] = 0; 
            $total_action_duration = array();
            $total_action_size = array();
            $total_good_rows = 0;
            $action_names_map = array();
            $rowdata = array();
            $enhancedrows = array();
            $ticketstats = array();
            $prevdaoinfo = NULL;
            $total_error_count = 0;
            $total_skip_count = 0;
            $error_detail_truncations = 0;
            $exec_order = 0;
            for($iteration=1; $iteration <= $iterations; $iteration++)
            {
                try
                {
                    $rawdetails = $this->m_oEDRM->getPerformanceDetails($ticketlist,$selected_filters_ar);
                } catch (\Exception $ex) {
                    throw new \Exception("Failed to get performance details at iteration $iteration",99876,$ex);
                }
                $daoinfo = $rawdetails['DAO'];
                if($prevdaoinfo != $daoinfo)
                {
                    //Add this one because it changed
                    $bundle['DAO'][$iteration] = $rawdetails['DAO'];
                    $prevdaoinfo = $daoinfo;
                }
                $ticketstats[$iteration] = $rawdetails['ticketstats'];
                foreach($ticketstats[$iteration] as $oneticketstat)
                {
                    $errs = $oneticketstat['error_count'];
                    if($errs > 0)
                    {
                        $total_error_count += $errs;
                    }
                }
                $metrics = $rawdetails['metrics'];
                $seqnum = 0;
                foreach($metrics as $onetest)
                {
                    $actionname = $onetest['metadata']['methodname'];
                    if(isset($onetest['failed_info']))
                    {
                        $ex = $onetest['failed_info'];
                        $has_error = 'YES';
                    } else {
                        $ex = NULL;
                        $has_error = 'NO';
                    }
                    if(isset($onetest['skipped_info']) && $onetest['skipped_info'] > '')
                    {
                        $skipped_info = $onetest['skipped_info'];
                        $total_skip_count++;
                    } else {
                        //Not skipped
                        $skipped_info = NULL;
                    }
                    
                    $seqnum++;
                    $error_detail = "$ex";
                    if(strlen($error_detail) > 10000)
                    {
                        $error_detail_truncations++;
                        //Let the first few get captured completely
                        if($error_detail_truncations < 3)
                        {
                            if(strlen($error_detail) > 100000)  //100k
                            {
                                //Prevent out of memory!
                                $error_detail = substr($error_detail,0,100000) . '...'; //100k
                            }
                        } else {
                            //Prevent out of memory!
                            $error_detail = substr($error_detail,0,10000) . '...';
                        }
                    }
                    $rowdata[] = array(
                        'iteration'=>$iteration,
                        'seqnum' => $seqnum,
                        'has_error'=>$has_error,
                        'error_detail'=>$error_detail,
                        'tracking_id'=>$onetest['tracking_id'],
                        'patient_id'=>$onetest['patient_id'],
                        'start_ts'=>$onetest['start_ts'],
                        'end_ts'=>$onetest['end_ts'],
                        'action'=>$actionname,
                        'duration'=>$onetest['end_ts']-$onetest['start_ts'],
                        'resultsize'=>$onetest['resultsize'],
                        'skipped_info'=>$skipped_info,
                        'paramvalues'=>$onetest['paramvalues'],
                    );
                }
                //Compute statistics for actions that did not fail.
                foreach($rowdata as $onerow)
                {
                    if($onerow['has_error'] == 'NO')
                    {
                        //If we are here, then the test did not fail.
                        $total_good_rows++;
                        $action_name = $onerow['action'];
                        if(!isset($action_names_map[$action_name]))
                        {
                            $action_names_map[$action_name] = 1;
                        } else {
                            $action_names_map[$action_name] = $action_names_map[$action_name] + 1;
                        }
                        $duration = $onerow['duration'];
                        $resultsize = $onerow['resultsize'];
                        if($onerow['resultsize'] > $biggestsize_item['resultsize'])
                        {
                            $biggestsize_item['resultsize'] = $onerow['resultsize']; 
                            $biggestsize_item['itemdetails'] = $onerow; 
                        }
                        if($onerow['duration'] > $slowest_item['duration'])
                        {
                            $slowest_item['duration'] = $onerow['duration']; 
                            $slowest_item['itemdetails'] = $onerow; 
                        }
                        if(!isset($total_action_duration[$action_name]))
                        {
                            $total_action_duration[$action_name] = $duration;
                        } else {
                            $total_action_duration[$action_name] = $total_action_duration[$action_name] + $duration;
                        }
                        if(!isset($total_action_size[$action_name]))
                        {
                            $total_action_size[$action_name] = $resultsize;
                        } else {
                            $total_action_size[$action_name] = $total_action_size[$action_name] + $resultsize;
                        }
                    }
                }
            }
            //Compute averages
            $avg_action_duration = array();
            $avg_action_size = array();
            foreach($action_names_map as $action_name=>$occurance_count)
            {
                if($occurance_count > 0)
                {
                    $avg_action_duration[$action_name] = $total_action_duration[$action_name] / $occurance_count;
                    $avg_action_size[$action_name] = $total_action_size[$action_name] / $occurance_count;
                }
            }
            //Enhance the row content
            foreach($rowdata as $onerow)
            {
                $iteration = $onerow['iteration'];
                $tid = $onerow['tracking_id'];
                $pid = $onerow['patient_id'];
                if(!in_array($pid, $patientlist))
                {
                    $patientlist[] = $pid;
                }
                $action_name = $onerow['action'];
                if($onerow['has_error'] == 'NO')
                {
                    $thisticketduration = $ticketstats[$iteration][$tid]['duration'];
                    $duration = $onerow['duration'];
                    $duration_delta = $duration - $avg_action_duration[$action_name];
                    if($thisticketduration > 0)
                    {
                        $duration_pct = 100 * $duration / $thisticketduration;
                    } else {
                        $duration_pct = NULL;
                    }
                    $resultsize = $onerow['resultsize'];
                    $resultsize_delta = $resultsize - $avg_action_size[$action_name];
                } else {
                    $thisticketduration = NULL;
                    $duration = NULL;
                    $duration_delta = NULL;
                    $resultsize = NULL;
                    $resultsize_delta = NULL;
                    $duration_pct = NULL;
                }

                $onerow['duration_delta'] = $duration_delta;
                $onerow['resultsize_delta'] = $resultsize_delta;
                if($resultsize > 0 && $duration > 0)
                {
                    if($resultsize < 10000)
                    {
                        //Improve numerical accuracy of result
                        $normalized_duration = 1000000 * (10000 * $duration) / (10000 * $resultsize);
                    } else {
                        $normalized_duration = 1000000 * ($duration / $resultsize);
                    }
                    $onerow['duration_per_1MB'] = $normalized_duration;
                } else {
                    $onerow['duration_per_1MB'] = '';
                }
                $onerow['duration_pct'] = $duration_pct;
                $onerow['thisticketduration'] = $thisticketduration;
                $enhancedrows[] = $onerow;
            }
            $bundle['iterations'] = $iterations;
            $bundle['total_error_count'] = $total_error_count;
            $bundle['total_skip_count'] = $total_skip_count;
            $bundle['stats'] 
                    = array('avg_action_duration'=>$avg_action_duration
                           ,'avg_action_size'=>$avg_action_size
                           ,'total_action_duration'=>$total_action_duration
                           ,'biggestsize_item'=>$biggestsize_item
                           ,'slowest_item'=>$slowest_item
                    );
            $bundle['ticketstats'] = $ticketstats;
            $bundle['tickets_for_test'] = $tickets_for_test;
            $bundle['patientlist'] = $patientlist;
            $bundle['rowdata'] = $enhancedrows;
            $getfieldvalues_endtime = microtime(TRUE);
            $bundle['getvalues_duration'] = $getfieldvalues_endtime - $getfieldvalues_starttime;;
            $myvalues['reportdata'] = $bundle;
            error_log("Finished getting values for ViewEhrDaoPerformance from site ".VISTA_SITE." as user {$user->uid} " 
                    . "\n\ttickets = $tickets_for_test"
                    . "\n\tfilters = $selected_filters"
                    . "\n\titerations = $iterations"
                    . "\n\ttotal skipped = $total_skip_count"
                    . "\n\ttotal errors = $total_error_count");
            return $myvalues;
        } catch (\Exception $ex) {
            throw new \Exception("Failed to get field values because $ex",99876,$ex);
        }
    }
	
    function getDownloadTypes()
    {
        $supported = array();
        $supported['CSV'] = array();
        $supported['CSV']['helptext'] = 'CSV files can be opened and analyzed in Excel';
        $supported['CSV']['downloadurl'] = $this->getDownloadURL('CSV');
        $supported['CSV']['linktext'] = 'Download detail to a CSV file';
        $supported['CSV']['delimiter'] = ",";

        $supported['TXT'] = array();
        $supported['TXT']['helptext'] = 'Tab delimited text files can be opened and analyzed in Excel';
        $supported['TXT']['downloadurl'] = $this->getDownloadURL('TXT');
        $supported['TXT']['linktext'] = 'Download detail to a tab delimited text file';
        $supported['TXT']['delimiter'] = "\t";
        
        return $supported;
    }
    
    
    private function formatBytes($bytes, $precision = 2) 
    {
        try
        {
            if($bytes < 0)
            {
                return NULL;
            }
            $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

            $bytes = max($bytes, 0); 
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
            $pow = min($pow, count($units) - 1); 
            if($pow < 0)
            {
                return "ERR pow=$pow for input ($bytes, $precision)";
            }
            // Uncomment one of the following alternatives
            $bytes /= pow(1024, $pow);
            // $bytes /= (1 << (10 * $pow)); 

            return round($bytes, $precision) . ' ' . $units[$pow]; 
        } catch (\Exception $ex) {
            return "ERR";
        }
    }     
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $now_dt = date("Y-m-d H:i:s", time());
        $reportdata = $myvalues['reportdata'];
        $getvalues_duration = $reportdata['getvalues_duration'];
        $iterations = isset($reportdata['iterations']) ? $reportdata['iterations'] : '1';
        $tickets_for_test = isset($reportdata['tickets_for_test']) ? $reportdata['tickets_for_test'] : '';
        $patientlist = isset($reportdata['patientlist']) ? $reportdata['patientlist'] : array();
        $selected_filters = isset($reportdata['selected_filters']) ? $reportdata['selected_filters'] : '';
        $available_filter_options = isset($reportdata['available_filter_options']) ? $reportdata['available_filter_options'] : 'MISSING';
        $headertext = array('i#'=>'Iteration number',
            's#'=>'Sequence number within the iteration',
            'TrackingID'=>'Ticket tracking number',
            'PatientID'=>'Patient tracking number at end of action',
            'Start Time'=>'Start time of the action',
            'End Time'=>'End time of the action',
            'Action Name'=>'The action that took place',
            'Duration'=>'Number of seconds duration for this action',
            'Delta from Ave Duration'=>'Difference from average duration',
            'Result Size'=>'Approximate size of action result',
            'Delta from Avg Size'=>'Difference from average size',
            'Normalized Duration'=>'Duration to get result per 1MB',
            '% Duration'=>'Percentage of total ticket duration due to this action',
            'All Duration'=>'Total duration of all executed actions of this ticket');

        $form['data_entry_area1'] = array(
            '#prefix' => "\n<section class='user-admin raptor-dialog-table'>\n",
            '#suffix' => "\n</section>\n",
        );
        $form['data_entry_area1']['context']['blurb'] = array('#type' => 'item',
                '#markup' => '<p>Raptor Site '.VISTA_SITE.' as of '.$now_dt."</p>", 
            );

        $keyparams = array();
        $keyparams['tickets_for_test'] = $tickets_for_test;
        $keyparams['iterations'] = $iterations;
        $keyparams['selected_filters'] = $selected_filters;
        $downloadlinks = $this->getDownloadLinksMarkup($keyparams);
        if(count($downloadlinks) > 0)
        {
            $markup = implode(' | ',$downloadlinks);
            $form['data_entry_area1']['context']['exportlink'][] = array(
                '#markup' => "<p>$markup</p>"
                );
        }
        
        $form['data_entry_area1']['table_container'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-dialog-table-container">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );

        /*
        $form['data_entry_area1']['table_container']['debugstuff'] = array('#type' => 'item',
                '#markup' => '<h1>debug report data details</h1><pre>' 
                    . print_r($reportdata,TRUE) 
                    . '<pre>'
            );
         * 
         */
        
        $rows = '';
        $total_error_count = $reportdata['total_error_count'];
        $total_skip_count = $reportdata['total_skip_count'];
        $reportstats = $reportdata['stats'];
        $biggestsize_amount = NULL;
        $biggestsize_action = NULL;
        $biggestsize_tid = NULL;
        $biggestsize_iteration = NULL;
        $slowest_amount = NULL;
        $slowest_action = NULL;
        $slowest_tid = NULL;
        $slowest_iteration = NULL;
        if(isset($reportstats['biggestsize_item']))
        {
            $biggestsize_item = $reportstats['biggestsize_item'];
            if(isset($biggestsize_item['itemdetails']))
            {
                $biggestsize_amount = $this->formatBytes($biggestsize_item['resultsize']);
                $biggestsize_action = $biggestsize_item['itemdetails']['action'];
                $biggestsize_tid = $biggestsize_item['itemdetails']['tracking_id'];
                $biggestsize_iteration = $biggestsize_item['itemdetails']['iteration'];
            }
        }
        if(isset($reportstats['slowest_item']))
        {
            $slowest_item = $reportstats['slowest_item'];
            if(isset($slowest_item['itemdetails']))
            {
                $slowest_amount = $slowest_item['duration'];
                $slowest_action = $slowest_item['itemdetails']['action'];
                $slowest_tid = $slowest_item['itemdetails']['tracking_id'];
                $slowest_iteration = $slowest_item['itemdetails']['iteration'];
            }
        }
        
        $errors_hit=0;
        $rowdata = $reportdata['rowdata'];
        foreach($rowdata as $onerow)
        {
            $iteration = $onerow['iteration'];
            $seqnum = $onerow['seqnum'];
            $action_name = $onerow['action'];
            $duration = $onerow['duration'];
            $duration_delta = $onerow['duration_delta'];
            $resultsize = $onerow['resultsize'];
            $resultsize_delta = $onerow['resultsize_delta'];
            $normalized_resultspeed = $onerow['duration_per_1MB'];
            $duration_pct = number_format($onerow['duration_pct'],2);
            $thisticketduration = $onerow['thisticketduration'];
            if($resultsize >= 0)
            {
                $nicesizetext = $this->formatBytes($resultsize);
            } else {
                $nicesizetext = 'Not a valid size!';
            }
            $nicesizedeltatext = $this->formatBytes($resultsize_delta);
            $tid = trim($onerow['tracking_id']);
            $tidmarkup = $tid;
            $extremetitle = '';
            if($tid == $slowest_tid || $tid == $biggestsize_tid)
            {
                $extreme_items = array();
                if($action_name == $slowest_action && $iteration == $slowest_iteration)
                {
                    $extreme_items[] = 'slowest';
                }
                if($action_name == $biggestsize_action && $iteration == $biggestsize_iteration)
                {
                    $extreme_items[] = 'biggest result';
                }
                if(count($extreme_items) > 0)
                {
                    $extremetitle = implode(',',$extreme_items);
                    $tidmarkup = "<b title='$action_name $extremetitle'>$tid</b>";
                }
            }
            $pid = trim($onerow['patient_id']);
            $paramstxt = $this->getParameterValuesAsText($onerow['paramvalues']);
            if($onerow['error_detail'] != NULL)
            {
                $errors_hit++;
                if($errors_hit > 5)
                {
                    //Avoid out of memory!!!
                    $errsubstr = substr($onerow['error_detail'],0,1000) . '...';
                    $action_name = "FAILED $action_name($paramstxt) {$errsubstr}";
                } else {
                    $action_name = "FAILED $action_name($paramstxt) {$onerow['error_detail']}";
                }
                
            } else if(isset($onerow['skipped_info']) && $onerow['skipped_info'] != NULL) {
                $action_name = "SKIPPED $action_name($paramstxt) because {$onerow['skipped_info']}";
            } else {
                $action_name = "$action_name($paramstxt)";
            }
            $rows .= '<tr>'
                    . "<td>{$iteration}</td>"
                    . "<td>{$seqnum}</td>"
                    . "<td>{$tidmarkup}</td>"
                    . "<td>{$pid}</td>"
                    . "<td>{$onerow['start_ts']}</td>"
                    . "<td>{$onerow['end_ts']}</td>"
                    . "<td>$action_name</td>"
                    . "<td>$duration</td>"
                    . "<td>$duration_delta</td>"
                    . "<td title='$nicesizetext'>$resultsize</td>"
                    . "<td title='$nicesizedeltatext'>$resultsize_delta</td>"
                    . "<td>$normalized_resultspeed</td>"
                    . "<td>$duration_pct</td>"
                    . "<td>$thisticketduration</td>"
                    . '</tr>';
        }

        $context_markup_ar = array();
        if($total_error_count>0)
        {
            $context_markup_ar[] = "<b>RUNTIME ERRORS : " . $total_error_count . "</b>";
        } else {
            $context_markup_ar[] = "Runtime errors : NONE";
        }
        if($total_skip_count>0)
        {
            $context_markup_ar[] = "<b>SKIPPED ACTIONS : " . $total_skip_count . "</b>";
        } else {
            $context_markup_ar[] = "Skipped actions : NONE";
        }
        if($getvalues_duration > 0)
        {
            //Strip off most of the decimals
            $getvalues_duration_clean = number_format($getvalues_duration,2);
        } else {
            //Don't strip anything off
            $getvalues_duration_clean = $getvalues_duration;
        }
        $context_markup_ar[] = "Total time to get report results: $getvalues_duration_clean seconds";
        $context_markup_ar[] = "Iterations in report run: " . $iterations;
        $context_markup_ar[] = "Selected Filters: " . print_r($selected_filters,TRUE);
        $ticketcount = 0;
        if(is_array($reportdata['ticketlist']))
        {
            $ticketlist = $reportdata['ticketlist'];
            $ticketcount = count($ticketlist);
            $context_markup_ar[] = "Total Tickets: " . $ticketcount;
            if($ticketcount > 0)
            {
                $context_markup_ar[] = "Tickets IDs Tested: " . implode(',',$ticketlist);
                $context_markup_ar[] = "Patient IDs in Test: " . implode(',',$patientlist);
            }
        }
        if($ticketcount > 0 && $total_error_count == 0)
        {
            if($biggestsize_action !== NULL)
            {
                $context_markup_ar[] = "Biggest result : " 
                        . $biggestsize_amount
                        . " found in " . $biggestsize_action 
                        . ' of ' . $biggestsize_tid
                        . ' at iteration '.$biggestsize_iteration;
            }
            if($slowest_action !== NULL)
            {
                $context_markup_ar[] = "Slowest result : " 
                        . $slowest_amount 
                        . " found in " . $slowest_action
                        . ' of ' . $slowest_tid
                        . ' at iteration '.$slowest_iteration;
            }
            if(is_array($reportstats))
            {
                foreach($reportstats as $key=>$value)
                {
                    $context_markup_ar[] = "Statistic detail of $key: " . print_r($value,TRUE);
                }
            }
        }
        $it=0;
        foreach($reportdata['DAO'] as $daodetail)
        {
            $it++;
            foreach($daodetail as $key=>$value)
            {
                $context_markup_ar[] = "DAO of iteration $it $key: $value";
            }
        }
        $form['data_entry_area1']['table_container']['daocontext'] 
                = array('#type' => 'item',
                '#markup' => '<h1>Result Context</h1>'
                    . '<ul><li>' . implode('</li><li>',$context_markup_ar) 
                    . '</ul>'
            );
        
        $headermarkup = '';
        foreach($headertext as $label=>$title)
        {
            $headermarkup .= "<th title='$title'>$label</th>";
        }
        $form['data_entry_area1']['table_container']['activity'] = array('#type' => 'item',
                 '#markup' => '<h2>Performance Details</h2>'
                            . '<table id="my-raptor-dialog-table" class="raptor-dialog-table dataTable">'
                            . '<thead><tr>'
                            . $headermarkup
                            . '</tr></thead>'
                            . '<tbody>'
                            . $rows
                            .  '</tbody>'
                            . '</table>');

        
        //Provide context options to the user
        $form['data_entry_area1']['selections']['iterations'] 
                = array('#type' => 'textfield',
                    '#title' => t('Number of iterations for the test'),
                    '#disabled' => $disabled,
                    '#size' => 2,
                    '#default_value' => $iterations,
            );
        $form['data_entry_area1']['selections']['selected_filters'] 
                = array('#type' => 'textfield',
                    '#title' => t('Function call groups to include'),
                    '#disabled' => $disabled,
                    '#size' => 100,
                    '#description' => "Available options are $available_filter_options",
                    '#default_value' => $selected_filters,
            );
        $form['data_entry_area1']['selections']['tickets_for_test'] 
                = array('#type' => 'textarea',
                    '#title' => t('Tickets for performance test use'),
                    '#rows' => 2,
                    '#disabled' => $disabled,
                    '#description' => "Provide comma delimited list of tickets to process or provide get:X:after:Y where X is number of tickets to process and Y is where to start grabbing them from the worklist.",
                    '#default_value' => $tickets_for_test,
            );
        
        $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );
       
        $form['data_entry_area1']['action_buttons']['refresh'] 
                = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'), 'id' => 'refresh-report')
                , '#value' => t('Refresh Report'));
        
        global $base_url;
        $goback = $base_url . '/raptor/viewReports';
        $form['data_entry_area1']['action_buttons']['cancel'] = $this->getExitButtonMarkup($goback);
        return $form;
    }
    
    private function getParameterValuesAsText($params,$max_len=25)
    {
        try
        {
            $paramstxt = '';
            if(is_array($params))
            {
                if($paramstxt > '')
                {
                    $paramstxt .= ', ';
                }
                foreach($params as $oneparam)
                {
                    if(is_array($oneparam))
                    {
                        $oneparamtxt = 'Array(size ' . count($oneparam) . ')';
                    } else if(is_object($oneparam)) {
                        $oneparamtxt = 'Object';
                    } else {
                        if(is_string($oneparam))
                        {
                            $oneparamtxt = $oneparam;
                        } else {
                            $oneparamtxt = print_r($oneparam,TRUE);
                        }
                    }
                    if(strlen($oneparamtxt) > $max_len)
                    {
                        $paramstxt .= strlen($oneparamtxt,0,$max_len-3) + '...';
                    } else {
                        $paramstxt .= $oneparamtxt;
                    }
                }
            }
            return $paramstxt;
        } catch (\Exception $ex) {
            throw new \Exception("Failed getParameterValuesAsText for ".print_r($params,TRUE),99888,$ex);
        }
    }
    
}
