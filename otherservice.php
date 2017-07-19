<?php
/**
 * 
 LinkedIn Profile Synchronization Tool downloads the LinkedIn profile and feeds the 
 downloaded data to Smarty, the templating engine, in order to update a local page.
 Copyright (C) 2012 Bas ten Berge

  This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Library General Public
 License as published by the Free Software Foundation; either
 version 2 of the License, or (at your option) any later version.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Library General Public License for more details.

 You should have received a copy of the GNU Library General Public
 License along with this library; if not, write to the
 Free Software Foundation, Inc., 51 Franklin St, Fifth Floor,
 Boston, MA  02110-1301, USA.
 *
 * $Id: otherservice.php 587979 2012-08-20 21:15:08Z bastb $
 *
 */


define('LIPS_USER_META_STACKEXCHANGE', 'lips:stackexchange');
define('LIPS_TRANSIENT_STACKEXCHANGE_SITES', 'lips:stackexchange:sites');


/**
 * Other services to use or query for account details
 */
abstract class LinkedInProfileSyncOtherServiceBase {
	abstract public function save();
}

abstract class LinkedInProfileSyncWebServiceBase extends LinkedInProfileSyncOtherServiceBase {
	protected $uri;
	protected $cainfo = null;
	
	public function __construct($uri, $cainfo) {
		$this->uri = $uri;
		$this->cainfo = $cainfo;
	}
	
	abstract function fetch();
}

/**
 * Base, abstract class for the StackExchange network sites
 */
abstract class LinkedInProfileSyncStackExchange extends LinkedInProfileSyncWebServiceBase {
	/**
	 * @throws LipsRequirementMissingException curl is not available
	 */
	public function __construct($url = '', $cainfo = '', $site_type = array()) {
		if (!function_exists("curl_setopt")) {
			throw new LipsRequirementMissingException("curl");
		}
		
		$cacert = $cainfo;
		if (empty($cacert)) {
			$cacert = dirname(__FILE__) . '/data/api.stackexchange.pem';
		}
		
		if (!empty($site_type)) {
			$this->site_type += $site_type;
		}
		 
		parent::__construct($url, $cacert);
	}

	/**
	 * Fetches uri, returning the data
	 */
	protected function fetchFromUri($uri) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_CAINFO, $this->cainfo);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		
		$data = curl_exec($ch);
		return $data;
	}
}

/**
 * Retrieve all the sites available from the stackexchange network
 */
class LinkedInProfileSyncStackExchangeSites extends LinkedInProfileSyncStackExchange {
	protected $site_type = array('main_site');
	protected $cached = null;
	protected $fetched = false;
	protected $fetched_sites;
	protected $fetched_match = null;
	
	public function __construct($url = 'https://api.stackexchange.com/2.0/sites', $cainfo = '', $site_type = array()) {
		if (!empty($site_type)) {
			$this->site_type += $site_type;
		}
		parent::__construct($url, $cainfo);
	}
	
	/**
	 * Updates the stored cached results, making sure the filter is 
	 * included.
	 */
	protected function setCachedResults($cache) {
		$this->updateSiteType($cache['filter']);
		$this->cached = $cache['result'];
	}
	
	/**
	 * Updates the site types to be included. 
	 *  
	 * @attention: Invalidates cached results.
	 * 
	 * @param array $site_types The types of sites to include in the query.
	 *  There is no way to exclude the 'main_site' type
	 * 
	 */
	protected function updateSiteType($site_types) {
		$types = $site_types;
		if (!is_array($types)) {
			$types = explode(',', $site_types); 
		}
		$this->site_type += $types;
		$this->cached = null;
	}
	
	/**
	 * Adds the api_site_parameter from each stackexchange site as a key to
	 * an dictionary, including the name and site-url of that site as the
	 * user-friendly label of that site.
	 * 
	 * Updates $this->fetched_sites.
	 */
	protected function storeSites($specific, $sites) {
		foreach ($sites as $a_site) {
			if (in_array($a_site['site_type'], $this->site_type)) {
				$this->fetched_sites[$a_site['api_site_parameter']] = $a_site['name'] . ' - ' . $a_site['site_url'];
			}
		}
	}
	
	/**
	 * Attempts to match a site by the values of $specific. Updates $fetched_match
	 * when a match is made.
	 * 
	 * @param array $specific Array containing key (position 0) of the element to
	 *   match, and the value of that element on position 1.
	 */
	protected function matchSites($specific, $sites) {
		foreach ($sites as $a_site) {
			if (array_key_exists($specific[0], $a_site) && $a_site[$specific[0]] == $specific[1]) {
				$this->fetched_match = $a_site;
			}
		}
	}
	
