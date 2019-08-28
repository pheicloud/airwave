<?php

namespace Pheicloud\Aruba;

use Pheicloud\Aruba\Ac\Ac7210;
use Pheicloud\Aruba\Iap\Iap;

class Aruba
{
    private $ac7210;
    private $iap;

    public function __construct()
    {
        $this->ac7210 = new Ac7210();
        $this->iap = new Iap();
    }

    //Ac7210
    public function ac7210Login()
    {
        return $this->ac7210->login();
    }

    public function ac7210Logout()
    {
        return $this->ac7210->logout();
    }

    public function ac7210QueryVPN()
    {
        return $this->ac7210->queryVPN();
    }

    public function ac7210ApList()
    {
        return $this->ac7210->apList();
    }

    public function ac7210ApPage()
    {
        return $this->ac7210->apPage();
    }

    public function ac7210RogueApClients()
    {
        return $this->ac7210->rogueApClients();
    }

    public function ac7210RogueAp()
    {
        return $this->ac7210->rogueAp();
    }

    public function ac7210UsageSummaryClientDevType()
    {
        return $this->ac7210->usageSummaryClientDevType();
    }

    public function ac7210UsageSummarApplication()
    {
        return $this->ac7210->usageSummaryApplication();
    }

    public function ac7210PerformanceClients()
    {
        return $this->ac7210->performanceClients();
    }

    public function ac7210PerformanceAps()
    {
        return $this->ac7210->performanceAps();
    }

    // Iap
    public function iapLogin()
    {
        return $this->iap->login();
    }

    public function iapLogout()
    {
        return $this->iap->logout();
    }

    public function iapQueryProfile()
    {
        return $this->iap->queryProfile();
    }

    public function iapClientList()
    {
        return $this->iap->clientList();
    }

    public function iapApList()
    {
        return $this->iap->apList();
    }

    public function iapApIp()
    {
        return $this->iap->apIp();
    }

    public function iapSetSSID($profile, $ssid)
    {
        return $this->iap->setSSID($profile, $ssid);
    }

    public function iapShowStats($ip)
    {
        return $this->iap->showStats($ip);
    }

    public function iapExport()
    {
        return $this->iap->export();
    }

    public function iapImport($filepath)
    {
        return $this->iap->import($filepath);
    }
}
