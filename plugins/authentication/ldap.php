<?php
/**
* @version		$Id$
* @package		Joomla
* @subpackage	JFramework
* @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
* @license		GNU General Public License, see LICENSE.php
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * LDAP Authentication Plugin
 *
 * @package		Joomla
 * @subpackage	JFramework
 * @since 1.5
 */

class plgAuthenticationLdap extends JPlugin
{
	/**
	 * This method should handle any authentication and report back to the subject
	 *
	 * @access	public
	 * @param   array 	$credentials Array holding the user credentials
	 * @param 	array   $options	 Array of extra options
	 * @param	object	$response	Authentication response object
	 * @return	object	boolean
	 * @since 1.5
	 */
	function onAuthenticate( $credentials, $options, &$response )
	{
		// Initialize variables
		$userdetails = null;
		$success = 0;

		// For JLog
		$response->type = 'LDAP';
		// LDAP does not like Blank passwords (tries to Anon Bind which is bad)
		if (empty($credentials['password']))
		{
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = 'LDAP can not have blank password';
			return false;
		}

		// load plugin params info
		$ldap_email 	= $this->params->get('ldap_email');
		$ldap_fullname	= $this->params->get('ldap_fullname');
		$ldap_uid		= $this->params->get('ldap_uid');
		$auth_method	= $this->params->get('auth_method');

		jimport('joomla.client.ldap');
		$ldap = new JLDAP($this->params);

		if (!$ldap->connect())
		{
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = 'Unable to connect to LDAP server';
			return;
		}

		switch($auth_method)
		{
			case 'search':
			{
				// Bind using Connect Username/password
				// Force anon bind to mitigate misconfiguration like [#7119]
				if(strlen($this->params->get('username'))) $bindtest = $ldap->bind();
				else $bindtest = $ldap->anonymous_bind();


				if($bindtest)
				{
					// Search for users DN
					$binddata = $ldap->simple_search(str_replace("[search]", $credentials['username'], $this->params->get('search_string')));
					// Verify Users Credentials
					$success = $ldap->bind($binddata[0]['dn'],$credentials['password'],1);
					// Get users details
					$userdetails = $binddata;
				}
				else
				{
					$response->status = JAUTHENTICATE_STATUS_FAILURE;
					$response->error_message = 'Unable to bind to LDAP';
				}
			}	break;

			case 'bind':
			{
				// We just accept the result here
				$success = $ldap->bind($credentials['username'],$credentials['password']);
				$userdetails = $ldap->simple_search(str_replace("[search]", $credentials['username'], $this->params->get('search_string')));
			}	break;
		}

		if(!$success)
		{
			$response->status = JAUTHENTICATE_STATUS_FAILURE;
			$response->error_message = 'Incorrect username/password';
		}
		else
		{
			// Grab some details from LDAP and return them
			if (isset($userdetails[0][$ldap_uid][0])) {
				$response->username = $userdetails[0][$ldap_uid][0];
			}

			if (isset($userdetails[0][$ldap_email][0])) {
				$response->email = $userdetails[0][$ldap_email][0];
			}

			if(isset($userdetails[0][$ldap_fullname][0])) {
				$response->fullname = $userdetails[0][$ldap_fullname][0];
			} else {
				$response->fullname = $credentials['username'];
			}

			// Were good - So say so.
			$response->status		= JAUTHENTICATE_STATUS_SUCCESS;
			$response->error_message = '';
		}

		$ldap->close();
	}
}
