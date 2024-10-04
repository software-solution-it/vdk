<?php

include_once '../interfaces/CampaignServiceInterface.php';

class CampaignService implements CampaignServiceInterface {
    private $campaignModel;

    public function __construct($campaignModel) {
        $this->campaignModel = $campaignModel;
    }

    public function createCampaign($data) {

    }

    public function trackCampaignProgress($campaignId) {
    }
}
