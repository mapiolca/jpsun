<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Native document model display names, indexed by documentary type and stable identifier. */
class JpsunDocumentModelTranslations
{
	/** @return bool Native getListOfModels hook introduced in Dolibarr 23. */
	public static function isDynamicSelectionAvailable()
	{
		return version_compare(DOL_VERSION, '23.0.0', '>=') && isModEnabled('jpsun');
	}

	/** @return array<string,array<string,string>> */
	public static function getNameKeys()
	{
		return array(
			'bankaccount' => array(
				'jpsunsepamandate' => 'JpsunSepaMandateModelLabel',
			),
			'order' => array(
				'jpsun' => 'JpsunOrderModelName',
			),
			'invoice' => array(
				'jpsuninvoice' => 'JpsunInvoiceModelTitle',
			),
			'contract' => array(
				'contratpartv3' => 'JpsunContractPrivateName',
				'jpsun' => 'JpsunContractStandardName',
				'jpsunpro' => 'JpsunContractProName',
				'soleilaquitain' => 'JpsunSoleilAquitainContractName',
			),
			'product' => array(
				'jpsunproductsheet' => 'JpsunProductSheetName',
			),
			'project' => array(
				'ddrenedis' => 'JpsunDdrEnedisModelName',
				'jpsun_projectlabels' => 'JpsunProjectLabelsPdfModel',
				'jpsun_projectstock' => 'JpsunProjectStockModel',
				'jpsun_projectsynthesis' => 'JPSUNProjectPdfSynthesis',
			),
			'propal' => array(
				'jpsunwithpictures' => 'JpsunWithPicturesName',
				'jpsunwithoutpictures' => 'JpsunWithoutPicturesName',
				'jpsunwithoutpicturescover' => 'JpsunCoverModel',
			),
			'stock' => array(
				'jpsun_stock' => 'JpsunStockModelName',
			),
			'order_supplier' => array(
				'jpsun' => 'JpsunSupplierOrderName',
			),
			'supplier_proposal' => array(
				'jpsun' => 'DocModelJPSUNName',
				'jpsunwithprices' => 'DocModelJPSUNwithpricesName',
			),
		);
	}
}
