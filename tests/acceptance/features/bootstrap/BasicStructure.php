<?php
/**
 * @author Sergio Bertolin <sbertolin@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\ResponseInterface;
use TestHelpers\OcsApiHelper;
use TestHelpers\SetupHelper;
use TestHelpers\HttpRequestHelper;

require __DIR__ . '/../../../../lib/composer/autoload.php';

/**
 * Basic functions needed by mostly everything
 */
trait BasicStructure {
	use AppConfiguration;
	use Auth;
	use Checksums;
	use Comments;
	use Provisioning;
	use Sharing;
	use Tags;
	use Trashbin;
	use WebDav;
	use CommandLine;

	/**
	 * @var array
	 */
	private $adminUsername = '';

	/**
	 * @var array
	 */
	private $adminPassword = '';

	/**
	 * @var string
	 */
	private $regularUserPassword = '';

	/**
	 * @var string
	 */
	private $alt1UserPassword = '';

	/**
	 * @var string
	 */
	private $alt2UserPassword = '';

	/**
	 * @var string
	 */
	private $alt3UserPassword = '';

	/**
	 * @var string
	 */
	private $alt4UserPassword = '';

	/**
	 * The password to use in tests that create a sub-admin user
	 *
	 * @var string
	 */
	private $subAdminPassword = '';

	/**
	 * The password to use in tests that create another admin user
	 *
	 * @var string
	 */
	private $alternateAdminPassword = '';

	/**
	 * The password to use in tests that create public link shares
	 *
	 * @var string
	 */
	private $publicLinkSharePassword = '';

	/**
	 * @var string
	 */
	private $ocPath = '';

	/**
	 * @var string location of the root folder of ownCloud on the local server under test
	 */
	private $localServerRoot = null;

	/**
	 * @var string
	 */
	private $currentUser = '';

	/**
	 * @var string
	 */
	private $currentServer = '';

	/**
	 * The base URL of the current server under test,
	 * without any terminating slash
	 * e.g. http://localhost:8080
	 *
	 * @var string
	 */
	private $baseUrl = '';

	/**
	 * The base URL of the local server under test,
	 * without any terminating slash
	 * e.g. http://localhost:8080
	 *
	 * @var string
	 */
	private $localBaseUrl = '';

	/**
	 * The base URL of the remote (federated) server under test,
	 * without any terminating slash
	 * e.g. http://localhost:8180
	 *
	 * @var string
	 */
	private $remoteBaseUrl = '';

	/**
	 *
	 *
	 * @var boolean true if TEST_SERVER_FED_URL is defined
	 */
	private $federatedServerExists = false;

	/**
	 * @var int
	 */
	private $ocsApiVersion = 1;

	/**
	 * @var ResponseInterface
	 */
	private $response = null;

	/**
	 * @var CookieJar
	 */
	private $cookieJar;

	/**
	 * @var string
	 */
	private $requestToken;

	/**
	 * @var string
	 */
	private $storageId = null;

	/**
	 * The local source IP address from which to initiate API actions.
	 * Defaults to system-selected address matching IP address family and scope.
	 *
	 * @var string|null
	 */
	private $sourceIpAddress = null;
	
	private $guzzleClientHeaders = [];

	/**
	 * BasicStructure constructor.
	 *
	 * @param string $baseUrl
	 * @param string $adminUsername
	 * @param string $adminPassword
	 * @param string $regularUserPassword
	 * @param string $ocPath
	 *
	 */
	public function __construct(
		$baseUrl,
		$adminUsername,
		$adminPassword,
		$regularUserPassword,
		$ocPath
	) {
		// Initialize your context here
		$this->baseUrl = \rtrim($baseUrl, '/');
		$this->adminUsername = $adminUsername;
		$this->adminPassword = $adminPassword;
		$this->regularUserPassword = $regularUserPassword;
		$this->localBaseUrl = $this->baseUrl;
		$this->currentServer = 'LOCAL';
		$this->cookieJar = new CookieJar();
		$this->ocPath = $ocPath;

		// These passwords are referenced in tests and can be overridden by
		// setting environment variables.
		$this->alt1UserPassword = "1234";
		$this->alt2UserPassword = "AaBb2Cc3Dd4";
		$this->alt3UserPassword = "aVeryLongPassword42TheMeaningOfLife";
		$this->alt4UserPassword = "ThisIsThe4thAlternatePwd";
		$this->subAdminPassword = "IamAJuniorAdmin42";
		$this->alternateAdminPassword = "IHave99LotsOfPriv";
		$this->publicLinkSharePassword = "publicPwd1";

		// in case of CI deployment we take the server url from the environment
		$testServerUrl = \getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = \rtrim($testServerUrl, '/');
			$this->localBaseUrl = $this->baseUrl;
		}

		// federated server url from the environment
		$testRemoteServerUrl = \getenv('TEST_SERVER_FED_URL');
		if ($testRemoteServerUrl !== false) {
			$this->remoteBaseUrl = \rtrim($testRemoteServerUrl, '/');
			$this->federatedServerExists = true;
		} else {
			$this->remoteBaseUrl = $this->localBaseUrl;
			$this->federatedServerExists = false;
		}

		// get the admin username from the environment (if defined)
		$adminUsernameFromEnvironment = $this->getAdminUsernameFromEnvironment();
		if ($adminUsernameFromEnvironment !== false) {
			$this->adminUsername = $adminUsernameFromEnvironment;
		}

		// get the admin password from the environment (if defined)
		$adminPasswordFromEnvironment = $this->getAdminPasswordFromEnvironment();
		if ($adminPasswordFromEnvironment !== false) {
			$this->adminPassword = $adminPasswordFromEnvironment;
		}

		// get the regular user password from the environment (if defined)
		$regularUserPasswordFromEnvironment = $this->getRegularUserPasswordFromEnvironment();
		if ($regularUserPasswordFromEnvironment !== false) {
			$this->regularUserPassword = $regularUserPasswordFromEnvironment;
		}

		// get the alternate(1) user password from the environment (if defined)
		$alt1UserPasswordFromEnvironment = $this->getAlt1UserPasswordFromEnvironment();
		if ($alt1UserPasswordFromEnvironment !== false) {
			$this->alt1UserPassword = $alt1UserPasswordFromEnvironment;
		}

