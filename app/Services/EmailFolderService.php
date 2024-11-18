<?php

namespace App\Services;

use App\Models\EmailFolder;

class EmailFolderService {
    private $emailFolderModel;

    public function __construct($db) {
        $this->emailFolderModel = new EmailFolder($db);
    }

    public function getFoldersByEmailId($email_id) {
        return $this->emailFolderModel->getFoldersByEmailAccountId($email_id);
    }
}
