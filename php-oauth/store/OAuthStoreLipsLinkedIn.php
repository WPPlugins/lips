<?php
/**
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
 * $Id: OAuthStoreLipsLinkedIn.php 791447 2013-10-21 19:53:23Z bastb $
 *
 * This hooks the LiPS OAuth Store to the OAuth module.
 *
 */

require_once(dirname(__FILE__) . '/OAuthStoreSession.php');

class OAuthStoreLipsLinkedIn extends OAuthStoreSession {
	public function __construct( $options = array() )
	{
		$tokenstore = $options['tokenstore'];
		$this->tokenstore = $tokenstore;

		$this->session = array();
		// Get tokenstore, and store the details to the $_SESSION
		// 2013-10-14 bastb Added scope to gain access to the fullprofile, default is r_basicprofile, which does not gain access to the
		//   education, tags etc.
		$this->updateUri(array(
			'request_token' =>  'https://api.linkedin.com/uas/oauth/requestToken?scope=r_fullprofile',
			'authorize' => null,
			'access_token' => 'https://api.linkedin.com/uas/oauth/accessToken',
		));
		$this->updateToken($tokenstore);
	}

	public function updateToken($tokenstore) {
		$id = $tokenstore->getIdentificationToken(true);
		$req = $tokenstore->getAuthenticationRequestToken(true);
		$this->session['consumer_key'] = $id['token'];
		$this->session['consumer_secret'] = $id['secret'];
		$this->session['signature_methods'] = array('HMAC-SHA1');
		$this->session['server_uri'] = $this->server_uri;

		$auth = $tokenstore->getAuthenticationToken(true);
		if (isset($auth)) {
			$this->session['token'] = $auth['token'];
			$this->session['token_secret'] = $auth['secret'];
		}
	}

	public function updateUri($options) {
		foreach($options as $purpose => $url) {
			$this->session[$purpose . '_uri'] = $url;
		}

	}

	public function addServerToken ( $consumer_key, $token_type, $token, $token_secret, $user_id, $options = array() ) {
		parent::addServerToken($consumer_key, $token_type, $token, $token_secret, $user_id, $options);
		$tokenstore = $this->tokenstore;
		$token_array = array(
			"oauth_token" => $token,
			"oauth_token_secret" => $token_secret,
		);

		if ('request' == $token_type) {
			$tokenstore->set(OAuthAuthenticationRequestToken::fromToken($token_array), true);
			// Create an url as it uses the request including the token
			$this->updateUri(array("authorize" => sprintf('https://www.linkedin.com/uas/oauth/authenticate?oauth_token=%s', $token)));
		}
		else if ('access' == $token_type) {
			$tokenstore->set(OAuthAccessToken::fromToken($token_array), true);
		}
	}

	public function getServerTokenSecrets ( $consumer_key, $token, $token_type, $user_id, $name = '') {
		$token_secrets = parent::getServerTokenSecrets ( $consumer_key, $token, $token_type, $user_id, $name);
		$request_token = $this->tokenstore->getAuthenticationRequestToken(true);
		$token_secrets['token'] = $request_token['token'];
		$token_secrets['token_secret'] = $request_token['secret'];
		return $token_secrets;
	}
}
?>