<?php

// dependencies: cURL php extension
class PlanetHosterAPIClient {
    private $apiUser; // @var string
    private $apiKey; // @var string
    private $rawResponse; // @var string
    private $baseUrl; // @var string
    private $userAgent; // @var string
    public $parsedResponse; // @var array|null(JSON)
    public $error; // @var string

    function __construct($apiUser, $apiKey, array $opts = array()) {
        $this->apiUser = $apiUser;
        $this->apiKey = $apiKey;
        if ($opts && isset($opts['timeout'])) {
            $this->timeout = (int)$opts['timeout'];
        } else {
            $this->timeout = 60;
        }
        if ($opts && isset($opts['userAgent'])) {
            $this->userAgent = $opts['userAgent'];
        } else {
            $this->userAgent = '';
        }
        $this->baseUrl = 'https://api.planethoster.net/reseller-api';
        $this->newRequest();
    }

    /* public */

    // reset response state
    public function newRequest() {
        $this->rawResponse = '';
        $this->parsedResponse = null;
        $this->error = '';
    }

    public function get($actionName, $path, array $params) {
        $params['api_user'] = $this->apiUser;
        $params['api_key'] = $this->apiKey;
        $paramsString = http_build_query($params);
        $url = $this->makeURL($path) . "?" . $paramsString;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cacert.pem");
        if ($this->userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        $this->do_curl_exec($ch);
        if (function_exists("logModuleCall")) {
            logModuleCall("planethoster", $actionName, $paramsString, $this->rawResponse, array($this->apiUser, $this->apiKey));
        }
        return $this->parsedResponse;
    }

    public function post($actionName, $path, array $params) {
        $params['api_user'] = $this->apiUser;
        $params['api_key'] = $this->apiKey;
        $url = $this->makeURL($path);
        $paramsString = http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsString);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cacert.pem");
        if ($this->userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        $this->do_curl_exec($ch);
        if (function_exists("logModuleCall")) {
            logModuleCall("planethoster", $actionName, $paramsString, $this->rawResponse, array($this->apiUser, $this->apiKey));
        }
        return $this->parsedResponse;
    }

    /* private */

    private function makeURL($path) {
        if (substr($path, 0, 1) != '/') {
            $path = "/{$path}";
        }
        return $this->baseUrl . $path;
    }

    private function do_curl_exec($ch) {
        $ret = curl_exec($ch);
        $this->rawResponse = $ret; // NOTE: is `FALSE` on error
        if ($this->rawResponse) {
            $this->parsedResponse = json_decode($this->rawResponse, true); // decode as array, not stdClass
        } else {
            $this->parsedResponse = null;
        }
        $reqInfo = curl_getinfo($ch);
        $errMsg = curl_error($ch);
        if ($errMsg) {
            $this->error = "curl error: " . $errMsg;
        }
        curl_close($ch);
    }

}

function planethoster_WHMCS_version() {
    return '0.1.1';
}

function planethoster_WHMCS_userAgent() {
    $version = planethoster_WHMCS_version();
    return "PlanetHoster WHMCS module version {$version}";
}

function planethoster_getConfigArray() {
    $version = planethoster_WHMCS_version();
    $configarray = array(
        "Description" => array(
            "Type" => "System",
            "Value" => "PlanetHoster Domain Reseller API Account (WHMCS client version {$version})"
        ),
        "API_User" => array("Type" => "text", "Size" => "40", "Description" => "Enter your PlanetHoster Reseller Account API User token here"),
        "API_Key" => array("Type" => "password", "Size" => "20", "Description" => "Enter your PlanetHoster Reseller Account API Key token here"),
    );
    return $configarray;
}

function planethoster_GetNameservers($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld']
    );
    $res = $ph->get('GetNameservers', '/get-nameservers', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    $values = array();
    if (isset($res['error']) && $res['error']) {
        $values['error'] = $res['error'];
        $values['error_code'] = $res['error_code'];
    } else {
        $values['message'] = $res['message'];
    }
    if (isset($res['nameservers']) && $res['nameservers']) {
        $nameservers = $res['nameservers'];
        $i = 1;
        foreach ($nameservers as $ns) {
            $values["ns{$i}"] = $ns['host'];
            $i++;
        }
    }
    return $values;
}

function planethoster_SaveNameservers($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'ns1' => $params['ns1'],
        'ns2' => $params['ns2'],
        'ns3' => $params['ns3'],
        'ns4' => $params['ns4'],
        'ns5' => $params['ns5']
    );
    $res = $ph->post('SaveNameservers', '/save-nameservers', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    $values = array();
    if (isset($res['error']) && $res['error']) {
        $values = $res;
    } else {
        $values['message'] = $res['message'];
    }
    return $values;
}

function planethoster_GetRegistrarLock($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $res = $ph->get('GetRegistrarLock', '/get-registrar-lock', $requestParams);
    if (!$res) {
        return null;
    }
    if (isset($res['error']) && $res['error']) {
        return null;
    }
    return $res['is_locked'] ? 'locked' : 'unlocked';
}

function planethoster_SaveRegistrarLock($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'lock_action' => $params['lockenabled'] == 'locked' ? 'Lock' : 'Unlock'
    );
    $res = $ph->post('SaveRegistrarLock', '/save-registrar-lock', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    return array('message' => $res['message']);
}

function planethoster_RegisterDomain($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'period' => $params['regperiod'],
        'ns1' => $params['ns1'],
        'ns2' => $params['ns2'],
        'ns3' => $params['ns3'],
        'ns4' => $params['ns4'],
        'ns5' => $params['ns5'],
        'registrant_name' => $params['firstname'] . ' ' . $params['lastname'],
        'registrant_company_name' => $params['companyname'],
        'registrant_address1' => $params['address1'],
        'registrant_address2' => $params['address2'],
        'registrant_city' => $params['city'],
        'registrant_state' => $params['state'],
        'registrant_postal_code' => $params['postcode'],
        'registrant_country' => $params['country'],
        'registrant_email' => $params['email'],
        'registrant_phone_number' => $params['fullphonenumber'],
        'id_protection' => $params['idprotection'] ? '1' : '',
        'addtl_fields' => $params['additionalfields'],
    );
    $res = $ph->post('RegisterDomain', '/register-domain', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    # TODO: update expirydate of domain if present in response
    return array('message' => $res['message'], 'expiry_date' => $res['expiry_date']);
}

function planethoster_TransferDomain($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'epp_code' => $params["transfersecret"],
    );
    $res = $ph->post('TransferDomain', '/transfer-domain', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    return array('message' => $res['message']);
}

function planethoster_RenewDomain($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'period' => $params['regperiod'],
    );
    $res = $ph->post('RenewDomain', '/renew-domain', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    # TODO: update expirydate of domain if present in response
    return array('message' => $res['message'], 'expiry_date' => $res['expiry_date']);
}

function planethoster_GetContactDetails($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $res = $ph->get('GetContactDetails', '/get-contact-details', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    $contacts = $res['contacts'];
    $values = array();
    foreach ($contacts as $c) {
        $type = $c['contact_type'];
        $values[$type] = array();
        list($firstName, $lastName) = explode(" ", $c['name'], 2);
        $values[$type]['First Name'] = $firstName;
        $values[$type]['Last Name'] = $lastName;
        $values[$type]['Email'] = $c['email'];
        $values[$type]['Address1'] = $c['addr']['address1'];
        $values[$type]['Address2'] = $c['addr']['address2'];
        $values[$type]['City'] = $c['addr']['city'];
        $values[$type]['Province'] = $c['addr']['state'];
        $values[$type]['PostalCode'] = $c['addr']['postal_code'];
        $values[$type]['Country'] = $c['addr']['country'];
        $values[$type]['Phone Number'] = $c['phone_number'];
    }
    $values['message'] = $res['message'];
    return $values;
}

function planethoster_SaveContactDetails($params) {
    $contactDetails = $params['contactdetails'];
    $contactDetailsParams = array();
    $contactTypes = array();
    foreach ($contactDetails as $type => $info) {
        $contactTypes[] = $type;
        $contactDetailsParams[$type] = array();
        $fullName = $info['First Name'] . " " . $info['Last Name'];
        $contactDetailsParams[$type . '_name'] = $fullName;
        $contactDetailsParams[$type . '_email'] = $info['Email'];
        $contactDetailsParams[$type . '_address1'] = $info['Address1'];
        $contactDetailsParams[$type . '_address2'] = $info['Address2'];
        $contactDetailsParams[$type . '_city'] = $info['City'];
        $contactDetailsParams[$type . '_state'] = $info['Province'];
        $contactDetailsParams[$type . '_postal_code'] = $info['PostalCode'];
        $contactDetailsParams[$type . '_country'] = $info['Country'];
        $contactDetailsParams[$type . '_phone_number'] = $info['Phone Number'];
    }

    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array_merge($contactDetailsParams, array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
        'contact_types' => join(',', $contactTypes),
        'addtl_fields' => $params['additionalfields']
    ));
    $res = $ph->post('SaveContactDetails', '/save-contact-details', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    return array('message' => $res['message']);
}

// NOTE: emails EPP Code to client
function planethoster_GetEppCode($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $res = $ph->post('GetEppCode', '/email-epp-code', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    return array('message' => $res['message']);
}

function planethoster_DomainInfo($params, $logAction = 'DomainInfo') {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $res = $ph->get($logAction, '/domain-info', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    return $res;
}

// NOTE: for crons/domainsync.php, syncs domains that are locally marked as 'Pending Transfer'
function planethoster_TransferSync($params) {
    $res = planethoster_DomainInfo($params, 'TransferSync');
    $values = array();
    if ($res['error']) {
        return $res;
    }
    $regStatus = $res['registration_status'];
    $transferRequestStatus = $res['transfer_request_status'];
    if ($regStatus == 'Active') {
        $values['completed'] = true;
    } else if ($regStatus == 'Cancelled' || $transferRequestStatus == 'Denied') {
        $values['failed'] = true;
        $values['reason'] = $res['transfer_request_denied_reason'];
    // pending or pending transfer
    } else {
        $values['pendingtransfer'] = true;
        if ($transferRequestStatus == 'Confirmed') {
            $values['reason'] = "Awaiting registry approval";
        } else if ($transferRequestStatus == 'Pending') {
            $values['reason'] = "Awaiting client to confirm transfer by email";
        } else {
            $values['reason'] = $res['registration_status_info'];
        }
    }
    return $values;
}

// NOTE: for crons/domainsync.php, syncs domains that are locally marked as 'Active'
function planethoster_Sync($params) {
    $res = planethoster_DomainInfo($params, 'Sync');
    if ($res['error']) {
        return $res;
    }
    $expiryDate = $res['expiry_date']; // example: '2017-01-01' (YYYY-MM-DD)
    $regStatus = $res['registration_status'];
    $values = array();
    if ($expiryDate && $regStatus == 'Active') {
        $values['status'] = 'Active';
    }
    if ($expiryDate) {
        $values['expirydate'] = $expiryDate;
    }
    return $values;
}

function planethoster_GetDNS($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $res = $ph->get('GetDNS', '/get-ph-dns-records', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    $records = $res['records']; // @var array<array<string => string>, ...>
    return $records;
}

function planethoster_SaveDNS($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld'],
    );
    $records = $params['dnsrecords'];
    $newValues = array();
    $idx = 1;
    foreach ($records as $_key => $val) {
        if ($val['type'] && $val['address']) {
            $newValues["type{$idx}"] = $val['type'];
            $newValues["hostname{$idx}"] = $val['hostname'];
            $newValues["address{$idx}"] = $val['address'];
            if (isset($val['priority']) && $val['priority']) {
                $newValues["priority{$idx}"] = $val['priority'];
            }
            $idx++;
        }
    }
    $requestParams = array_merge($requestParams, $newValues);
    $res = $ph->post('SaveDNS', '/save-ph-dns-records', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    if (isset($res['error']) && $res['error']) {
        return $res;
    }
    return $res;
}

function planethoster_AdminCustomButtonArray($params) {
    return array();
}


function planethoster_CheckAvailability($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $requestParams = array(
        'tld' => $params['tld'],
        'sld' => $params['sld']
    );
    $res = $ph->get('CheckAvailability', '/check-availability', $requestParams);
    if (!$res) {
        return array('error' => $ph->error);
    }
    $values = array();
    if (isset($res['error']) && $res['error']) {
        $values = $res;
    } else {
        $values = array_merge($values, $res);
    }
    $values['available'] = isset($res['available']) && $res['available'] ? true : false;
    return $values;
}

function planethoster_TestConnection($params) {
    $ph = new PlanetHosterAPIClient($params['API_User'], $params['API_Key'], array('userAgent' => planethoster_WHMCS_userAgent()));
    $res = $ph->get('TestConnection', '/test-connection', array());
    if (!$res) {
        return array('error' => $ph->error);
    }
    $values = array();
    if (isset($res['error']) && $res['error']) {
        $values = $res;
    } else {
        $values['message'] = $res['message'];
    }
    return $values;
}
