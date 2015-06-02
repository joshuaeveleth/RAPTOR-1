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

require_once 'data_context.php';


/**
 * The RuntimeResultFlexCache is a singleton that caches results at runtime.
 * This session level cache has a configurable expiration time.
 *
 * @author Frank Font of SAN Business Consultants
 */
class RuntimeResultFlexCache 
{
    private $m_sGroupName = NULL;
    private static $m_aGroups = array();
    
    private function __construct($sGroupName)
    {
        $this->m_sGroupName = $sGroupName;
    }
    
    /**
     * Get the existing cache for the group or create a new one
     * @return instance of RuntimeResultFlexCache class
     */
    public static function getInstance($sGroupName, $bReset=FALSE)
    {
        if(!isset(RuntimeResultFlexCache::$m_aGroups[$sGroupName]) || $bReset )
        {
            RuntimeResultFlexCache::$m_aGroups[$sGroupName] = new RuntimeResultFlexCache($sGroupName);
            if(!isset($_SESSION['RuntimeResultFlexCache']))
            {
                $cacheroot = array();
                $flagroot = array();
            } else {
                $cacheroot = $_SESSION['RuntimeResultFlexCache'];
                $flagroot = $_SESSION['RuntimeResultFlexCache_flags'];
            }
            if(!isset($cacheroot[$sGroupName]) || $bReset)
            {
                $cacheroot[$sGroupName] = array();
                $_SESSION['RuntimeResultFlexCache'] = $cacheroot;
                $flagroot[$sGroupName] = array();
                $_SESSION['RuntimeResultFlexCache_flags'] = $flagroot;
            }
        }
        return RuntimeResultFlexCache::$m_aGroups[$sGroupName];
    }

    /**
     * Mark a cache as building
     */
    public function markCacheBuilding($sThisResultName,$nRetrySeconds=5,$nFailTimeoutSeconds=100)
    {
        $this->updateCacheFlag($sThisResultName,'building',TRUE,$nRetrySeconds,$nFailTimeoutSeconds);
    }

    /**
     * Mark a cache as building
     */
    public function isCacheBuilding($sThisResultName)
    {
        return ($this->getCacheFlagValue($sThisResultName,'building') == TRUE);
    }

    /**
     * If 0 then no need to wait, else try again after result seconds.
     */
    public function getCacheBuildingRetrySeconds($sThisResultName)
    {
        $foundinfo = $this->getCacheFlagInfo($sThisResultName,'building');
        if($foundinfo == NULL)
        {
            return 0;
        }
        return $foundinfo['retry_seconds'];
    }
    
    /**
     * Mark a cache as building
     */
    public function clearCacheBuilding($sThisResultName,$nRetrySeconds=5,$nFailTimeoutSeconds=100)
    {
        $this->clearCacheFlag($sThisResultName,'building');
    }
    
    /**
     * Update a cache flag values
     */
    private function updateCacheFlag($sThisResultName,$flagname,$flagvalue,$nRetrySeconds=5,$nFailTimeoutSeconds=100)
    {
        $flagroot = $_SESSION['RuntimeResultFlexCache_flags'];
        if($this->m_sGroupName == NULL)
        {
            throw new \Exception("The RuntimeResultFlexCache must be initialized with a group name BEFORE you can set flag[$flagname]=[$flagvalue] of $sThisResultName!");
        }
        $groupflag = $flagroot[$this->m_sGroupName];
        if(!isset($groupflag[$sThisResultName]))
        {
            $groupflag[$sThisResultName] = array();
        }
        $groupflag[$sThisResultName][$flagname]['value'] = $flagvalue;
        $groupflag[$sThisResultName][$flagname]['creation_time'] = time();
        $groupflag[$sThisResultName][$flagname]['retry_seconds'] = $nRetrySeconds;
        $groupflag[$sThisResultName][$flagname]['fail_timeout_seconds'] = $nFailTimeoutSeconds;
        $flagroot[$this->m_sGroupName] = $groupflag;
        $_SESSION['RuntimeResultFlexCache_flags'] = $flagroot;
    }

    /**
     * Get the flag value
     */
    private function getCacheFlagValue($sThisResultName,$flagname)
    {
        $foundcache = getCacheFlagInfo($sThisResultName,$flagname);
        if($foundcache == NULL)
        {
            return NULL;
        }
        return $foundcache[$flagname]['value'];
    }

