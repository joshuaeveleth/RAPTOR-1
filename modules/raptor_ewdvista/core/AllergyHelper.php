<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Alex Podlesny, et al
 * EWD Integration and VISTA collaboration: Joel Mewton, Rob Tweed
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

namespace raptor_ewdvista;

require_once 'EwdUtils.php';

/**
 * Helper for returning allergy content
 *
 * @author Frank Font of SAN Business Consultants
 */
class AllergyHelper
{
    
    //Declare the field numbers
    //private static $FLDNM_FACILITY = 'facility';
    //private static $FLDNM_FAC_NAME = 'name';
    //private static $FLDNM_FAC_ID = 'id';
    //private static $FLDNM_DRUGCLASES = 'drugClasses'; NOT returned by EWD!
    //private static $FLDNM_DRU_NAME = 'name';
    private static $FLDNM_ALLERGEN_NAME = 'allergenName';
    private static $FLDNM_ALLERGEN_TYPE = 'allergenType';
    private static $FLDNM_REPORTED_TS = 'timestamp';
    private static $FLDNM_TYPE = 'type';
    private static $FLDNM_TYP_NAME = 'name';
    //private static $FLDNM_TYP_CATEGORY = 'category';
    private static $FLDNM_REACTIONS = 'reactions';
    private static $FLDNM_REA_NAME = 'name';
    
    private function getSnipDetailArray($rawitem, $containerfieldname, $valuefieldname, $sublevels=1)
    {
        try
        {
            if(!isset($rawitem[$containerfieldname]))
            {
                $final_ar = array();
            } else {
                $onetype = $rawitem[$containerfieldname];
                if($sublevels==1)
                {
                    if(!isset($onetype[$valuefieldname]))
                    {
                        $rawvalue = NULL;
                    } else {
                        $rawvalue = $onetype[$valuefieldname];
                    }
                } else {
                    if(!is_array($onetype) || count($onetype) == 0)
                    {
                        $rawvalue = NULL;
                    } else {
                        $raw_ar = array();
                        foreach($onetype as $oneitem)
                        {
                            if(!isset($oneitem[$valuefieldname]))
                            {
                                break;
                            }
                            $raw_ar[] = $oneitem[$valuefieldname];
                        }
                        if(count($raw_ar) == 0)
                        {
                            $rawvalue = NULL;
                        } else {
                            $rawvalue = implode(', ', $raw_ar);
                        }
                    }
                }
                if($rawvalue != NULL)
                {
                    $det = $rawvalue;
                    if(strlen($det) > RAPTOR_DEFAULT_SNIPPET_LEN)
                    {
                        $snip = substr($det,0,RAPTOR_DEFAULT_SNIPPET_LEN);
                        $same = FALSE;
                    } else {
                        $snip = $det;
                        $same = TRUE;
                    }
                } else {
                    $det = NULL;
                    $snip = NULL;
                    $same = TRUE;
                }
                $final_ar = [
                          'Snippet'=>$snip
                        , 'Details'=>$det
                        , 'SnippetSameAsDetail'=>$same];
            }
            return $final_ar;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * If atriskmeds is not null, it should be an array of medication names
     */
    public function getFormattedAllergyDetail($rawresult_ar)
    {
        try
        {
            if(!is_array($rawresult_ar))
            {
                throw new \Exception("Cannot format a non-array of data!");
            }
            
            $bundle = array();
            foreach($rawresult_ar as $rawitem)
            {
                $tsreported = trim($rawitem[self::$FLDNM_REPORTED_TS]);
                $tsparts = explode(' ',$tsreported);
                $datereported = $tsparts[0];
                //$drugclasses_ar = $this->getSnipDetailArray($rawitem, self::$FLDNM_DRUGCLASES, self::$FLDNM_DRU_NAME, 2);
                $reactions_ar = $this->getSnipDetailArray($rawitem, self::$FLDNM_REACTIONS, self::$FLDNM_REA_NAME, 2);
                $historical_ar = $this->getSnipDetailArray($rawitem, self::$FLDNM_TYPE, self::$FLDNM_TYP_NAME);
                $cleanitem = array(
                    'DateReported' => $datereported,
                    'Item' => $rawitem[self::$FLDNM_ALLERGEN_NAME],
                    'CausativeAgent' => $rawitem[self::$FLDNM_ALLERGEN_TYPE],
                    'SignsSymptoms' => $reactions_ar,
                    //'DrugClasses' => $drugclasses_ar,
                    'ObservedHistorical' => $historical_ar,
                );
                $bundle[] = $cleanitem;
            }
            return $bundle;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}