		// get the alternate(2) user password from the environment (if defined)
		$alt2UserPasswordFromEnvironment = $this->getAlt2UserPasswordFromEnvironment();
		if ($alt2UserPasswordFromEnvironment !== false) {
			$this->alt2UserPassword = $alt2UserPasswordFromEnvironment;
		}

		// get the alternate(3) user password from the environment (if defined)
		$alt3UserPasswordFromEnvironment = $this->getAlt3UserPasswordFromEnvironment();
		if ($alt3UserPasswordFromEnvironment !== false) {
			$this->alt3UserPassword = $alt3UserPasswordFromEnvironment;
		}

		// get the alternate(4) user password from the environment (if defined)
		$alt4UserPasswordFromEnvironment = $this->getAlt4UserPasswordFromEnvironment();
		if ($alt4UserPasswordFromEnvironment !== false) {
			$this->alt4UserPassword = $alt4UserPasswordFromEnvironment;
		}

		// get the sub-admin password from the environment (if defined)
		$subAdminPasswordFromEnvironment = $this->getSubAdminPasswordFromEnvironment();
		if ($subAdminPasswordFromEnvironment !== false) {
			$this->subAdminPassword = $subAdminPasswordFromEnvironment;
		}

		// get the alternate admin password from the environment (if defined)
		$alternateAdminPasswordFromEnvironment = $this->getAlternateAdminPasswordFromEnvironment();
		if ($alternateAdminPasswordFromEnvironment !== false) {
			$this->alternateAdminPassword = $alternateAdminPasswordFromEnvironment;
		}

