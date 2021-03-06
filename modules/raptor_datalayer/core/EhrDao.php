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
 * 
 */ 

namespace raptor;

require_once 'Context.php';
require_once 'IEhrDao.php';
require_once 'RuntimeResultFlexCache.php';
require_once 'WorklistColumnMap.php';

/**
 * This is the primary interface abstraction to EHR
 *
 * @author Frank Font of SAN Business Consultants
 */
class EhrDao implements \raptor\IEhrDao
{
    private $instanceTimestamp = NULL;
    private $m_implclass = NULL;
    
    function __construct()
    {
        try
        {
            $this->instanceTimestamp = microtime(TRUE);
            //error_log("Creating instance of EhrDao ts={$this->instanceTimestamp}");
            $loaded = module_load_include('php', 'raptor_datalayer', 'config/ehr_integration');
            if($loaded === FALSE)
            {
                throw new \Exception("Failed to load config/ehr_integration!");
            }
            defined('EHR_INT_IMPL_DAO_NAMESPACE')
                or define('EHR_INT_IMPL_DAO_NAMESPACE', 'MISSING');
            if(EHR_INT_IMPL_DAO_NAMESPACE == 'MISSING')
            {
                throw new \Exception("Did NOT find definition for EHR_INT_IMPL_DAO_NAMESPACE!");
            }
            $classname = EHR_INT_IMPL_DAO_CLASSNAME;
            $namespace = EHR_INT_IMPL_DAO_NAMESPACE;
            $class = "\\$namespace\\$classname";
            $this->m_implclass = new $class(VISTA_SITE);
            //error_log("EhrDao construction completed >>> " . $this);
        } catch (\Exception $ex) {
            throw new \Exception("Failed constructor EhrDao(".EHR_INT_IMPL_DAO_NAMESPACE.") because $ex",99876,$ex);
        }
    }
    
    /**
     * Get the implementation instance
     * This function should NOT be overridden.
     */
    public function getImplementationInstance()
    {
        return $this->m_implclass;
    }
    
    
    public function __toString()
    {
        try
        {
            $authenticated_info = $this->isAuthenticated() ? 'isAuthenticated=[TRUE] ' : 'isAuthenticated=[FALSE] ';
        } catch (\Exception $ex) {
            $authenticated_info = "isAuthenticated failed because ".$ex->getMessage() . ' ';
        }
        try
        {
            $ehr_user_info = 'EHR User ID=['.$this->getEHRUserID().'] ';
        } catch (\Exception $ex) {
            $ehr_user_info = "getEHRUserID failed because " . $ex->getMessage() . ' ';
        }
        try
        {
            $impl_info = "EHR Implementation details are as follows ...\n".$this->m_implclass.' ';
        } catch (\Exception $ex) {
            $impl_info = "EHR Implementation failed because " . $ex->getMessage() . ' ';
        }
        try 
        {
            return 'EhrDao instance created at ' 
                    . $this->instanceTimestamp . ' '
                    . $authenticated_info
                    . $ehr_user_info
                    . "\n\tImplementation DAO=".$this->m_implclass;
        } catch (\Exception $ex) {
            return 'Cannot get toString of EhrDao because ' . $ex->getMessage();
        }
    }
    
    public function getIntegrationInfo() 
    {
        return $this->m_implclass->getIntegrationInfo();
    }
    
    public function setCustomInfoMessage($msg)
    {
        return $this->m_implclass->setCustomInfoMessage($msg);
    }
    
    public function getCustomInfoMessage()
    {
        return $this->m_implclass->getCustomInfoMessage();
    }
    
    /**
     * We can only pre-cache orders if the DAO implementation is not statefully
     * remembering the last selected order as the current order.
     * 
     * Returns TRUE if critical functions support tracking ID override for precache purposes.
     */
    public function getSupportsPreCacheOrderData()
    {
        return $this->m_implclass->getSupportsPreCacheOrderData();
    }
    
    /**
     * We can only pre-cache patient data if the DAO implementation is not statefully
     * remembering the last selected order as the current order.
     * 
     * Returns TRUE if critical functions support patientId override for precache purposes.
     */
    public function getSupportsPreCachePatientData()
    {
        return $this->m_implclass->getSupportsPreCachePatientData();
    }
    
    public function connectAndLogin($siteCode, $username, $password) 
    {
        return $this->m_implclass->connectAndLogin($siteCode, $username, $password);
    }

    public function disconnect() 
    {
       return $this->m_implclass->disconnect();
    }

    public function isAuthenticated() 
    {
       return $this->m_implclass->isAuthenticated();
    }

    /**
     * Gets dashboard details for the currently selected ticket of the session
     */
    function getDashboardDetailsMap($override_tracking_id=NULL)
    {
        return $this->m_implclass->getDashboardDetailsMap($override_tracking_id);
    }
    
    public function getWorklistDetailsMap()
    {
        return $this->m_implclass->getWorklistDetailsMap();
    }
    
    public function getVistaAccountKeyProblems() 
    {
        return $this->m_implclass->getVistaAccountKeyProblems();
    }

    public function getSelectedPatientID()
    {
        return $this->m_implclass->getSelectedPatientID();
    }
    
    public function getPatientIDFromTrackingID($sTrackingID) 
    {
        return $this->m_implclass->getPatientIDFromTrackingID($sTrackingID);
    }
    
    public function createNewRadiologyOrder($orderChecks, $args)
    {
        return $this->m_implclass->createNewRadiologyOrder($orderChecks, $args);
    }

    public function createUnsignedRadiologyOrder($orderChecks, $args)
    {
        return $this->m_implclass->createUnsignedRadiologyOrder($orderChecks, $args);
    }

