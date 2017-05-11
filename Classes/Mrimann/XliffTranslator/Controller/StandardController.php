<?php
namespace Mrimann\XliffTranslator\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mrimann.XliffTranslator".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the Mrimann.XliffTranslator package
 *
 * @Flow\Scope("singleton")
 */
class StandardController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * @Flow\Inject
	 * @var \Mrimann\XliffTranslator\XliffTranslatorServiceInterface
	 */
	protected $xliffTranslatorService;

	/**
	 * The index action that shows a list of packages that are available for testing
	 *
	 * @return void
	 */
	public function indexAction() {
		$this->view->assign('packages', $this->xliffTranslatorService->getAvailablePackages());
	}

	/**
	 * Renders the list of available packages
	 *
	 * @param string $packageKey
	 */
	public function packageAction($packageKey) {
		$sourceNames = $this->xliffTranslatorService->getAvailableXliffFiles($packageKey);
		array_walk($sourceNames, function (&$v) { $v = basename($v, '.xlf'); });
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('languages', explode(',', $this->settings['availableLanguages']));
		$this->view->assign('defaultLanguage', $this->settings['defaultLanguage']);
		$this->view->assign('sourceNames', $sourceNames);
	}

	/**
	 * Renders the form to edit a specific Xliff file
	 *
	 * @param string $packageKey
	 * @param string $editLang
	 * @param string $sourceName
	 */
	public function editAction($packageKey, $editLang, $sourceName = 'Main') {
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('sourceName', $sourceName);
		$this->view->assign('editLang', $editLang);

		list($sourceLang, $editMatrix) = $this->xliffTranslatorService->generateEditMatrix($packageKey, $editLang, $sourceName);
		$this->view->assign('editMatrix', $editMatrix);
		$this->view->assign('sourceLang', $sourceLang);
	}

	/**
	 * Updates the translations from the edit form
	 *
	 * @param string $packageKey
	 * @param string $editLang
	 * @param array $editUnits
	 * @param string $sourceName
	 */
	public function updateAction($packageKey, $editLang, array $editUnits, $sourceName = 'Main') {
		list($sourceLang, $matrixToSave) = $this->xliffTranslatorService->getEditMatrixToSave($packageKey, $editLang, $editUnits, $sourceName);
		$this->xliffTranslatorService->saveXliff($packageKey, $sourceLang, $editLang, $matrixToSave, $this->controllerContext, $sourceName);

		// redirect the user back to the edit page
		$this->addFlashMessage(
			'Your translations were successfully saved!',
			'Yippie!',
			'OK'
		);

		$this->redirect(
			'edit',
			'Standard',
			'Mrimann.XliffTranslator',
			array(
				'packageKey' => $packageKey,
				'sourceName' => $sourceName,
				'editLang' => $editLang
			)
		);
	}

	/**
	 * Renders the form to translate a specific Xliff file from language A to language B
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 * @param string $sourceName
	 */
	public function translateAction($packageKey, $fromLang, $toLang, $sourceName = 'Main') {
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('languages', explode(',', $this->settings['availableLanguages']));
		$this->view->assign('fromLang', $fromLang);
		$this->view->assign('toLang', $toLang);
		$this->view->assign('sourceName', $sourceName);

		$translationMatrix = $this->xliffTranslatorService->generateTranslationMatrix($packageKey, $fromLang, $toLang, $sourceName);
		$this->view->assign('translationMatrix', $translationMatrix);
	}

	/**
	 * Saves the translations from the translation form
	 *
	 * @param string $packageKey
	 * @param string $fromLang
	 * @param string $toLang
	 * @param array $translationUnits
	 * @param string $sourceName
	 */
	public function saveTranslationsAction($packageKey, $fromLang, $toLang, array $translationUnits, $sourceName = 'Main') {
		$matrixToSave = $this->xliffTranslatorService->getTranslationMatrixToSave($packageKey, $fromLang, $toLang, $translationUnits, $sourceName);
		$this->xliffTranslatorService->saveXliff($packageKey, $fromLang, $toLang, $matrixToSave, $this->getControllerContext(), $sourceName);

		// redirect the user back to the translation page
		$this->addFlashMessage(
			'Your translations were successfully saved!',
			'Yippie!',
			'OK'
		);

		$this->redirect(
			'translate',
			'Standard',
			'Mrimann.XliffTranslator',
			array(
				'packageKey' => $packageKey,
				'sourceName' => $sourceName,
				'fromLang' => $fromLang,
				'toLang' => $toLang
			)
		);
	}
}

?>