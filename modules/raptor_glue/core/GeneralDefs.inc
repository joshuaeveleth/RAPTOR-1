<?php

defined('GENERAL_DEFS_VERSION_INFO')
    or define('GENERAL_DEFS_VERSION_INFO', 'UAT 20150930.2');

/*
 * These are General Purpose settings
 */

//Require acknowledgement of default values?
defined('REQUIRE_ACKNOWLEDGE_DEFAULTS')
    or define('REQUIRE_ACKNOWLEDGE_DEFAULTS', FALSE);

//Preferred miniumum size for shortlist of protocol filter result
defined('PROTOCOL_SHORTLIST_MIN_SIZE')
    or define('PROTOCOL_SHORTLIST_MIN_SIZE', 3);

//Some default limits to reduce data volumes on text displayed
defined('MAX_DEFAULT_NOTES_TO_SHOW')
    or define('MAX_DEFAULT_NOTES_TO_SHOW', 100);

//Define some graphing constraints
defined('GRAPH_THUMBNAIL_DEFAULT_POINT_COUNT')
    or define('GRAPH_THUMBNAIL_DEFAULT_POINT_COUNT', 8);
defined('GRAPH_NORMAL_DEFAULT_POINT_COUNT')
    or define('GRAPH_NORMAL_DEFAULT_POINT_COUNT', 20);
defined('GRAPH_LARGE_DEFAULT_POINT_COUNT')
    or define('GRAPH_LARGE_DEFAULT_POINT_COUNT', 100);

//Modality inference configuration items; change with care.
defined('MATCH_MODALITY_ON_PREFIX')
    or define('MATCH_MODALITY_ON_PREFIX', TRUE);
defined('MATCH_MODALITY_STANDARD_TERMS')
    or define('MATCH_MODALITY_STANDARD_TERMS', TRUE);
//Comma delimited lists of terms else NULL
defined('CUSTOM_TERMS4MATCH_MR')
    or define('CUSTOM_TERMS4MATCH_MR', NULL);
defined('CUSTOM_TERMS4MATCH_CT')
    or define('CUSTOM_TERMS4MATCH_CT', NULL);
defined('CUSTOM_TERMS4MATCH_NM')
    or define('CUSTOM_TERMS4MATCH_NM', NULL);
defined('CUSTOM_TERMS4MATCH_FL')
    or define('CUSTOM_TERMS4MATCH_FL', NULL);
defined('CUSTOM_TERMS4MATCH_US')
    or define('CUSTOM_TERMS4MATCH_US', NULL);

//How much of the username to mask from other standard RAPTOR users (99 to hide)
defined('DEFAULT_USERNAME_TAILMASK')
    or define('DEFAULT_USERNAME_TAILMASK', 5);

//Declare minimum passwords length for NON-VISTA user accounts
defined('MIN_ADMIN_PASSWORD_LEN')
    or define('MIN_ADMIN_PASSWORD_LEN', 5);

//Default size of snippet text
defined('RAPTOR_DEFAULT_SNIPPET_LEN')
    or define('RAPTOR_DEFAULT_SNIPPET_LEN', 40);

//Number of orders to pre-cache
defined('PRE_CACHE_SIZE')
    or define('PRE_CACHE_SIZE', 10);

defined('CACHE_AGE_SHORTLIVED')
    or define('CACHE_AGE_SHORTLIVED', 6);

defined('CACHE_AGE_LABS')
    or define('CACHE_AGE_LABS', 60);

defined('CACHE_AGE_SITEVALUES')
    or define('CACHE_AGE_SITEVALUES', 600);