    public function getOrderableItems($imagingTypeId)
    {
        return $this->m_implclass->getOrderableItems($imagingTypeId);
    }

    public function getRadiologyOrderChecks($args)
    {
        return $this->m_implclass->getRadiologyOrderChecks($args);
    }

    public function getRadiologyOrderDialog($imagingTypeId, $patientId)
    {
        return $this->m_implclass->getRadiologyOrderDialog($imagingTypeId, $patientId);
    }

    /**
     * Return limited list of providers starting with neworderprovider_name
     */
    public function getProviders($start_name)
    {
        return $this->m_implclass->getProviders($start_name);
    }

    public function getUserSecurityKeys()
    {
        return $this->m_implclass->getUserSecurityKeys();
    }

    public function isProvider()
    {
        return $this->m_implclass->isProvider();
    }

    public function userHasKeyOREMAS()
    {
        return $this->m_implclass->userHasKeyOREMAS();
    }

    public function cancelRadiologyOrder($patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, $cancelesig)
    {
        return $this->m_implclass->cancelRadiologyOrder($patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, $cancelesig);
    }

    public function getChemHemLabs($override_patientId = NULL)
    {
        return $this->m_implclass->getChemHemLabs($override_patientId);
    }

    public function getEncounterStringFromVisit($vistitTo)
    {
        return $this->m_implclass->getEncounterStringFromVisit($vistitTo);
    }

    public function getHospitalLocationsMap($startingitem)
    {
        return $this->m_implclass->getHospitalLocationsMap($startingitem);
    }

    public function getRadiologyCancellationReasons()
    {
        return $this->m_implclass->getRadiologyCancellationReasons();
    }

    public function getVisits()
    {
        return $this->m_implclass->getVisits();
    }

    public function signNote($newNoteIen, $eSig)
    {
        return $this->m_implclass->signNote($newNoteIen, $eSig);
    }

    public function validateEsig($eSig)
    {
        return $this->m_implclass->validateEsig($eSig);
    }

    /**
     * Returns array of arrays IEN is key of top array, member array is collection of TITLES
     */
    public function getNoteTitles($startingitem)
    {
        return $this->m_implclass->getNoteTitles($startingitem);
    }
    
    public function verifyNoteTitleMapping($checkVistaNoteIEN, $checkVistaNoteTitle)
    {
        return $this->m_implclass->verifyNoteTitleMapping($checkVistaNoteIEN, $checkVistaNoteTitle);
    }

    public function writeRaptorGeneralNote($noteTextArray, $encounterString, $cosignerDUZ)
    {
        return $this->m_implclass->writeRaptorGeneralNote($noteTextArray, $encounterString, $cosignerDUZ);
    }

    public function writeRaptorSafetyChecklist($aChecklistData, $encounterString, $cosignerDUZ)
    {
        return $this->m_implclass->writeRaptorSafetyChecklist($aChecklistData, $encounterString, $cosignerDUZ);
    }

    public function getEHRUserID($fail_if_missing=TRUE)
    {
        return $this->m_implclass->getEHRUserID($fail_if_missing);
    }
    
    public function setPatientID($sPatientID)
    {
        return $this->m_implclass->setPatientID($sPatientID);
    }
    
    public function getVitalsDetailOnlyLatestMap()
    {
        return $this->m_implclass->getVitalsDetailOnlyLatestMap();
    }
    
    public function getEGFRDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getEGFRDetailMap($override_patientId);
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getRawVitalSignsMap($override_patientId = NULL)
    {
        $result = $this->m_implclass->getRawVitalSignsMap($override_patientId);
        return $result;
    }
    
    public function getAllHospitalLocationsMap()
    {
        return $this->m_implclass->getAllHospitalLocationsMap();
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getAllergiesDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getAllergiesDetailMap($override_patientId);
    }

    public function getOrderOverviewMap()
    {
        return $this->m_implclass->getOrderOverviewMap();
    }

    public function getVitalsSummaryMap()
    {
        return $this->m_implclass->getVitalsSummaryMap();
    }

    public function getVitalsDetailMap()
    {
        return $this->m_implclass->getVitalsDetailMap();
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getDiagnosticLabsDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getDiagnosticLabsDetailMap($override_patientId);
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getPathologyReportsDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getPathologyReportsDetailMap($override_patientId);
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getSurgeryReportsDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getSurgeryReportsDetailMap($override_patientId);
    }

    public function getProblemsListDetailMap()
    {
        return $this->m_implclass->getProblemsListDetailMap();
    }

    /**
     * Returns FALSE if optional param is not NULL and feature is not supported.
     */
    public function getRadiologyReportsDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getRadiologyReportsDetailMap($override_patientId);
    }

    public function getMedicationsDetailMap($atriskmeds = NULL)
    {
        return $this->m_implclass->getMedicationsDetailMap($atriskmeds);
    }
    
    public function getNotesDetailMap($override_patientId = NULL)
    {
        return $this->m_implclass->getNotesDetailMap($override_patientId);
    }

    /**
     * Return the set of orders that exist for one patient
     */
    public function getPendingOrdersMap($override_patientId = NULL)
    {
        return $this->m_implclass->getPendingOrdersMap($override_patientId);
    }

    public function getImagingTypesMap()
    {
        return $this->m_implclass->getImagingTypesMap();
    }

    public function invalidateCacheForEverything()
    {
        return $this->m_implclass->invalidateCacheForEverything();
    }
    
    public function invalidateCacheForOrder($tid)
    {
        return $this->m_implclass->invalidateCacheForOrder($tid);
    }

    public function invalidateCacheForPatient($pid)
    {
        return $this->m_implclass->invalidateCacheForPatient($pid);
    }

    public function getPatientMap($pid)
    {
        return $this->m_implclass->getPatientMap($pid);
    }

}
