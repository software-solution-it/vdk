<?php
namespace App\Services;

use App\Models\FolderAssociation;

class FolderAssociationService {
    private $folderAssociationModel;

    public function __construct(FolderAssociation $folderAssociationModel) {
        $this->folderAssociationModel = $folderAssociationModel;
    }

    public function createOrUpdateAssociation($emailAccountId, $folderId, $folderType) {
        return $this->folderAssociationModel->createOrUpdateAssociation(
            $emailAccountId,
            $folderId,
            $folderType
        );
    }
    

    public function getAssociationsByEmailAccount($emailAccountId) {
        return $this->folderAssociationModel->getAssociationsByEmailAccount($emailAccountId);
    }

    public function getAssociationsByEmailAccountList($emailAccountId) {
        return $this->folderAssociationModel->getAssociationsByEmailAccountList($emailAccountId);
    }

}
