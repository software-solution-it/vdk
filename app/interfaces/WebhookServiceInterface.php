<?php

interface WebhookServiceInterface {
    public function registerWebhook($data);
    public function triggerWebhook($event);
}