	/**
	 * Issues REST call on StackExchange API, leaving the handling of the 
	 * returned data to the callback function.
	 * 
	 * @param array $callback Callable item, suitable for call_user_func_array.
	 * @param * $specific Parameter being passed to the $callback.
	 */
	protected function fetchFromStackExchange($callback, $specific) {
		$uri = $this->uri;
		$page_num = 1;
		
		do {
			$has_more = false;
			$paged_uri = add_query_arg(array("page" => $page_num, "pagesize" => 200), $uri);

			$stacknetwork_data = $this->fetchFromUri($paged_uri);
			$stacknetwork_sites = json_decode($stacknetwork_data, true);
			if (array_key_exists('items', $stacknetwork_sites)) {
				$site_items = $stacknetwork_sites['items'];
				call_user_func_array($callback, array($specific, $site_items));
			}
			$page_num++;
			
			if (is_array($stacknetwork_sites) && array_key_exists('has_more', $stacknetwork_sites)) {
				$has_more = $stacknetwork_sites['has_more'];
			}
		} while ($has_more);

		$this->fetched = true;
	}
	
	/**
	 * Fetches each site available through the stackexchange network.
	 * 
	 * @return associative array key selection, value the user-friendly name
	 */
	public function fetch() {
		if (empty($this->cached)) {
			$this->fetched_sites = array();
			$this->fetchFromStackExchange(array($this, 'storeSites'), null);
			$this->cached = $this->fetched_sites;
		}
		
		return $this->cached;
	}
	
	/**
	 * Fetch the data using a single key, value filter. 
	 * 
	 * @return null There was no site matching $val by $key.
	 * @example $o->fetchFiltered("api_site_parameter", "stackoverflow");
	 */
	public function fetchFiltered($key, $val) {
		$this->fetched_match = null;
		$this->fetchFromStackExchange(array($this, 'matchSites'), array($key, $val));
		return $this->fetched_match;
	}
	
	/**
	 * Stores retrieved sites, only when they were fetched
	 */
	public function save() {
		if ($this->fetched) {
			$cached_data = array(
				"filter" => $this->site_type,
				"result" => $this->cached,
			);
			set_transient(LIPS_TRANSIENT_STACKEXCHANGE_SITES, $cached_data, 60*60*12);	
		}
	}
	
	/**
	 * Can return previously stored content
	 */
	public static function fromTransientData() {
		$o = new LinkedInProfileSyncStackExchangeSites();
		$cached_data = get_transient(LIPS_TRANSIENT_STACKEXCHANGE_SITES);
		if (false != $cached_data) {
			$o->setCachedResults($cached_data);
		}
		return $o;
	}
}

/**
 * Stores and uses the StackExchange Details
 */
class LinkedInProfileSyncStackExchangeUserDetails extends LinkedInProfileSyncStackExchange {
	protected $account_name;
	protected $account_number;
	protected $service;
	protected $meta_key = LIPS_USER_META_STACKEXCHANGE;
	
	/**
	 * Initializes the user-id resolver
	 * 
	 * @param string $name The displayname of the user being sought.
	 * @param string $id The numerical id of the user being sought.
	 * @param string $service The site, being a member of the StackExchange
	 *   network.
	 * @param string $cainfo Filename of the certificate authority. Leave
	 *   blank to select the default.
	 * @param string $meta_key The key under which the user-details will be 
	 *   stored. Leave blank to select the default.
	 */
	public function __construct($name = null, $id = null, $service = null, $cainfo = null, $meta_key = null) {
		parent::__construct('https://api.stackexchange.com/2.0/users', $cainfo);
		
		$the_name = trim($name);

		$parsed_account = $this->unparseAccount($the_name);
		$parsed_name = $parsed_account[0];
		$parsed_id = $parsed_account[1];
		if (!empty($parsed_name) || !empty($parsed_id)) {
			$this->account_number = $parsed_id;
			$this->account_name = $parsed_name;
		}
		else {
			if (is_numeric($the_name)) {
				$this->account_number = $the_name;
			}
			else {
				$this->account_name = $the_name;
				$this->account_number = trim($id);
			}
		}
		
		if (empty($this->account_name) && empty($this->account_number)) {
			throw new LogicException();
		}
		
		if (!empty($meta_key)) {
			$this->meta_key = trim($meta_key);
		}
		
		$this->service = $service;
	}
	