    /**
     * Get the flag information
     */
    private function getCacheFlagInfo($sThisResultName,$flagname)
    {
        $flagroot = $_SESSION['RuntimeResultFlexCache_flags'];
        if($this->m_sGroupName == NULL)
        {
            throw new \Exception("The RuntimeResultFlexCache must be initialized with a group name BEFORE you can read flag[$flagname] of $sThisResultName!");
        }
        $groupflag = $flagroot[$this->m_sGroupName];
        if(!isset($groupflag[$sThisResultName]) || !isset($groupflag[$sThisResultName][$flagname]))
        {
            //Not set.
            return NULL;
        }
        $foundcache = $groupflag[$sThisResultName];
        //It exists, but is it still valid?
        $currenttime = time();
        $creation_time = $foundcache['creation_time'];
        $flag_age = $currenttime - $creation_time;
        $fail_timeout_seconds = $foundcache['fail_timeout_seconds'];                
        if($flag_age > $fail_timeout_seconds)
        {
            //Kill it.
            $this->clearCacheFlag($sThisResultName, $flagname);
            return NULL;
        }
        return $foundcache;
    }
    
    /**
     * Update a cache flag action
     */
    private function clearCacheFlag($sThisResultName,$flagname)
    {
        $flagroot = $_SESSION['RuntimeResultFlexCache_flags'];
        if($this->m_sGroupName == NULL)
        {
            throw new \Exception("The RuntimeResultFlexCache must be initialized with a group name BEFORE you can clear flag[$flagname] of $sThisResultName!");
        }
        $groupflag = $flagroot[$this->m_sGroupName];
        if(!isset($groupflag[$sThisResultName]) || !isset($groupflag[$sThisResultName][$flagname]))
        {
            //Already missing.
            return FALSE;
        }
        unset($groupflag[$sThisResultName][$flagname]);
        $flagroot[$this->m_sGroupName] = $groupflag;
        $_SESSION['RuntimeResultFlexCache_flags'] = $flagroot;
        return TRUE;
    }
    
    /**
     * Add the result data to the cache.
     */
    public function addToCache($sThisResultName,$aResult,$nMaxDataAgeSeconds=600)
    {
        $cacheroot = $_SESSION['RuntimeResultFlexCache'];
        if($this->m_sGroupName == NULL || !isset($cacheroot[$this->m_sGroupName]))
        {
            throw new \Exception("The RuntimeResultFlexCache must be initialized with a group name BEFORE you can add $sThisResultName!");
        }
        $groupcache = $cacheroot[$this->m_sGroupName];
        if(!isset($groupcache[$sThisResultName]))
        {
            $groupcache[$sThisResultName] = array();
        }
        $groupcache[$sThisResultName]['creation_time'] = time();
        $groupcache[$sThisResultName]['max_age_seconds'] = $nMaxDataAgeSeconds;
        $groupcache[$sThisResultName]['data'] = $aResult;
        $cacheroot[$this->m_sGroupName] = $groupcache;
        $_SESSION['RuntimeResultFlexCache'] = $cacheroot;
        $this->clearCacheBuilding($sThisResultName);
    }
    
    /**
     * Side effect of the check is that it prepares the cache to accept a new result.
     * @return NULL if not found in cache, else the result from the cache.
     */
    public function checkCache($sThisResultName)
    {
        //See if we are already building a cache.
        $aResult = NULL;
        $retry_seconds = $this->getCacheBuildingRetrySeconds($sThisResultName);
        while($retry_seconds > 0)
        {
            error_log("Waiting for $sThisResultName to build, will retry in $retry_seconds seconds.");
            sleep($retry_seconds);
            $retry_seconds = $this->getCacheBuildingRetrySeconds($sThisResultName);
        }
        //Now check for an available cache.
        $cacheroot = $_SESSION['RuntimeResultFlexCache'];
        if($this->m_sGroupName == NULL || !isset($cacheroot[$this->m_sGroupName]))
        {
            throw new \Exception("The RuntimeResultFlexCache must be initialized with a group name BEFORE you can read $sThisResultName!");
        }
        $groupcache = $cacheroot[$this->m_sGroupName];
        if(isset($groupcache[$sThisResultName]))
        {
            $foundcache = $groupcache[$sThisResultName];
            //Make sure cache data is not too old
            $currenttime = time();
            $creation_time = $foundcache['creation_time'];
            $data_age = $currenttime - $creation_time;
            $max_age_seconds = $foundcache['max_age_seconds'];                
            if($data_age > $max_age_seconds)
            {
                //Cache data is stale, kill it.
                $groupcache[$sThisResultName] = array();
                $_SESSION['RuntimeResultFlexCache'] = $cacheroot;
            } else {
                //We have good cache data, use it.
                $aResult = $foundcache['data'];
            }
        }
        return $aResult;
    }
}
