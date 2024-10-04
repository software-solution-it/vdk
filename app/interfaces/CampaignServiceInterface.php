<?php

interface CampaignServiceInterface {
    public function createCampaign($data);
    public function trackCampaignProgress($campaignId);
}