	/**
	 * Fetches the account from StackExchange, optionally updating the user-account
	 * name and number when the account name was changed, or when the user was 
	 * resolved for the first time.
	 * 
	 */
	public function fetch() {
		$has_id = true;
		$has_name = false;
		
		if (empty($this->account_number)) {
			$uri = add_query_arg(array('inname' => $this->account_name), $this->uri); 
			$has_id = false;
		}
		else {
			$uri = $this->uri . '/' . urlencode($this->account_number);
		}
		
		/* Add the display name when the users' name differs */
		$has_name = !empty($this->account_name);
		
		$uri = add_query_arg('site', $this->service, $uri);
		
		$stacknetwork_data = $this->fetchFromUri($uri);
		
		if (false !== $stacknetwork_data) {
			$decoded = json_decode($stacknetwork_data, true);
			if (array_key_exists('items', $decoded) && 1 == count($decoded['items'])) {
				if (! $has_id) {
					$this->setId($decoded['items'][0]['user_id']);
				}
				$stacknetwork_name = $decoded['items'][0]['display_name']; 
				if (! $has_name || ($has_name && $stacknetwork_name != $this->account_name)) {
					// The loginname may have changed while the item is not saved at all
					$this->setName($stacknetwork_name, true);
				}
			}		
		}
		
		return $decoded;	
	}
	
	/**
	 * Compares the objects' service and account name to see if we're dealing
	 * with the same
	 * 
	 * @param string $uid The uid, either returned by getAccount().
	 * @param string $service The name of the service
	 */
	public function isSame($uid, $service) {
		$is_same = false;
		
		if ($service == $this->service) {
			$is_same = $this->getAccount() == $uid; 
		}
		
		return $is_same;
	}
	
	/**
	 * Constructs a string containing the account name and number (if present)
	 * from the users' account.
	 */
	public function getAccount() {
		if (!empty($this->account_number)) {
			if (!empty($this->account_name)) {
				return $this->account_name . " (#" . $this->account_number . ")";
			}
			else {
				return '#' . $this->account_number;
			}
		}
		
		return $this->account_name;
	}
	
	/**
	 * Parses the account label as returned by getAccount(), returning an array
	 * containing the name (if present) and the numerical id (if present).
	 * 
	 * Recognizes three formats:
	 * 1 - The account name and number: bastb (#447291)
	 * 2 - Just the account number: #447291
	 * 3 - Just the account name
	 * 
	 * @return array First position being the name, second the id.
	 */
	protected function unparseAccount($account) {
		$trimmed_account = trim($account);
		$name = $id = null;
		$name_number = explode(' ', $trimmed_account, 2);
		if ($name_number[0] == $trimmed_account) {
			// There was no whitespace in there
			if ('#' == substr($trimmed_account, 0, 1)) {
				$id = substr($trimmed_account, 1);
			}
			else {
				// Must be the name
				$name = $trimmed_account;
			}
		}
		else {
			$name = $name_number[0];
			$id = substr($name_number[1], 2, -1);
		}
		
		return array($name, $id);
	}
	
	/**
	 * Overwrites the users numerical id, persisting the change when 
	 * $save == true.
	 * 
	 * @param int $id The numerical id of the user.
	 * @param bool $save true => persist immediately, false => don't save yet.
	 */
	public function setId($id, $save = false) {
		$this->account_number = $id;
		if ($save) {
			$this->save();
		}
	}
	
	/**
	 * Overwrites the users display_name, persisting the change when 
	 * $save == true.
	 * 
	 * @param int $login The login of the user.
	 * @param bool $save true => persist immediately, false => don't save yet.
	 */
	public function setName($login, $save = false) {
		$this->account_name = $login;
		if ($save) {
			$this->save();
		}
	}
	
	public function getService() {
		return $this->service;	
	}
	
	/**
	 * Overwrites the stackexchange service, persisting the change when 
	 * $save == true.
	 * 
	 * @param int $service The StackExchange service, to be used as a parameter
	 *   in a call to the REST API (?site=$service)
	 * @param bool $save true => persist immediately, false => don't save yet.
	 */
	public function setService($service, $save = false) {
		$this->service = $service;
		if ($save) {
			$this->save();
		}
	}
	
	/**
	 * Persists the data to the user metadata. 
	 */
	public function save() {
		$uid = wp_get_current_user();
		update_user_meta($uid->ID, $this->meta_key, array("service" => $this->service, "id" => $this->account_number, "name" => $this->account_name));
	}
	
	/**
	 * Initializes the object from previously persisted metadata.
	 * 
	 * @param string $meta_key The label of the user metadata key to use.
	 *   Overwrites the default.
	 * 
	 * @throws LipsMetadataNotFoundException: When there's no metadata, or when
	 *   the metadata is not stored in an array.
	 */
	public static function fromMetadata($meta_key = LIPS_USER_META_STACKEXCHANGE) {
		$uid = wp_get_current_user();
		$meta = get_user_meta($uid->ID, $meta_key, true);
		if (!empty($meta) && is_array($meta)) {
			return new LinkedInProfileSyncStackExchangeUserDetails($meta['name'], $meta['id'], $meta['service']);
		}
		throw new LipsMetadataNotFoundException();
	}
}

class LipsMetadataNotFoundException extends LipsException { }
class LipsRequirementMissingException extends LipsException { }
?>