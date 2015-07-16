<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2014
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * MDWS Integration and VISTA collaboration: Joel Mewton
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * 
 */ 

namespace raptor_mdwsvista;

require_once 'MdwsStringUtils.php';

class MdwsUserUtils {

    public static function getUserSecurityKeys($mdwsDao, $userDuz=NULL) 
    {
        try 
        {
            if($userDuz == NULL)
            {
                $userDuz = $mdwsDao->getDUZ();
            }
            if(trim($userDuz) == '')
            {
                throw new \Exception("Missing DUZ for ".$mdwsDao);
            }
            $soapResult = $mdwsDao->makeQuery('getUserSecurityKeys', array('uid'=>$userDuz));

            if (!(isset($soapResult->getUserSecurityKeysResult)) ||
                    isset($soapResult->getUserSecurityKeysResult->fault)) {
                throw new \Exception('There was a problem fetching the user security keys: '
                        .$soapResult->getUserSecurityKeysResult->fault);
            }

            $result= array();
            $keysTO = $soapResult->getUserSecurityKeysResult;
            $keyCount = $soapResult->getUserSecurityKeysResult->count;

            if ($keyCount == 0) {
                return $result;
            }

            if (isset($keysTO->keys->UserSecurityKeyTO) && is_array($keysTO->keys->UserSecurityKeyTO)) {
                $keysTO = $keysTO->keys->UserSecurityKeyTO;
            } else {
                $keysTO = array($keysTO->keys);
            }

            for ($i = 0; $i < $keyCount; $i++) {
                $keyId = $keysTO[$i]->id;
                $keyName = $keysTO[$i]->name;
                $result[$keyId] = $keyName;
            }

            return $result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public static function userHasKey($mdwsDao, $userDuz, $keyName) 
    {
        $usersKeys = MdwsUserUtils::getUserSecurityKeys($mdwsDao, $userDuz);
        return in_array($keyName, array_values($usersKeys));
    }
    
    public static function userHasSecondaryMenuOption($mdwsDao, $userDuz, $optionName)
    {
        try
        {
            $params = array('uid'=>$userDuz, 'permissionName' => $optionName );
            $soapResult = $mdwsDao->makeQuery('userHasPermission', $params);
            $haskey = FALSE;
            if(isset($soapResult->userHasPermissionResult->trueOrFalse))
            {
                $haskey = strtolower($soapResult->userHasPermissionResult->trueOrFalse);
            }
            return $haskey;
        } catch (\Exception $ex) {
            throw new \Exception("Possible trouble with soap result ".print_r($soapResult,TRUE),99876,$ex);
        }
    }
    
    /**
     * Return NULL if no problems.
     */
    public static function getVistaAccountKeyProblems($mdwsDao, $userDuz)
    {
        $missingkeys = array();
        $has_superkey = \raptor_mdwsvista\MdwsUserUtils::userHasKey($mdwsDao, $userDuz, 'XUPROGMODE');
        if(!$has_superkey)
        {
            $minSecondaryOptions = array('DVBA CAPRI GUI'); //'OR CPRS GUI CHART'
            foreach($minSecondaryOptions as $keyName)
            {
                $haskey = \raptor_mdwsvista\MdwsUserUtils::userHasSecondaryMenuOption($mdwsDao, $userDuz, $keyName);
                if(!$haskey)
                {
                    $missingkeys[] = $keyName;
                }
            }
        }
        $errormsg = NULL;
        if(count($missingkeys) > 0)
        {
            $keystext = implode(', ',$missingkeys);
            $missingkeycount = count($missingkeys);
            $errormsg = "The VistA user account does not have access to: $keystext!";
            error_log("The VistA account for is missing $missingkeycount menu options ".print_r($userDuz,TRUE));
        }
        return $errormsg;
    }

    /**
     * Return max of 44 providers starting with target string
     * @return array
     * @throws \Exception
     */
    public static function getProviders($mdwsDao, $target)
    {
        $soapResult = $mdwsDao->makeQuery('cprsUserLookup', array('target'=>$target));
        
        if (!isset($soapResult->cprsUserLookupResult) || isset($soapResult->cprsUserLookupResult->fault)) {
            throw new \Exception('There was a problem fetching CPRS users: '
                .$soapResult->cprsUserLookupResult->fault->message);
        }
        
        $result = array();

        if (!isset($soapResult->cprsUserLookupResult->users) ||
                !isset($soapResult->cprsUserLookupResult->users->UserTO)) {
            return $result;
        }
        
        $cprsUserTOs = $soapResult->cprsUserLookupResult->users->UserTO;
        if (!is_array($cprsUserTOs)) {
            $cprsUserTOs = array($cprsUserTOs);
        } else {
            $cprsUserTOs = $soapResult->cprsUserLookupResult->users->UserTO;
        }
        
        for ($i = 0; $i < count($cprsUserTOs); $i++) {
            $userDuz = $cprsUserTOs[$i]->DUZ;
            $userName = $cprsUserTOs[$i]->name;
            $result[$userDuz] = $userName;
        }
        
        return $result;
    }

    // TODO - refactor this function to retrieve keys from MdwsUserUtils::getUserSecurityKeys
    /**
     * @return boolean TRUE if the user is a provider, else FALSE
     */
    public static function isProvider($mdwsDao, $userDuz)
    {
        return MdwsUserUtils::userHasKey($mdwsDao, $userDuz, 'PROVIDER');
    }

    public static function userHasKeyOREMAS($mdwsDao, $userDuz) {
        return MdwsUserUtils::userHasKey($mdwsDao, $userDuz, 'OREMAS');
    }

}