		// get the public link share password from the environment (if defined)
		$publicLinkSharePasswordFromEnvironment = $this->getPublicLinkSharePasswordFromEnvironment();
		if ($publicLinkSharePasswordFromEnvironment !== false) {
			$this->publicLinkSharePassword = $publicLinkSharePasswordFromEnvironment;
		}
	}

	/**
	 * Get the externally-defined admin username, if any
	 *
	 * @return string|false
	 */
	private static function getAdminUsernameFromEnvironment() {
		return \getenv('ADMIN_USERNAME');
	}

	/**
	 * Get the externally-defined admin password, if any
	 *
	 * @return string|false
	 */
	private static function getAdminPasswordFromEnvironment() {
		return \getenv('ADMIN_PASSWORD');
	}

	/**
	 * Get the externally-defined regular user password, if any
	 *
	 * @return string|false
	 */
	private static function getRegularUserPasswordFromEnvironment() {
		return \getenv('REGULAR_USER_PASSWORD');
	}

	/**
	 * Get the externally-defined alternate(1) user password, if any
	 *
	 * @return string|false
	 */
	private static function getAlt1UserPasswordFromEnvironment() {
		return \getenv('ALT1_USER_PASSWORD');
	}

	/**
	 * Get the externally-defined alternate(2) user password, if any
	 *
	 * @return string|false
	 */
	private static function getAlt2UserPasswordFromEnvironment() {
		return \getenv('ALT2_USER_PASSWORD');
	}

	/**
	 * Get the externally-defined alternate(3) user password, if any
	 *
	 * @return string|false
	 */
	private static function getAlt3UserPasswordFromEnvironment() {
		return \getenv('ALT3_USER_PASSWORD');
	}

	/**
	 * Get the externally-defined alternate(4) user password, if any
	 *
	 * @return string|false
	 */
	private static function getAlt4UserPasswordFromEnvironment() {
		return \getenv('ALT4_USER_PASSWORD');
	}

	/**
	 * Get the externally-defined sub-admin password, if any
	 *
	 * @return string|false
	 */
	private static function getSubAdminPasswordFromEnvironment() {
		return \getenv('SUB_ADMIN_PASSWORD');
	}

	/**
	 * Get the externally-defined alternate admin password, if any
	 *
	 * @return string|false
	 */
	private static function getAlternateAdminPasswordFromEnvironment() {
		return \getenv('ALTERNATE_ADMIN_PASSWORD');
	}

	/**
	 * Get the externally-defined public link share password, if any
	 *
	 * @return string|false
	 */
	private static function getPublicLinkSharePasswordFromEnvironment() {
		return \getenv('PUBLIC_LINK_SHARE_PASSWORD');
	}

	/**
	 * removes the scheme "http(s)://" (if any) from the front of a URL
	 * note: only needs to handle http or https
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function removeSchemeFromUrl($url) {
		return \preg_replace(
			"(^https?://)", "", $url
		);
	}

	/**
	 * @return string
	 */
	public function getOcPath() {
		return (string) $this->ocPath;
	}

	/**
	 * @return CookieJar
	 */
	public function getCookieJar() {
		return $this->cookieJar;
	}

	/**
	 * @return string
	 */
	public function getRequestToken() {
		return $this->requestToken;
	}

	/**
	 * returns the base URL (which is without a slash at the end)
	 *
	 * @return string
	 */
	public function getBaseUrl() {
		return $this->baseUrl;
	}

	/**
	 * returns the path of the base URL
	 * e.g. owncloud-core/10 if the baseUrl is http://localhost/owncloud-core/10
	 * the path is without a slash at the end and without a slash at the beginning
	 *
	 * @return string
	 */
	public function getBasePath() {
		return \ltrim(\parse_url($this->getBaseUrl(), PHP_URL_PATH), "/");
	}

	/**
	 * returns the base URL but without "http(s)://" in front of it
	 *
	 * @return string
	 */
	public function getBaseUrlWithoutScheme() {
		return $this->removeSchemeFromUrl($this->getBaseUrl());
	}

	/**
	 * returns the local base URL (which is without a slash at the end)
	 *
	 * @return string
	 */
	public function getLocalBaseUrl() {
		return $this->localBaseUrl;
	}

	/**
	 * returns the local base URL but without "http(s)://" in front of it
	 *
	 * @return string
	 */
	public function getLocalBaseUrlWithoutScheme() {
		return $this->removeSchemeFromUrl($this->getLocalBaseUrl());
	}

	/**
	 * returns the remote base URL (which is without a slash at the end)
	 *
	 * @return string
	 */
	public function getRemoteBaseUrl() {
		return $this->remoteBaseUrl;
	}

	/**
	 * returns the remote base URL but without "http(s)://" in front of it
	 *
	 * @return string
	 */
	public function getRemoteBaseUrlWithoutScheme() {
		return $this->removeSchemeFromUrl($this->getRemoteBaseUrl());
	}

	/**
	 * @return string
	 */
	public function getOcsApiVersion() {
		return $this->ocsApiVersion;
	}

	/**
	 * @return string|null
	 */
	public function getSourceIpAddress() {
		return $this->sourceIpAddress;
	}
	
	/**
	 * @param string $sourceIpAddress
	 *
	 * @return void
	 */
	public function setSourceIpAddress($sourceIpAddress) {
		$this->sourceIpAddress = $sourceIpAddress;
	}

	/**
	 * @return array
	 */
	public function getGuzzleClientHeaders() {
		return $this->guzzleClientHeaders;
	}
	
	/**
	 * @param array $guzzleClientHeaders ['X-Foo' => 'Bar']
	 *
	 * @return void
	 */
	public function setGuzzleClientHeaders($guzzleClientHeaders) {
		$this->guzzleClientHeaders = $guzzleClientHeaders;
	}

	/**
	 * @param array $guzzleClientHeaders ['X-Foo' => 'Bar']
	 *
	 * @return void
	 */
	public function addGuzzleClientHeaders($guzzleClientHeaders) {
		$this->guzzleClientHeaders = \array_merge(
			$this->guzzleClientHeaders, $guzzleClientHeaders
		);
	}

	/**
	 * @Given /^using OCS API version "([^"]*)"$/
	 *
	 * @param string $version
	 *
	 * @return void
	 */
	public function usingOcsApiVersion($version) {
		$this->ocsApiVersion = (int) $version;
	}

	/**
	 * @Given /^as user "([^"]*)"$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function asUser($user) {
		$this->currentUser = $this->getActualUsername($user);
	}

	/**
	 * @return string
	 */
	public function getCurrentUser() {
		return $this->currentUser;
	}

	/**
	 * returns $this->response
	 * some steps use that private var to store the response for other steps
	 *
	 * @return ResponseInterface
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * let this class remember a response that was received elsewhere
	 * so that steps in this class can be used to examine the response
	 *
	 * @param ResponseInterface $response
	 *
	 * @return void
	 */
	public function setResponse($response) {
		$this->response = $response;
	}

	/**
	 * @return string
	 */
	public function getCurrentServer() {
		return $this->currentServer;
	}

	/**
	 * @Given /^using server "(LOCAL|REMOTE)"$/
	 *
	 * @param string $server
	 *
	 * @return string Previous used server
	 */
	public function usingServer($server) {
		$previousServer = $this->currentServer;
		if ($server === 'LOCAL') {
			$this->baseUrl = $this->localBaseUrl;
			$this->currentServer = 'LOCAL';
		} else {
			$this->baseUrl = $this->remoteBaseUrl;
			$this->currentServer = 'REMOTE';
		}
		return $previousServer;
	}

	/**
	 *
	 * @return boolean
	 */
	public function federatedServerExists() {
		return $this->federatedServerExists;
	}

	/**
	 * disable CSRF
	 *
	 * @throws Exception
	 * @return string the previous setting of csrf.disabled
	 */
	public function disableCSRF() {
		return $this->setCSRFDotDisabled('true');
	}

	/**
	 * enable CSRF
	 *
	 * @throws Exception
	 * @return string the previous setting of csrf.disabled
	 */
	public function enableCSRF() {
		return $this->setCSRFDotDisabled('false');
	}

	/**
	 * set csrf.disabled
	 *
	 * @param string $setting "true", "false" or "" to delete the setting
	 *
	 * @throws Exception
	 * @return string the previous setting of csrf.disabled
	 */
	public function setCSRFDotDisabled($setting) {
		$oldCSRFSetting = SetupHelper::runOcc(
			['config:system:get', 'csrf.disabled']
		)['stdOut'];

		if ($setting === "") {
			SetupHelper::runOcc(['config:system:delete', 'csrf.disabled']);
		} elseif (($setting === 'true') || ($setting === 'false')) {
			SetupHelper::runOcc(
				[
					'config:system:set',
					'csrf.disabled',
					'--type',
					'boolean',
					'--value',
					$setting
				]
			);
		} else {
			throw new \http\Exception\InvalidArgumentException(
				'setting must be "true", "false" or ""'
			);
		}
		return \trim($oldCSRFSetting);
	}

	/**
	 * @When /^the user sends HTTP method "([^"]*)" to OCS API endpoint "([^"]*)"$/
	 * @Given /^the user has sent HTTP method "([^"]*)" to OCS API endpoint "([^"]*)"$/
	 *
	 * @param string $verb
	 * @param string $url
	 *
	 * @return void
	 */
	public function theUserSendsToOcsApiEndpoint($verb, $url) {
		$this->theUserSendsToOcsApiEndpointWithBody($verb, $url, null);
	}

	/**
	 * @When /^user "([^"]*)" sends HTTP method "([^"]*)" to OCS API endpoint "([^"]*)"$/
	 * @Given /^user "([^"]*)" has sent HTTP method "([^"]*)" to API endpoint "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 *
	 * @return void
	 */
	public function userSendsToOcsApiEndpoint($user, $verb, $url) {
		$this->userSendsHTTPMethodToOcsApiEndpointWithBody(
			$user,
			$verb,
			$url,
			null
		);
	}

	/**
	 * Parses the xml answer to get ocs response which doesn't match with
	 * http one in v1 of the api.
	 *
	 * @param ResponseInterface $response
	 *
	 * @return string
	 */
	public function getOCSResponseStatusCode($response) {
		return (string) $this->getResponseXml($response)->meta[0]->statuscode;
	}

	/**
	 * Parses the response as XML
	 *
	 * @param ResponseInterface $response
	 *
	 * @return SimpleXMLElement
	 */
	public function getResponseXml($response = null) {
		if ($response === null) {
			$response = $this->response;
		}

		return HttpRequestHelper::getResponseXml($response);
	}

	/**
	 * Parses the xml answer to get ocs response message which doesn't match with
	 * http one in v1 of the api.
	 *
	 * @param ResponseInterface $response
	 *
	 * @return string
	 */
	public function getOCSResponseStatusMessage($response) {
		return (string) $this->getResponseXml($response)->meta[0]->message;
	}

	/**
	 * Parses the xml answer to get the requested key and sub-key
	 *
	 * @param ResponseInterface $response
	 * @param string $key1
	 * @param string $key2
	 *
	 * @return string
	 */
	public function getXMLKey1Key2Value($response, $key1, $key2) {
		return $this->getResponseXml($response)->$key1->$key2;
	}

	/**
	 * Parses the xml answer to get the requested key sequence
	 *
	 * @param ResponseInterface $response
	 * @param string $key1
	 * @param string $key2
	 * @param string $key3
	 *
	 * @return string
	 */
	public function getXMLKey1Key2Key3Value($response, $key1, $key2, $key3) {
		return $this->getResponseXml($response)->$key1->$key2->$key3;
	}

	/**
	 * Parses the xml answer to get the requested attribute value
	 *
	 * @param ResponseInterface $response
	 * @param string $key1
	 * @param string $key2
	 * @param string $key3
	 * @param string $attribute
	 *
	 * @return string
	 */
	public function getXMLKey1Key2Key3AttributeValue(
		$response, $key1, $key2, $key3, $attribute
	) {
		return (string) $this->getResponseXml($response)->$key1->$key2->$key3->attributes()->$attribute;
	}

	/**
	 * This function is needed to use a vertical fashion in the gherkin tables.
	 *
	 * @param array $arrayOfArrays
	 *
	 * @return array
	 */
	public function simplifyArray($arrayOfArrays) {
		$a = \array_map(
			function ($subArray) {
				return $subArray[0];
			}, $arrayOfArrays
		);
		return $a;
	}

	/**
	 * @When /^the user sends HTTP method "([^"]*)" to OCS API endpoint "([^"]*)" with body$/
	 * @Given /^the user has sent HTTP method "([^"]*)" to OCS API endpoint "([^"]*)" with body$/
	 *
	 * @param string $verb
	 * @param string $url
	 * @param TableNode $body
	 *
	 * @return void
	 */
	public function theUserSendsToOcsApiEndpointWithBody($verb, $url, $body) {
		$this->userSendsHTTPMethodToOcsApiEndpointWithBody(
			$this->currentUser,
			$verb,
			$url,
			$body
		);
	}

	/**
	 * @When /^user "([^"]*)" sends HTTP method "([^"]*)" to OCS API endpoint "([^"]*)" with body$/
	 * @Given /^user "([^"]*)" has sent HTTP method "([^"]*)" to OCS API endpoint "([^"]*)" with body$/
	 *
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 * @param TableNode|null $body
	 *
	 * @return void
	 */
	public function userSendsHTTPMethodToOcsApiEndpointWithBody(
		$user, $verb, $url, $body
	) {

		/**
		 * array of the data to be sent in the body.
		 * contains $body data converted to an array
		 *
		 * @var array $bodyArray
		 */
		$bodyArray = [];
		if ($body instanceof TableNode) {
			$bodyArray = $body->getRowsHash();
		}

		if ($user !== 'UNAUTHORIZED_USER') {
			$user = $this->getActualUsername($user);
			$password = $this->getPasswordForUser($user);
		} else {
			$user = null;
			$password = null;
		}

		$this->response = OcsApiHelper::sendRequest(
			$this->getBaseUrl(),
			$user, $password, $verb, $url, $bodyArray, $this->ocsApiVersion
		);
	}

	/**
	 * @When /^user "([^"]*)" sends HTTP method "([^"]*)" to URL "([^"]*)"$/
	 * @Given /^user "([^"]*)" has sent HTTP method "([^"]*)" to URL "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 *
	 * @return void
	 */
	public function userSendsHTTPMethodToUrl($user, $verb, $url) {
		$this->sendingToWithDirectUrl($user, $verb, $url, null);
	}

	/**
	 * @When /^user "([^"]*)" sends HTTP method "([^"]*)" to URL "([^"]*)" with password "([^"]*)"$/
	 * @Given /^user "([^"]*)" has sent HTTP method "([^"]*)" to URL "([^"]*)" with password "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 * @param string $password
	 *
	 * @return void
	 */
	public function userSendsHTTPMethodToUrlWithPassword($user, $verb, $url, $password) {
		$this->sendingToWithDirectUrl($user, $verb, $url, null, $password);
	}

	/**
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 * @param TableNode $body
	 * @param string $password
	 *
	 * @return void
	 */
	public function sendingToWithDirectUrl($user, $verb, $url, $body, $password = null) {
		$fullUrl = $this->getBaseUrl() . $url;

		if ($password === null) {
			$password = $this->getPasswordForUser($user);
		}
		
		$headers = $this->guzzleClientHeaders;

		$config = null;
		if ($this->sourceIpAddress !== null) {
			$config = [
				'curl' => [
					CURLOPT_INTERFACE => $this->sourceIpAddress
				]
			];
		}
		
		$cookies = null;
		if (!empty($this->cookieJar->toArray())) {
			$cookies = $this->cookieJar;
		}

		$fd = null;
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
		}

		if (isset($this->requestToken)) {
			$headers['requesttoken'] = $this->requestToken;
		}

		$this->response = HttpRequestHelper::sendRequest(
			$fullUrl, $verb, $user, $password, $headers, $fd, $config, $cookies
		);
	}

	/**
	 * @param string $url
	 *
	 * @return bool
	 */
	public function isAPublicLinkUrl($url) {
		$urlEnding = \substr($url, \strlen($this->getBaseUrl() . '/'));
		return \preg_match("%^(index.php/)?s/([a-zA-Z0-9]{15})$%", $urlEnding);
	}

	/**
	 * @Then /^the OCS status code should be "([^"]*)"$/
	 *
	 * @param int|int[] $statusCode
	 * @param string $message
	 *
	 * @return void
	 */
	public function theOCSStatusCodeShouldBe($statusCode, $message = "") {
		if ($message === "") {
			$message = "OCS status code is not the expected value";
		}

		if (\is_array($statusCode)) {
			PHPUnit_Framework_Assert::assertContains(
				$this->getOCSResponseStatusCode($this->response), $statusCode,
				$message
			);
		} else {
			PHPUnit_Framework_Assert::assertEquals(
				$statusCode, $this->getOCSResponseStatusCode($this->response),
				$message
			);
		}
	}

	/**
	 * @Then /^the HTTP status code should be "([^"]*)"$/
	 *
	 * @param int|int[] $statusCode
	 * @param string $message
	 *
	 * @return void
	 */
	public function theHTTPStatusCodeShouldBe($statusCode, $message = "") {
		if ($message === "") {
			$message = "HTTP status code is not the expected value";
		}

		if (\is_array($statusCode)) {
			PHPUnit_Framework_Assert::assertContains(
				$this->response->getStatusCode(), $statusCode,
				$message
			);
		} else {
			PHPUnit_Framework_Assert::assertEquals(
				$statusCode, $this->response->getStatusCode(), $message
			);
		}
	}

	/**
	 * Check the text in an HTTP reason phrase
	 *
	 * @Then /^the HTTP reason phrase should be "([^"]*)"$/
	 *
	 * @param string $reasonPhrase
	 *
	 * @return void
	 */
	public function theHTTPReasonPhraseShouldBe($reasonPhrase) {
		PHPUnit_Framework_Assert::assertEquals(
			$reasonPhrase,
			$this->getResponse()->getReasonPhrase(),
			'Unexpected HTTP reason phrase in response'
		);
	}

	/**
	 * Check the text in an HTTP reason phrase
	 * Use this step form if the expected text contains double quotes,
	 * single quotes and other content that theHTTPReasonPhraseShouldBe()
	 * cannot handle.
	 *
	 * After the step, write the expected text in PyString form like:
	 *
	 * """
	 * File "abc.txt" can't be shared due to reason "xyz"
	 * """
	 *
	 * @Then /^the HTTP reason phrase should be:$/
	 *
	 * @param PyStringNode $reasonPhrase
	 *
	 * @return void
	 */
	public function theHTTPReasonPhraseShouldBePyString(
		PyStringNode $reasonPhrase
	) {
		PHPUnit_Framework_Assert::assertEquals(
			$reasonPhrase->getRaw(),
			$this->getResponse()->getReasonPhrase(),
			'Unexpected HTTP reason phrase in response'
		);
	}

	/**
	 * Check the text in an OCS status message
	 *
	 * @Then /^the OCS status message should be "([^"]*)"$/
	 *
	 * @param string $statusMessage
	 *
	 * @return void
	 */
	public function theOCSStatusMessageShouldBe($statusMessage) {
		PHPUnit_Framework_Assert::assertEquals(
			$statusMessage,
			$this->getOCSResponseStatusMessage(
				$this->getResponse()
			),
			'Unexpected OCS status message in response'
		);
	}

	/**
	 * Check the text in an OCS status message.
	 * Use this step form if the expected text contains double quotes,
	 * single quotes and other content that theOCSStatusMessageShouldBe()
	 * cannot handle.
	 *
	 * After the step, write the expected text in PyString form like:
	 *
	 * """
	 * File "abc.txt" can't be shared due to reason "xyz"
	 * """
	 *
	 * @Then /^the OCS status message should be:$/
	 *
	 * @param PyStringNode $statusMessage
	 *
	 * @return void
	 */
	public function theOCSStatusMessageShouldBePyString(
		PyStringNode $statusMessage
	) {
		PHPUnit_Framework_Assert::assertEquals(
			$statusMessage->getRaw(),
			$this->getOCSResponseStatusMessage(
				$this->getResponse()
			),
			'Unexpected OCS status message in response'
		);
	}

	/**
	 * @Then /^the XML "([^"]*)" "([^"]*)" value should be "([^"]*)"$/
	 *
	 * @param string $key1
	 * @param string $key2
	 * @param string $idText
	 *
	 * @return void
	 */
	public function theXMLKey1Key2ValueShouldBe($key1, $key2, $idText) {
		PHPUnit_Framework_Assert::assertEquals(
			$idText,
			$this->getXMLKey1Key2Value($this->response, $key1, $key2)
		);
	}

	/**
	 * @Then /^the XML "([^"]*)" "([^"]*)" "([^"]*)" value should be "([^"]*)"$/
	 *
	 * @param string $key1
	 * @param string $key2
	 * @param string $key3
	 * @param string $idText
	 *
	 * @return void
	 */
	public function theXMLKey1Key2Key3ValueShouldBe(
		$key1, $key2, $key3, $idText
	) {
		PHPUnit_Framework_Assert::assertEquals(
			$idText,
			$this->getXMLKey1Key2Key3Value($this->response, $key1, $key2, $key3)
		);
	}

	/**
	 * @Then /^the XML "([^"]*)" "([^"]*)" "([^"]*)" "([^"]*)" attribute value should be a valid version string$/
	 *
	 * @param string $key1
	 * @param string $key2
	 * @param string $key3
	 * @param string $attribute
	 *
	 * @return void
	 */
	public function theXMLKey1Key2AttributeValueShouldBe(
		$key1, $key2, $key3, $attribute
	) {
		$value = $this->getXMLKey1Key2Key3AttributeValue(
			$this->response, $key1, $key2, $key3, $attribute
		);
		PHPUnit_Framework_Assert::assertTrue(
			\version_compare($value, '0.0.1') >= 0,
			"attribute $attribute value $value is not a valid version string"
		);
	}

	/**
	 * @param ResponseInterface $response
	 *
	 * @return void
	 */
	public function extractRequestTokenFromResponse(ResponseInterface $response) {
		$this->requestToken = \substr(
			\preg_replace(
				'/(.*)data-requesttoken="(.*)">(.*)/sm', '\2',
				$response->getBody()->getContents()
			),
			0,
			89
		);
	}

	/**
	 * @Given /^user "([^"]*)" has logged in to a web-style session$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userHasLoggedInToAWebStyleSessionUsingTheAPI($user) {
		$loginUrl = $this->getBaseUrl() . '/login';
		// Request a new session and extract CSRF token
		
		$config = null;
		if ($this->sourceIpAddress !== null) {
			$config = [
				'curl' => [
					CURLOPT_INTERFACE => $this->sourceIpAddress
				]
			];
		}

		$response = HttpRequestHelper::get(
			$loginUrl, null, null, $this->guzzleClientHeaders, null, $config, $this->cookieJar
		);
		$this->extractRequestTokenFromResponse($response);

		// Login and extract new token
		$password = $this->getPasswordForUser($user);
		$body = [
			'user' => $user,
			'password' => $password,
			'requesttoken' => $this->requestToken
		];
		$response = HttpRequestHelper::post(
			$loginUrl, null, null, $this->guzzleClientHeaders, $body, $config, $this->cookieJar
		);
		$this->extractRequestTokenFromResponse($response);
	}

	/**
	 * @When the client sends a :method to :url with requesttoken
	 * @Given the client has sent a :method to :url with requesttoken
	 *
	 * @param string $method
	 * @param string $url
	 *
	 * @return void
	 */
	public function sendingAToWithRequesttoken($method, $url) {
		$headers = $this->guzzleClientHeaders;
		
		$config = null;
		if ($this->sourceIpAddress !== null) {
			$config = [
				'curl' => [
					CURLOPT_INTERFACE => $this->sourceIpAddress
				]
			];
		}

		$headers['requesttoken'] = $this->requestToken;

		$url = $this->getBaseUrl() . $url;
		$this->response = HttpRequestHelper::sendRequest(
			$url, $method, null, null, $headers, null, $config, $this->cookieJar
		);
	}

	/**
	 * @When the client sends a :method to :url without requesttoken
	 * @Given the client has sent a :method to :url without requesttoken
	 *
	 * @param string $method
	 * @param string $url
	 *
	 * @return void
	 */
	public function sendingAToWithoutRequesttoken($method, $url) {
		$config = null;
		if ($this->sourceIpAddress !== null) {
			$config = [
				'curl' => [
					CURLOPT_INTERFACE => $this->sourceIpAddress
				]
			];
		}

		$url = $this->getBaseUrl() . $url;
		$this->response = HttpRequestHelper::sendRequest(
			$url, $method, null, null, $this->guzzleClientHeaders,
			null, $config, $this->cookieJar
		);
	}

	/**
	 * @param string $path
	 * @param string $filename
	 *
	 * @return void
	 */
	public static function removeFile($path, $filename) {
		if (\file_exists("$path$filename")) {
			\unlink("$path$filename");
		}
	}

	/**
	 * Creates a file locally in the file system of the test runner
	 * The file will be available to upload to the server
	 *
	 * @param string $name
	 * @param string $size
	 *
	 * @return void
	 */
	public function createLocalFileOfSpecificSize($name, $size) {
		$folder = $this->workStorageDirLocation();
		if (!\is_dir($folder)) {
			\mkDir($folder);
		}
		$file = \fopen($folder . $name, 'w');
		\fseek($file, $size - 1, SEEK_CUR);
		\fwrite($file, 'a'); // write a dummy char at SIZE position
		\fclose($file);
	}

	/**
	 * Make a directory under the server root on the ownCloud server
	 *
	 * @param string $dirPathFromServerRoot e.g. 'apps2/myapp/appinfo'
	 *
	 * @return void
	 * @throws Exception
	 */
	public function mkDirOnServer($dirPathFromServerRoot) {
		SetupHelper::mkDirOnServer(
			$dirPathFromServerRoot,
			$this->getBaseUrl(),
			$this->getAdminUsername(),
			$this->getAdminPassword()
		);
	}

	/**
	 * @param string $filePathFromServerRoot
	 * @param string $content
	 *
	 * @return void
	 */
	public function createFileOnServerWithContent(
		$filePathFromServerRoot, $content
	) {
		SetupHelper::createFileOnServer(
			$filePathFromServerRoot,
			$content,
			$this->getBaseUrl(),
			$this->getAdminUsername(),
			$this->getAdminPassword()
		);
	}

	/**
	 * @Given file :filename with text :text has been created in local storage on the server
	 *
	 * @param string $filename
	 * @param string $text
	 *
	 * @return void
	 */
	public function fileHasBeenCreatedInLocalStorageWithText($filename, $text) {
		$this->createFileOnServerWithContent(
			LOCAL_STORAGE_DIR_ON_REMOTE_SERVER . "/$filename", $text
		);
	}

	/**
	 * @Given file :filename has been deleted from local storage on the server
	 *
	 * @param string $filename
	 *
	 * @return void
	 */
	public function fileHasBeenDeletedInLocalStorage($filename) {
		SetupHelper::deleteFileOnServer(
			LOCAL_STORAGE_DIR_ON_REMOTE_SERVER . "/$filename",
			$this->getBaseUrl(),
			$this->getAdminUsername(),
			$this->getAdminPassword()
		);
	}

	/**
	 * @return string
	 */
	public function getAdminUsername() {
		return (string) $this->adminUsername;
	}

	/**
	 * @return string
	 */
	public function getAdminPassword() {
		return (string) $this->adminPassword;
	}

	/**
	 * @param string $userName
	 *
	 * @return string
	 */
	public function getPasswordForUser($userName) {
		$userName = $this->getActualUsername($userName);
		if ($userName === $this->getAdminUsername()) {
			return (string) $this->getAdminPassword();
		} elseif (\array_key_exists($userName, $this->createdUsers)) {
			return (string) $this->createdUsers[$userName]['password'];
		} elseif (\array_key_exists($userName, $this->createdRemoteUsers)) {
			return (string) $this->createdRemoteUsers[$userName]['password'];
		} elseif ($userName === 'regularuser') {
			return (string) $this->regularUserPassword;
		} elseif ($userName === 'user0') {
			return (string) $this->regularUserPassword;
		} elseif ($userName === 'user1') {
			return (string) $this->alt1UserPassword;
		} elseif ($userName === 'user2') {
			return (string) $this->alt2UserPassword;
		} elseif ($userName === 'user3') {
			return (string) $this->alt3UserPassword;
		} elseif ($userName === 'user4') {
			return (string) $this->alt4UserPassword;
		} elseif ($userName === 'usergrp') {
			return (string) $this->regularUserPassword;
		} elseif ($userName === 'sharee1') {
			return (string) $this->regularUserPassword;
		} else {
			// The user has not been created yet and is not one of the pre-known
			// users. So let the caller have the default password.
			return (string) $this->regularUserPassword;
		}
	}

	/**
	 * Get the display name of the user.
	 *
	 * For users that have already been created, return their display name.
	 * For special known user names, return the display name that is also used by LDAP tests.
	 * For other users, return null. They will not be assigned any particular
	 * display name by this function.
	 *
	 * @param string $userName
	 *
	 * @return string|null
	 */
	public function getDisplayNameForUser($userName) {
		$userName = $this->getActualUsername($userName);
		// The hard-coded user names and display names are also in ldap-users.ldif
		// for testing in an LDAP environment. The mapping must be kept the
		// same in both places.
		if (\array_key_exists($userName, $this->createdUsers)) {
			return (string) $this->createdUsers[$userName]['displayname'];
		} elseif (\array_key_exists($userName, $this->createdRemoteUsers)) {
			return (string)$this->createdRemoteUsers[$userName]['displayname'];
		} elseif ($userName === 'regularuser') {
			return 'Regular User';
		} elseif ($userName === 'user0') {
			return 'User Zero';
		} elseif ($userName === 'user1') {
			return 'User One';
		} elseif ($userName === 'user2') {
			return 'User Two';
		} elseif ($userName === 'user3') {
			return 'User Three';
		} elseif ($userName === 'user4') {
			return 'User Four';
		} elseif ($userName === 'usergrp') {
			return 'User Grp';
		} elseif ($userName === 'sharee1') {
			return 'Sharee One';
		} else {
			return null;
		}
	}

	/**
	 * Get the email address of the user.
	 *
	 * For users that have already been created, return their email address.
	 * For special known user names, return the email address that is also used by LDAP tests.
	 * For other users, return null. They will not be assigned any particular
	 * email address by this function.
	 *
	 * @param string $userName
	 *
	 * @return string|null
	 */
	public function getEmailAddressForUser($userName) {
		$userName = $this->getActualUsername($userName);
		// The hard-coded user names and email addresses are also in ldap-users.ldif
		// for testing in an LDAP environment. The mapping must be kept the
		// same in both places.
		if (\array_key_exists($userName, $this->createdUsers)) {
			return (string) $this->createdUsers[$userName]['email'];
		} elseif (\array_key_exists($userName, $this->createdRemoteUsers)) {
			return (string)$this->createdRemoteUsers[$userName]['email'];
		} elseif ($userName === 'regularuser') {
			return 'regularuser@example.org';
		} elseif ($userName === 'user0') {
			return 'user0@example.org';
		} elseif ($userName === 'user1') {
			return 'user1@example.org';
		} elseif ($userName === 'user2') {
			return 'user2@example.org';
		} elseif ($userName === 'user3') {
			return 'user3@example.org';
		} elseif ($userName === 'user4') {
			return 'user4@example.org';
		} elseif ($userName === 'usergrp') {
			return 'usergrp@example.org';
		} elseif ($userName === 'sharee1') {
			return 'sharee1@example.org';
		} else {
			return null;
		}
	}

	/**
	 * @param string $functionalUsername
	 *
	 * @return string
	 */
	public function getActualUsername($functionalUsername) {
		if ($functionalUsername === "%admin%") {
			return (string) $this->getAdminUsername();
		} else {
			return $functionalUsername;
		}
	}

	/**
	 * @param string $functionalPassword
	 *
	 * @return string
	 */
	public function getActualPassword($functionalPassword) {
		if ($functionalPassword === "%regular%") {
			return (string) $this->regularUserPassword;
		} elseif ($functionalPassword === "%alt1%") {
			return (string) $this->alt1UserPassword;
		} elseif ($functionalPassword === "%alt2%") {
			return (string) $this->alt2UserPassword;
		} elseif ($functionalPassword === "%alt3%") {
			return (string) $this->alt3UserPassword;
		} elseif ($functionalPassword === "%alt4%") {
			return (string) $this->alt4UserPassword;
		} elseif ($functionalPassword === "%subadmin%") {
			return (string) $this->subAdminPassword;
		} elseif ($functionalPassword === "%admin%") {
			return (string) $this->getAdminPassword();
		} elseif ($functionalPassword === "%altadmin%") {
			return (string) $this->alternateAdminPassword;
		} elseif ($functionalPassword === "%public%") {
			return (string) $this->publicLinkSharePassword;
		} else {
			return $functionalPassword;
		}
	}

	/**
	 * @param string $userName
	 *
	 * @return array
	 */
	public function getAuthOptionForUser($userName) {
		return [$userName, $this->getPasswordForUser($userName)];
	}

	/**
	 * @return array
	 */
	public function getAuthOptionForAdmin() {
		return $this->getAuthOptionForUser($this->getAdminUsername());
	}

	/**
	 * @When the administrator requests status.php
	 *
	 * @return void
	 */
	public function getStatusPhp() {
		$fullUrl = $this->getBaseUrl() . "/status.php";
		
		$config = null;
		if ($this->sourceIpAddress !== null) {
			$config = [
				'curl' => [
					CURLOPT_INTERFACE => $this->sourceIpAddress
				]
			];
		}

		$this->response = HttpRequestHelper::get(
			$fullUrl, $this->getAdminUsername(),
			$this->getAdminPassword(), $this->guzzleClientHeaders, null, $config
		);
	}

	/**
	 * @Then the json responded should match with
	 *
	 * @param PyStringNode $jsonExpected
	 *
	 * @return void
	 */
	public function jsonRespondedShouldMatch(PyStringNode $jsonExpected) {
		$jsonExpectedEncoded = \json_encode($jsonExpected->getRaw());
		$jsonRespondedEncoded = \json_encode((string) $this->response->getBody());
		PHPUnit\Framework\Assert::assertEquals(
			$jsonExpectedEncoded, $jsonRespondedEncoded
		);
	}

	/**
	 * @Then the status.php response should match with
	 *
	 * @param PyStringNode $jsonExpected
	 *
	 * @return void
	 */
	public function statusPhpRespondedShouldMatch(PyStringNode $jsonExpected) {
		$jsonExpectedDecoded = \json_decode($jsonExpected->getRaw(), true);
		$jsonRespondedEncoded
			= \json_encode(\json_decode($this->response->getBody(), true));
		$runOccStatus = $this->runOcc(['status']);
		if ($runOccStatus === 0) {
			$output = \explode("- ", $this->lastStdOut);
			$version = \explode(": ", $output[3]);
			PHPUnit_Framework_Assert::assertEquals(
				"version", $version[0]
			);
			$versionString = \explode(": ", $output[4]);
			PHPUnit_Framework_Assert::assertEquals(
				"versionstring", $versionString[0]
			);
			$jsonExpectedDecoded['version'] = \trim($version[1]);
			$jsonExpectedDecoded['versionstring'] = \trim($versionString[1]);
			$jsonExpectedEncoded = \json_encode($jsonExpectedDecoded);
			PHPUnit\Framework\Assert::assertEquals(
				$jsonExpectedEncoded, $jsonRespondedEncoded
			);
		} else {
			PHPUnit_Framework_Assert::fail(
				"Cannot get version variables from occ - status $runOccStatus"
			);
		}
	}

	/**
	 * substitutes codes like %base_url% with the value
	 * if the given value does not have anything to be substituted
	 * then it is returned unmodified
	 *
	 * @param string $value
	 * @param array $functions associative array of functions and parameters to be
	 *                         called on every replacement string before the
	 *                         replacement
	 *                         function name has to be the key and the parameters an
	 *                         own array
	 *                         the replacement itself will be used as first parameter
	 *                         e.g. substituteInLineCodes($value, ['preg_quote' => ['/']])
	 *
	 * @return string
	 */
	public function substituteInLineCodes($value, $functions = []) {
		$substitutions = [
			[
				"code" => "%base_url%",
				"function" => [
					$this,
					"getBaseUrl"
				],
				"parameter" => []
			],
			[
				"code" => "%base_url_without_scheme%",
				"function" => [
					$this,
					"getBaseUrlWithoutScheme"
				],
				"parameter" => []
			],
			[
				"code" => "%remote_server%",
				"function" => [
					$this,
					"getRemoteBaseUrl"
				],
				"parameter" => []
			],
			[
				"code" => "%remote_server_without_scheme%",
				"function" => [
					$this,
					"getRemoteBaseUrlWithoutScheme"
				],
				"parameter" => []
			],
			[
				"code" => "%local_server%",
				"function" => [
					$this,
					"getLocalBaseUrl"
				],
				"parameter" => []
			],
			[
				"code" => "%local_server_without_scheme%",
				"function" => [
					$this,
					"getLocalBaseUrlWithoutScheme"
				],
				"parameter" => []
			]
		];

		foreach ($substitutions as $substitution) {
			$replacement = \call_user_func_array(
				$substitution["function"],
				$substitution["parameter"]
			);
			foreach ($functions as $function => $parameters) {
				$replacement = \call_user_func_array(
					$function,
					\array_merge([$replacement], $parameters)
				);
			}
			$value = \str_replace(
				$substitution["code"],
				$replacement,
				$value
			);
		}
		return $value;
	}

	/**
	 * @return string
	 */
	public function temporaryStorageSubfolderName() {
		return "work_tmp";
	}

	/**
	 * @return string
	 */
	public function acceptanceTestsDirLocation() {
		return \dirname(__FILE__) . "/../../";
	}

	/**
	 * @return string
	 */
	public function workStorageDirLocation() {
		return $this->acceptanceTestsDirLocation() . $this->temporaryStorageSubfolderName() . "/";
	}

	/**
	 * Get the path of the ownCloud server root directory
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getServerRoot() {
		if ($this->localServerRoot === null) {
			$this->localServerRoot = SetupHelper::getServerRoot(
				$this->getBaseUrl(),
				$this->getAdminUsername(),
				$this->getAdminPassword()
			);
		}
		return $this->localServerRoot;
	}

	/**
	 * @BeforeScenario @local_storage
	 *
	 * @return void
	 */
	public function setupLocalStorageBefore() {
		SetupHelper::init(
			$this->getAdminUsername(),
			$this->getAdminPassword(),
			$this->getBaseUrl(),
			$this->getOcPath()
		);
		SetupHelper::mkDirOnServer(
			LOCAL_STORAGE_DIR_ON_REMOTE_SERVER
		);
		$result = SetupHelper::runOcc(
			[
				'files_external:create',
				'local_storage',
				'local',
				'null::null',
				'-c',
				'datadir=' . $this->getServerRoot() . '/' . LOCAL_STORAGE_DIR_ON_REMOTE_SERVER
			]
		);
		// stdOut should have a string like "Storage created with id 65"
		$storageIdWords = \explode(" ", \trim($result['stdOut']));
		$this->storageId = $storageIdWords[4];
		SetupHelper::runOcc(
			[
				'files_external:option',
				$this->storageId,
				'enable_sharing',
				'true'
			]
		);
	}

	/**
	 * @AfterScenario @local_storage
	 *
	 * @return void
	 */
	public function removeLocalStorageAfter() {
		if ($this->storageId !== null) {
			SetupHelper::runOcc(
				[
					'files_external:delete',
					'-y',
					$this->storageId
				]
			);
		}
		SetupHelper::rmDirOnServer(
			LOCAL_STORAGE_DIR_ON_REMOTE_SERVER
		);
		SetupHelper::rmDirOnServer(
			TEMPORARY_STORAGE_DIR_ON_REMOTE_SERVER
		);
	}

	/**
	 * @BeforeSuite
	 *
	 * @param BeforeSuiteScope $scope
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function useBigFileIDs(BeforeSuiteScope $scope) {
		$fullUrl = \getenv('TEST_SERVER_URL');
		if (\substr($fullUrl, -1) !== '/') {
			$fullUrl .= '/';
		}
		$fullUrl .= "ocs/v1.php/apps/testing/api/v1/increasefileid";
		$suiteSettingsContexts = $scope->getSuite()->getSettings()['contexts'];
		$adminUsername = null;
		$adminPassword = null;
		foreach ($suiteSettingsContexts as $context) {
			if (isset($context[__CLASS__])) {
				$adminUsername = $context[__CLASS__]['adminUsername'];
				$adminPassword = $context[__CLASS__]['adminPassword'];
				break;
			}
		}

		// get the admin username from the environment (if defined)
		$adminUsernameFromEnvironment = self::getAdminUsernameFromEnvironment();
		if ($adminUsernameFromEnvironment !== false) {
			$adminUsername = $adminUsernameFromEnvironment;
		}

		// get the admin password from the environment (if defined)
		$adminPasswordFromEnvironment = self::getAdminPasswordFromEnvironment();
		if ($adminPasswordFromEnvironment !== false) {
			$adminPassword = $adminPasswordFromEnvironment;
		}

		if (($adminUsername === null) || ($adminPassword === null)) {
			throw new \Exception(
				"Could not find adminUsername and/or adminPassword in useBigFileIDs"
			);
		}

		HttpRequestHelper::post($fullUrl, $adminUsername, $adminPassword);
	}
}
