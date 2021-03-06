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

class EwdUtils 
{
    
    public static function convertFromVistaSSN($digits)
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
     * Using the current system time (with an optional offset, get date in VistA format
     */
    public static function getVistaDate($dateOffset) 
    {
        $curDt = new \DateTime();
        
        if ($dateOffset < 0) {
            $dateOffset = abs($dateOffset);
            $curDt->sub(new \DateInterval('P'.$dateOffset.'D'));
        }
        else if ($dateOffset > 0) {
            $curDt->add(new \DateInterval('P'.$dateOffset.'D'));
        }
               
        return self::convertPhpDateTimeToVistaDate($curDt);
    }
    
    /**
     * Convert \DateTime to Vista format
     * Ex 1) EwdUtils::convertPhpDateTimeToVista(new \DateTime('2010-12-31')) -> '3101231'
     */
    public static function convertPhpDateTimeToVistaDate($phpDateTime) {
        $year = $phpDateTime->format('Y');
        $month = $phpDateTime->format('m');
        $day = $phpDateTime->format('d');
        
        return ($year - 1700).$month.$day;
    }

    /**
     * Convert VistA format: 3101231 -> 2010-12-31
     */
    public static function convertVistaDateTimeToDate($vistaDateTime) {
        $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
        $year = 1700 + substr($datePart, 0, 3);
        $month = substr($datePart, 3, 2);
        $day = substr($datePart, 5, 2);
        
        return $month."-".$day."-".$year;
    }
    
    /**
     * Convert VistA format: 3101231.064515 -> 2010-12-31 064515
     */
    public static function convertVistaDateTimeToDatetime($vistaDateTime) {
        $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
        $timePart = self::getVistaDateTimePart($vistaDateTime, "time");
        $year = 1700 + substr($datePart, 0, 3);
        $month = substr($datePart, 3, 2);
        $day = substr($datePart, 5, 2);
        
        return $month."-".$day."-".$year." ".$timePart;
    }
    
    /**
     * Convert VistA format: 3101231 -> 20101231
     */
    public static function convertVistaDateToYYYYMMDD($vistaDateTime) {
        $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
        if($datePart > '')
        {
            $year = 1700 + substr($datePart, 0, 3);
            $month = substr($datePart, 3, 2);
            $day = substr($datePart, 5, 2);

            $converted = $year.$month.$day;
        } else {
            $converted = NULL;  //Nothing to convert
        }
        return $converted;
    }

    /**
     * Convert VistA format: 3101231.150020 -> 20101231.150020
     */
    public static function convertVistaDateToYYYYMMDDtttt($vistaDateTime) 
    {
        try
        {
            $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
            $timePart = self::getVistaDateTimePart($vistaDateTime, "time");
            $year = 1700 + substr($datePart, 0, 3);
            $month = substr($datePart, 3, 2);
            $day = substr($datePart, 5, 2);
            if(strlen($timePart) == 2)
            {
                $timePart = $timePart . '00';   //Add the missing zeros
            } else
            if(strlen($timePart) == 3)
            {
                $timePart = $timePart . '0';   //Add the missing zero
            }
            return $year.$month.$day.".".$timePart;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }    
    
    /**
     * Convert 20100101 format -> 2010-01-01
     */
    public static function convertYYYYMMDDToDate($vistaDateTime) 
    {
        try
        {
            $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
            $year = substr($datePart, 0, 4);
            $month = substr($datePart, 4, 2);
            $day = substr($datePart, 6, 2);

            return $month."-".$day."-".$year;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Convert 20100101.083400 format -> 2010-01-01 083400
     */
    public static function convertYYYYMMDDToDatetime($vistaDateTime) {
        $datePart = self::getVistaDateTimePart($vistaDateTime, "date");
        $timePart = self::getVistaDateTimePart($vistaDateTime, "time");
        $year = substr($datePart, 0, 4);
        $month = substr($datePart, 4, 2);
        $day = substr($datePart, 6, 2);
        
        return $month."-".$day."-".$year." ".$timePart;
    }
    
    /*
     * Fetch either the date or time part of a VistA date. 
     * Ex 1) EwdUtils::getVistaDateTimePart('3101231.0930', 'date') -> '3101231'
     * Ex 2) EwdUtils::getVistaDateTimePart('3101231.0930', 'time') -> '0930'
     * Ex 3) EwdUtils::getVistaDateTimePart('3101231', 'time') -> '000000' (defaults to midnight if not time part)
     */
    public static function getVistaDateTimePart($vistaDateTime, $dateOrTime) {
        if ($vistaDateTime === NULL) {
            throw new \Exception('Vista date/time cannot be null');
        }
        $pieces = explode('.', $vistaDateTime);
        if ($dateOrTime == 'date' || $dateOrTime == 'Date' || $dateOrTime == 'DATE') {
            return $pieces[0];
        }
        else {
            if (count($pieces) == 1 || trim($pieces[1]) == '') {
                return '000000'; // default to midnight if no time part 
            }
            return $pieces[1];
        }
    }

    /**
     * phpseconds -> Sept 10@15:30
     */
    public static function convertPhpDateTimeToFunnyText($phpDateTime) 
    {
        return date('M d, Y@H:i', $phpDateTime);
    }
    
    /**
     * 1441515600 -> 20150906.0100
     */
    public static function convertPhpDateTimeToISODate($phpDateTime) 
    {
        return date('Ymd.Hi', $phpDateTime);
    }
}


